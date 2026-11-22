<?php

namespace App\Controller\Admin;

use App\Entity\MapPoint;
use App\Form\Map\MapPointFormType;
use App\Map\Symbols;
use App\Repository\MapPointRepository;
use App\Repository\MapRepository;
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
final class AdminMapPointController extends AbstractController
{
    #[Route('/{mapId}/points/new', name: 'app_admin_map_point_new', requirements: ['mapId' => '\d+'])]
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

        $point = new MapPoint($map, '', 0.0, 0.0);

        $form = $this->pointForm($point);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $point->setSymbols($this->symbolsFrom($form));

            $entityManager->persist($point);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.point_created'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $map->getId()]);
        }

        return $this->render('admin/map/point/new.html.twig', [
            'map' => $map,
            'form' => $form,
        ]);
    }

    #[Route('/points/{id}/edit', name: 'app_admin_map_point_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        int $id,
        MapPointRepository $points,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $point = $points->find($id);

        if (null === $point) {
            throw $this->createNotFoundException('The point does not exist.');
        }

        $form = $this->pointForm($point);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $point->setSymbols($this->symbolsFrom($form));

            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.point_updated'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $point->getMap()->getId()]);
        }

        return $this->render('admin/map/point/edit.html.twig', [
            'map' => $point->getMap(),
            'form' => $form,
        ]);
    }

    #[Route('/points/{id}/delete', name: 'app_admin_map_point_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        int $id,
        MapPointRepository $points,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $point = $points->find($id);

        if (null === $point) {
            throw $this->createNotFoundException('The point does not exist.');
        }

        if (!$this->isCsrfTokenValid('delete-map-point', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.admin.map.flash.delete_not_allowed'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $point->getMap()->getId()]);
        }

        $mapId = $point->getMap()->getId();

        $entityManager->remove($point);
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('app.admin.map.flash.point_deleted'));

        return $this->redirectToRoute('app_admin_map_edit', ['id' => $mapId]);
    }

    /**
     * @return FormInterface<MapPoint>
     */
    private function pointForm(MapPoint $point): FormInterface
    {
        $map = $point->getMap();

        return $this->createForm(MapPointFormType::class, $point, [
            'space_width' => $map->getSpaceWidth(),
            'space_height' => $map->getSpaceHeight(),
            'symbols_text' => Symbols::format($point->getSymbols()),
        ]);
    }

    /**
     * @param FormInterface<MapPoint> $form
     *
     * @return list<string>
     */
    private function symbolsFrom(FormInterface $form): array
    {
        $symbols = $form->get('symbols')->getData();
        \assert(null === $symbols || \is_string($symbols));

        return Symbols::parse($symbols ?? '');
    }
}
