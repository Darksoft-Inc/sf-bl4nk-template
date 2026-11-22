<?php

namespace App\Controller\Admin;

use App\Entity\MapRouteType;
use App\Form\Map\MapRouteTypeFormType;
use App\Repository\MapRepository;
use App\Repository\MapRouteTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/maps')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMapRouteTypeController extends AbstractController
{
    #[Route('/{mapId}/route-types/new', name: 'app_admin_map_route_type_new', requirements: ['mapId' => '\d+'])]
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

        $routeType = new MapRouteType($map, '', '#000000');

        $form = $this->createForm(MapRouteTypeFormType::class, $routeType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($routeType);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.route_type_created'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $map->getId()]);
        }

        return $this->render('admin/map/route_type/new.html.twig', [
            'map' => $map,
            'form' => $form,
        ]);
    }

    #[Route('/route-types/{id}/edit', name: 'app_admin_map_route_type_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        int $id,
        MapRouteTypeRepository $routeTypes,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $routeType = $routeTypes->find($id);

        if (null === $routeType) {
            throw $this->createNotFoundException('The route type does not exist.');
        }

        $form = $this->createForm(MapRouteTypeFormType::class, $routeType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.route_type_updated'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $routeType->getMap()->getId()]);
        }

        return $this->render('admin/map/route_type/edit.html.twig', [
            'map' => $routeType->getMap(),
            'form' => $form,
        ]);
    }

    #[Route('/route-types/{id}/delete', name: 'app_admin_map_route_type_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        int $id,
        MapRouteTypeRepository $routeTypes,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $routeType = $routeTypes->find($id);

        if (null === $routeType) {
            throw $this->createNotFoundException('The route type does not exist.');
        }

        if (!$this->isCsrfTokenValid('delete-map-route-type', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.admin.map.flash.delete_not_allowed'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $routeType->getMap()->getId()]);
        }

        $mapId = $routeType->getMap()->getId();

        // The map relation cascades the removal over the routes of this
        // type: the confirm text on the map edit page names the route count.
        $entityManager->remove($routeType);
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('app.admin.map.flash.route_type_deleted'));

        return $this->redirectToRoute('app_admin_map_edit', ['id' => $mapId]);
    }
}
