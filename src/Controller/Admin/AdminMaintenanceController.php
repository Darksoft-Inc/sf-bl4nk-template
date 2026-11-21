<?php

namespace App\Controller\Admin;

use App\Uploads\UploadInUseException;
use App\Uploads\UploadKindInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lists the uploads of every registered kind and removes them on request.
 * Each kind owns its location and rules; the pages treat them uniformly.
 */
#[Route('/admin/maintenance')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMaintenanceController extends AbstractController
{
    /** @var array<string, UploadKindInterface> */
    private readonly array $uploadKinds;

    /**
     * @param iterable<UploadKindInterface> $uploadKinds
     */
    public function __construct(
        #[AutowireIterator('app.upload_kind')] iterable $uploadKinds,
    ) {
        $kinds = [];
        foreach ($uploadKinds as $kind) {
            $kinds[$kind->getName()] = $kind;
        }

        $this->uploadKinds = $kinds;
    }

    #[Route('/uploads', name: 'app_admin_maintenance_uploads')]
    public function uploads(): Response
    {
        $uploads = [];
        foreach ($this->uploadKinds as $name => $kind) {
            $uploads[$name] = $kind->list();
        }

        return $this->render('admin/maintenance/uploads.html.twig', [
            'uploads' => $uploads,
        ]);
    }

    #[Route('/uploads/{kind}/{filename}/remove', name: 'app_admin_maintenance_upload_remove', requirements: ['filename' => '[\w.\-]+'], methods: ['POST'])]
    public function removeUpload(string $kind, string $filename, Request $request, TranslatorInterface $translator): Response
    {
        $uploadKind = $this->uploadKinds[$kind] ?? null;

        if (null === $uploadKind) {
            throw $this->createNotFoundException(\sprintf('The upload kind "%s" does not exist.', $kind));
        }

        if (!$this->isCsrfTokenValid('remove-upload', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.admin.uploads.flash.not_removed'));

            return $this->redirectToRoute('app_admin_maintenance_uploads');
        }

        try {
            $uploadKind->remove($filename);
        } catch (UploadInUseException) {
            // A kind that refuses the removal keeps its file: the avatar
            // clears the reference instead, so this case has no catch there.
            $this->addFlash('error', $translator->trans('app.admin.uploads.flash.in_use'));

            return $this->redirectToRoute('app_admin_maintenance_uploads');
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $this->addFlash('success', $translator->trans('app.admin.uploads.flash.removed'));

        return $this->redirectToRoute('app_admin_maintenance_uploads');
    }
}
