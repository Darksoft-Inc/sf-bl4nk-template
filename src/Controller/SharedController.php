<?php

namespace App\Controller;

use App\Entity\SharedUploadType;
use App\Entity\User;
use App\Form\Shared\SharedUploadFormType;
use App\Repository\SharedUploadRepository;
use App\Uploads\SharedUploadStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Pagination\PaginatorInterface;

/**
 * The files the members share with each other: images and plain-text
 * files that any signed-in member uploads and every signed-in member
 * reads. The section is the first one restricted to members only.
 */
#[Route('/shared')]
#[IsGranted('ROLE_USER')]
final class SharedController extends AbstractController
{
    #[Route('', name: 'app_shared_index')]
    public function index(SharedUploadRepository $uploads, PaginatorInterface $paginator): Response
    {
        $query = $uploads->createQueryBuilder('s')->orderBy('s.uploadedAt', \SortDirection::Descending);

        $pagination = $paginator->query($query)->perPage(10)->paginate();

        return $this->render('shared/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_shared_new')]
    public function new(
        Request $request,
        SharedUploadStorage $storage,
        TranslatorInterface $translator,
        ClockInterface $clock,
    ): Response {
        $form = $this->createForm(SharedUploadFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            \assert($file instanceof UploadedFile);

            $user = $this->getUser();
            \assert($user instanceof User);

            $storage->store($user, $file, $clock->now());

            $this->addFlash('success', $translator->trans('app.shared.flash.uploaded'));

            return $this->redirectToRoute('app_shared_index');
        }

        return $this->render('shared/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_shared_show', requirements: ['id' => '\d+'])]
    public function show(int $id, SharedUploadRepository $uploads, SharedUploadStorage $storage): Response
    {
        $upload = $uploads->find($id);

        if (null === $upload) {
            throw $this->createNotFoundException('The shared file does not exist.');
        }

        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('shared/show.html.twig', [
            'upload' => $upload,
            'content' => SharedUploadType::Text === $upload->getType() ? $storage->readText($upload) : null,
            'canDelete' => $upload->getOwner()?->getId() === $user->getId(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_shared_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        int $id,
        SharedUploadRepository $uploads,
        SharedUploadStorage $storage,
        TranslatorInterface $translator,
    ): Response {
        $upload = $uploads->find($id);

        if (null === $upload) {
            throw $this->createNotFoundException('The shared file does not exist.');
        }

        $user = $this->getUser();
        \assert($user instanceof User);

        // The ownership check comes first: another member never reaches
        // the token check, and the owner of a file whose uploader was
        // deleted is nobody, so only the maintenance pages remove it.
        $owner = $upload->getOwner();
        if (null === $owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Only the uploader removes a shared file.');
        }

        if (!$this->isCsrfTokenValid('delete-shared', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.shared.flash.not_deleted'));

            return $this->redirectToRoute('app_shared_index');
        }

        $storage->remove($upload);

        $this->addFlash('success', $translator->trans('app.shared.flash.deleted'));

        return $this->redirectToRoute('app_shared_index');
    }
}
