<?php

namespace App\Entity;

use App\Repository\MapRouteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapRouteRepository::class)]
class MapRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'routes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Map $map;

    #[ORM\ManyToOne(inversedBy: 'routes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MapRouteType $type;

    /**
     * The optional name of the route, shown in the line popup. Null falls
     * back to the type name.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Major routes are drawn thicker and stay visible under the "major lines"
     * filter state of the public map.
     */
    #[ORM\Column]
    private bool $isMajor = false;

    /**
     * The line vertices in the coordinate space of the map, in pixels, from
     * the start of the route to its end.
     *
     * @var list<array{x: float, y: float}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $points = [];

    public function __construct(Map $map, MapRouteType $type)
    {
        $this->map = $map;
        $this->type = $type;

        $map->getRoutes()->add($this);
        $type->getRoutes()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMap(): Map
    {
        return $this->map;
    }

    public function getType(): MapRouteType
    {
        return $this->type;
    }

    public function setType(MapRouteType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

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

    public function isMajor(): bool
    {
        return $this->isMajor;
    }

    public function setIsMajor(bool $isMajor): static
    {
        $this->isMajor = $isMajor;

        return $this;
    }

    /** @return list<array{x: float, y: float}> */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @param list<array{x: float, y: float}> $points
     */
    public function setPoints(array $points): static
    {
        $this->points = $points;

        return $this;
    }

    /**
     * The popup header: the route name, or the type name when the route
     * carries none.
     */
    public function getDisplayName(): string
    {
        return $this->name ?? $this->type->getName();
    }
}
