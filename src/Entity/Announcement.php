<?php

namespace App\Entity;

use App\Repository\AnnouncementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The dataset key this row was imported under, or null when the row was
     * created outside the dataset import. The import never touches rows
     * whose key is null.
     */
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $datasetSlug = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    public function __construct(string $title, string $content, \DateTimeImmutable $publishedAt, User $author)
    {
        $this->title = $title;
        $this->content = $content;
        $this->publishedAt = $publishedAt;
        $this->author = $author;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getDatasetSlug(): ?string
    {
        return $this->datasetSlug;
    }

    public function setDatasetSlug(?string $datasetSlug): static
    {
        $this->datasetSlug = $datasetSlug;

        return $this;
    }
}
