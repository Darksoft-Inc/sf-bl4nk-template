<?php

namespace App\Entity;

use App\Repository\MapRouteTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapRouteTypeRepository::class)]
class MapRouteType
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

    #[ORM\ManyToOne(inversedBy: 'routeTypes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Map $map;

    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * The line color of every route of this type, as a "#rrggbb" string.
     */
    #[ORM\Column(length: 7)]
    private string $color;

    /** @var Collection<int, MapRoute> */
    #[ORM\OneToMany(mappedBy: 'type', targetEntity: MapRoute::class, cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $routes;

    public function __construct(Map $map, string $name, string $color)
    {
        $this->map = $map;
        $this->name = $name;
        $this->color = $color;
        $this->routes = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /** @return Collection<int, MapRoute> */
    public function getRoutes(): Collection
    {
        return $this->routes;
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
