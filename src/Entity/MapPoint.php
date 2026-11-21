<?php

namespace App\Entity;

use App\Repository\MapPointRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapPointRepository::class)]
class MapPoint
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

    #[ORM\ManyToOne(inversedBy: 'points')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Map $map;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * The position in the coordinate space of the map, in pixels: x grows to
     * the right and y grows downward from the top-left corner of the image.
     */
    #[ORM\Column]
    private float $x;

    #[ORM\Column]
    private float $y;

    /**
     * The ux-icons names of the symbols shown around the pin, in display
     * order. An empty list carries no symbol.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $symbols = [];

    public function __construct(Map $map, string $title, float $x, float $y)
    {
        $this->map = $map;
        $this->title = $title;
        $this->x = $x;
        $this->y = $y;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMap(): Map
    {
        return $this->map;
    }

    public function setMap(Map $map): static
    {
        $this->map = $map;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function setX(float $x): static
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): static
    {
        $this->y = $y;

        return $this;
    }

    /** @return list<string> */
    public function getSymbols(): array
    {
        return $this->symbols;
    }

    /**
     * @param list<string> $symbols
     */
    public function setSymbols(array $symbols): static
    {
        $this->symbols = $symbols;

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
