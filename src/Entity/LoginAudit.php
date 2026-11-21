<?php

namespace App\Entity;

use App\Repository\LoginAuditRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One authentication event: a form login or the restore of a persistent
 * session. Requests served from a live session write no row.
 *
 * The identifier is a snapshot: the user relation is cleared when the account
 * is deleted, the row itself stays for its retention period.
 */
#[ORM\Entity(repositoryClass: LoginAuditRepository::class)]
#[ORM\Table(name: 'login_audit')]
#[ORM\Index(name: 'idx_login_audit_created_at', columns: ['created_at'])]
class LoginAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * The user identifier at the time of the event.
     */
    #[ORM\Column(length: 180)]
    private string $identifier;

    /**
     * The client IP at the time of the event, null when the request had none.
     */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    /**
     * The start of the User-Agent header, kept short enough for a row size.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(enumType: LoginAuditType::class, length: 10)]
    private LoginAuditType $type;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        LoginAuditType $type,
        \DateTimeImmutable $createdAt,
        ?string $ip = null,
        ?string $userAgent = null,
    ) {
        $this->user = $user;
        $this->identifier = $user->getUserIdentifier();
        $this->type = $type;
        $this->createdAt = $createdAt;
        $this->ip = $ip;
        $this->userAgent = null !== $userAgent ? mb_substr($userAgent, 0, 255) : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getType(): LoginAuditType
    {
        return $this->type;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
