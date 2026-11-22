<?php

namespace App\Entity;

use App\Repository\SharedUploadRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One file a member shared with the others: an image or a plain-text
 * file. The stored name is the only name the disk and the upload kinds
 * see; the original name is display data.
 *
 * The owner is cleared when the account is deleted: the file stays with
 * an unknown uploader. No identifier snapshot is kept, unlike the login
 * audit: a shared file is member content, not a security record, and
 * the email of a deleted account has no reason to outlive the account.
 */
#[ORM\Entity(repositoryClass: SharedUploadRepository::class)]
#[ORM\Table(name: 'shared_upload')]
#[ORM\Index(name: 'idx_shared_upload_uploaded_at', columns: ['uploaded_at'])]
class SharedUpload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: SharedUploadType::class, length: 10)]
    private SharedUploadType $type;

    /**
     * 32 lower-case hex digits plus the extension the sniffed content
     * suggests, the shape SharedUploadStorage writes.
     */
    #[ORM\Column(unique: true)]
    private string $filename;

    /**
     * The name the uploader chose, kept for display only.
     */
    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column]
    private int $size;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(
        User $owner,
        SharedUploadType $type,
        string $filename,
        string $originalName,
        int $size,
        \DateTimeImmutable $uploadedAt,
    ) {
        $this->owner = $owner;
        $this->type = $type;
        $this->filename = $filename;
        $this->originalName = $originalName;
        $this->size = $size;
        $this->uploadedAt = $uploadedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): SharedUploadType
    {
        return $this->type;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    /**
     * The public URL of the stored file, under public/uploads/shared/.
     */
    public function getPublicUrl(): string
    {
        return match ($this->type) {
            SharedUploadType::Image => '/uploads/shared/images/'.$this->filename,
            SharedUploadType::Text => '/uploads/shared/text/'.$this->filename,
        };
    }
}
