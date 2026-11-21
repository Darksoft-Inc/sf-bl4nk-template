<?php

namespace App\Controller\Admin;

use App\Entity\MapRoute;
use App\Form\MapRouteFormType;
use App\Form\RoutePoints;
use App\Repository\MapRepository;
use App\Repository\MapRouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/maps')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMapRouteController extends AbstractController
{
    #[Route('/{mapId}/routes/new', name: 'app_admin_map_route_new', requirements: ['mapId' => '\d+'])]
    public function new(
        Request $request,
        int $mapId,
        MapRepository $maps,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $map = $maps->find($mapId);

        if (null === $map) {
            throw $this->createNotFoundException('The map does not exist.');
        }

        if ($map->getRouteTypes()->isEmpty()) {
            $this->addFlash('error', $translator->trans('app.admin.map.flash.no_route_types'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $map->getId()]);
        }

        $route = new MapRoute($map, $map->getRouteTypes()->first());

        $form = $this->routeForm($route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // The browser sends the optional name field as an empty string,
            // which means no name: the popup falls back to the type name.
            $route->setName($route->getName() ?: null);
            $route->setPoints($this->routePoints($route, $this->pointsText($form)));

            $entityManager->persist($route);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.route_created'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $map->getId()]);
        }

        return $this->render('admin/map/route/new.html.twig', [
            'map' => $map,
            'form' => $form,
        ]);
    }

    #[Route('/routes/{id}/edit', name: 'app_admin_map_route_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        int $id,
        MapRouteRepository $routes,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $route = $routes->find($id);

        if (null === $route) {
            throw $this->createNotFoundException('The route does not exist.');
        }

        $form = $this->routeForm($route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // The browser sends the optional name field as an empty string,
            // which means no name: the popup falls back to the type name.
            $route->setName($route->getName() ?: null);
            $route->setPoints($this->routePoints($route, $this->pointsText($form)));

            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.route_updated'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $route->getMap()->getId()]);
        }

        return $this->render('admin/map/route/edit.html.twig', [
            'map' => $route->getMap(),
            'form' => $form,
        ]);
    }

    #[Route('/routes/{id}/delete', name: 'app_admin_map_route_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        int $id,
        MapRouteRepository $routes,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $route = $routes->find($id);

        if (null === $route) {
            throw $this->createNotFoundException('The route does not exist.');
        }

        if (!$this->isCsrfTokenValid('delete-map-route', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.admin.map.flash.delete_not_allowed'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $route->getMap()->getId()]);
        }

        $mapId = $route->getMap()->getId();

        $entityManager->remove($route);
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('app.admin.map.flash.route_deleted'));

        return $this->redirectToRoute('app_admin_map_edit', ['id' => $mapId]);
    }

    /**
     * @return FormInterface<MapRoute>
     */
    private function routeForm(MapRoute $route): FormInterface
    {
        $map = $route->getMap();

        return $this->createForm(MapRouteFormType::class, $route, [
            'route_types' => $map->getRouteTypes()->toArray(),
            'points_text' => RoutePoints::format($route->getPoints()),
            'map' => $map,
        ]);
    }

    /**
     * @param FormInterface<MapRoute> $form
     */
    private function pointsText(FormInterface $form): string
    {
        $points = $form->get('points')->getData();
        \assert(null === $points || \is_string($points));

        return $points ?? '';
    }

    /**
     * The constraint on the points field ran the same parse, so the text the
     * form accepted converts without a second check.
     *
     * @return list<array{x: float, y: float}>
     */
    private function routePoints(MapRoute $route, string $text): array
    {
        $map = $route->getMap();

        return RoutePoints::parse($text, $map->getSpaceWidth(), $map->getSpaceHeight());
    }
}
