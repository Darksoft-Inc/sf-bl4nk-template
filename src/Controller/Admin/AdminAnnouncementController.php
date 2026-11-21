<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\User;
use App\Form\AnnouncementFormType;
use App\Repository\AnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Pagination\PaginatorInterface;

#[Route('/admin/announcements')]
#[IsGranted('ROLE_ADMIN')]
final class AdminAnnouncementController extends AbstractController
{
    #[Route('', name: 'app_admin_announcement_index')]
    public function index(AnnouncementRepository $announcements, PaginatorInterface $paginator): Response
    {
        $query = $announcements->createQueryBuilder('a')
            ->orderBy('a.publishedAt', \SortDirection::Descending)
            ->addOrderBy('a.id', \SortDirection::Descending);

        $pagination = $paginator->query($query)->perPage(10)->paginate();

        return $this->render('admin/announcement/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_admin_announcement_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $announcement = new Announcement('', '', new \DateTimeImmutable('now'), $this->currentUser());

        $form = $this->createForm(AnnouncementFormType::class, $announcement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($announcement);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.flash.announcement_created'));

            return $this->redirectToRoute('app_admin_announcement_index');
        }

        return $this->render('admin/announcement/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_announcement_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id, AnnouncementRepository $announcements, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $announcement = $announcements->find($id);

        if (null === $announcement) {
            throw $this->createNotFoundException('The announcement does not exist.');
        }

        $form = $this->createForm(AnnouncementFormType::class, $announcement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.flash.announcement_updated'));

            return $this->redirectToRoute('app_admin_announcement_index');
        }

        return $this->render('admin/announcement/edit.html.twig', [
            'announcement' => $announcement,
            'form' => $form,
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
