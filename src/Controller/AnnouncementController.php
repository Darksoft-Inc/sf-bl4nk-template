<?php

namespace App\Controller;

use App\Repository\AnnouncementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnouncementController extends AbstractController
{
    #[Route('/announcements', name: 'app_announcement_index')]
    public function index(AnnouncementRepository $announcements): Response
    {
        return $this->render('announcement/index.html.twig', [
            'announcements' => $announcements->findLatest(),
        ]);
    }

    #[Route('/announcements/{id}', name: 'app_announcement_show', requirements: ['id' => '\d+'])]
    public function show(int $id, AnnouncementRepository $announcements): Response
    {
        $announcement = $announcements->find($id);

        if (null === $announcement) {
            throw $this->createNotFoundException('The announcement does not exist.');
        }

        return $this->render('announcement/show.html.twig', [
            'announcement' => $announcement,
        ]);
    }
}
