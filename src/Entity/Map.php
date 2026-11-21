<?php

namespace App\Entity;

use App\Repository\MapRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapRepository::class)]
class Map
{
    /**
     * The space used for annotations when the map carries no image: points and
     * routes placed before an image is attached stay inside a known area.
     */
    public const NOMINAL_SPACE = 512;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * The file name inside the map image directory. Null means the map has no
     * background image: the annotations render over an empty space.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    /**
     * The pixel size of the current or last image. It defines the coordinate
     * space of the points and routes, so it survives an image being unset.
     */
    #[ORM\Column(nullable: true)]
    private ?int $imageWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $imageHeight = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $minZoom = 0;

    #[ORM\Column(options: ['default' => 4])]
    private int $maxZoom = 4;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, MapPoint> */
    #[ORM\OneToMany(mappedBy: 'map', targetEntity: MapPoint::class, cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $points;

    /** @var Collection<int, MapRoute> */
    #[ORM\OneToMany(mappedBy: 'map', targetEntity: MapRoute::class, cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $routes;

    /** @var Collection<int, MapRouteType> */
    #[ORM\OneToMany(mappedBy: 'map', targetEntity: MapRouteType::class, cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $routeTypes;

    public function __construct(string $name, \DateTimeImmutable $createdAt)
    {
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->points = new ArrayCollection();
        $this->routes = new ArrayCollection();
        $this->routeTypes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getImageWidth(): ?int
    {
        return $this->imageWidth;
    }

    public function getImageHeight(): ?int
    {
        return $this->imageHeight;
    }

    public function setImageSize(int $width, int $height): static
    {
        $this->imageWidth = $width;
        $this->imageHeight = $height;

        return $this;
    }

    /**
     * The annotation space in pixels: the image size when one is known, the
     * nominal square otherwise.
     */
    public function getSpaceWidth(): int
    {
        return $this->imageWidth ?? self::NOMINAL_SPACE;
    }

    public function getSpaceHeight(): int
    {
        return $this->imageHeight ?? self::NOMINAL_SPACE;
    }

    public function getMinZoom(): int
    {
        return $this->minZoom;
    }

    public function setMinZoom(int $minZoom): static
    {
        $this->minZoom = $minZoom;

        return $this;
    }

    public function getMaxZoom(): int
    {
        return $this->maxZoom;
    }

    public function setMaxZoom(int $maxZoom): static
    {
        $this->maxZoom = $maxZoom;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, MapPoint> */
    public function getPoints(): Collection
    {
        return $this->points;
    }

    /** @return Collection<int, MapRoute> */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    /** @return Collection<int, MapRouteType> */
    public function getRouteTypes(): Collection
    {
        return $this->routeTypes;
    }

    public function hasPointWithSymbols(): bool
    {
        foreach ($this->points as $point) {
            if ([] !== $point->getSymbols()) {
                return true;
            }
        }

        return false;
    }
}
