<?php

namespace App\Entity;

use App\Repository\MaintenanceRunRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One run of a maintenance task, recorded whether the scheduler or an admin
 * page triggered it.
 */
#[ORM\Entity(repositoryClass: MaintenanceRunRepository::class)]
#[ORM\Table(name: 'maintenance_run')]
class MaintenanceRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $task;

    /**
     * Who started the run: "schedule" or "manual".
     */
    #[ORM\Column(length: 16)]
    private string $trigger;

    #[ORM\Column(length: 255)]
    private string $result;

    #[ORM\Column]
    private \DateTimeImmutable $ranAt;

    public function __construct(string $task, string $trigger, string $result, \DateTimeImmutable $ranAt)
    {
        $this->task = $task;
        $this->trigger = $trigger;
        $this->result = $result;
        $this->ranAt = $ranAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getRanAt(): \DateTimeImmutable
    {
        return $this->ranAt;
    }
}
