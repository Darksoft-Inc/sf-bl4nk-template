<?php

namespace App\Controller;

use App\Map\UxMapBuilder;
use App\Repository\MapRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MapController extends AbstractController
{
    public function __construct(
        private readonly UxMapBuilder $mapBuilder,
    ) {
    }

    #[Route('/maps', name: 'app_map_index')]
    public function index(MapRepository $maps): Response
    {
        return $this->render('map/index.html.twig', [
            'maps' => $maps->findLatestFirst(),
        ]);
    }

    #[Route('/maps/{id}', name: 'app_map_show', requirements: ['id' => '\d+'])]
    public function show(int $id, MapRepository $maps): Response
    {
        $map = $maps->find($id);

        if (null === $map) {
            throw $this->createNotFoundException('The map does not exist.');
        }

        return $this->render('map/show.html.twig', [
            'map' => $map,
            'uxMap' => $this->mapBuilder->build($map),
            'hasFilters' => !$map->getPoints()->isEmpty() || !$map->getRoutes()->isEmpty(),
            'hasSymbols' => $map->hasPointWithSymbols(),
        ]);
    }
}
