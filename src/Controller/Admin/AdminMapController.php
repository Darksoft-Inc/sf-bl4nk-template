<?php

namespace App\Controller\Admin;

use App\Entity\Map;
use App\Form\MapFormType;
use App\Form\MapImageFormType;
use App\Repository\MapRepository;
use App\Uploads\MapImageUploadKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Pagination\PaginatorInterface;

/**
 * The map section of the admin area: maps and their parameters, and the
 * pool of background images. Points, route types, and routes are managed
 * from the map edit page through their own controllers.
 */
#[Route('/admin/maps')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMapController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.map_image_dir')] private readonly string $mapImageDir,
    ) {
    }

    #[Route('', name: 'app_admin_map_index')]
    public function index(Request $request, MapRepository $maps, PaginatorInterface $paginator): Response
    {
        $query = $maps->createQueryBuilder('m')
            ->orderBy('m.createdAt', \SortDirection::Descending)
            ->addOrderBy('m.id', \SortDirection::Descending);

        $pagination = $paginator->query($query)->perPage(10)->paginate();

        return $this->render('admin/map/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_admin_map_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $map = new Map('', new \DateTimeImmutable('now'));

        $form = $this->createForm(MapFormType::class, $map, [
            'image_files' => $this->imageFiles(),
            'current_image' => null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('image')->getData();
            \assert(null === $image || \is_string($image));

            if (!$this->applyImage($map, $image ?? '', $translator)) {
                return $this->redirectToRoute('app_admin_map_new');
            }

            $entityManager->persist($map);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.created'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $map->getId()]);
        }

        return $this->render('admin/map/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_map_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        int $id,
        MapRepository $maps,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $map = $maps->find($id);

        if (null === $map) {
            throw $this->createNotFoundException('The map does not exist.');
        }

        $form = $this->createForm(MapFormType::class, $map, [
            'image_files' => $this->imageFiles(),
            'current_image' => $map->getImageFilename(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('image')->getData();
            \assert(null === $image || \is_string($image));

            if (!$this->applyImage($map, $image ?? '', $translator)) {
                return $this->redirectToRoute('app_admin_map_edit', ['id' => $id]);
            }

            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.admin.map.flash.updated'));

            return $this->redirectToRoute('app_admin_map_edit', ['id' => $id]);
        }

        return $this->render('admin/map/edit.html.twig', [
            'map' => $map,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_map_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        int $id,
        MapRepository $maps,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $map = $maps->find($id);

        if (null === $map) {
            throw $this->createNotFoundException('The map does not exist.');
        }

        if (!$this->isCsrfTokenValid('delete-map', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.admin.map.flash.delete_not_allowed'));

            return $this->redirectToRoute('app_admin_map_index');
        }

        $entityManager->remove($map);
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('app.admin.map.flash.deleted'));

        return $this->redirectToRoute('app_admin_map_index');
    }

    #[Route('/images', name: 'app_admin_map_images')]
    public function images(MapImageUploadKind $mapImages): Response
    {
        return $this->render('admin/map/images.html.twig', [
            'files' => $mapImages->list(),
            'uploadForm' => $this->createForm(MapImageFormType::class),
        ]);
    }

    #[Route('/images/upload', name: 'app_admin_map_image_upload', methods: ['POST'])]
    public function imageUpload(
        Request $request,
        MapImageUploadKind $mapImages,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(MapImageFormType::class);
        $form->handleRequest($request);

        $file = $form->isSubmitted() && $form->isValid() ? $form->get('image')->getData() : null;

        if (null === $file) {
            return $this->render('admin/map/images.html.twig', [
                'files' => $mapImages->list(),
                'uploadForm' => $form,
            ]);
        }

        \assert($file instanceof UploadedFile);

        $extension = $file->guessExtension();
        \assert(null !== $extension);

        $file->move($this->mapImageDir, bin2hex(random_bytes(16)).'.'.$extension);

        $this->addFlash('success', $translator->trans('app.admin.map.flash.image_uploaded'));

        return $this->redirectToRoute('app_admin_map_images');
    }

    /**
     * Applies the chosen pool image to the map and records its pixel size,
     * which defines the coordinate space of the points and routes. An empty
     * choice unsets the image: the map keeps its space and renders without
     * a background. The stored file stays in the pool either way.
     */
    private function applyImage(Map $map, string $filename, TranslatorInterface $translator): bool
    {
        if ('' === $filename) {
            $map->setImageFilename(null);

            return true;
        }

        $dimensions = getimagesize($this->mapImageDir.'/'.$filename);

        if (false === $dimensions) {
            $this->addFlash('error', $translator->trans('app.admin.map.flash.image_not_readable'));

            return false;
        }

        $map->setImageFilename($filename);
        $map->setImageSize($dimensions[0], $dimensions[1]);

        return true;
    }

    /**
     * The image pool is the content of the map image directory: files from
     * the admin upload and files an operator dropped there are listed the
     * same way.
     *
     * @return list<string>
     */
    private function imageFiles(): array
    {
        if (!is_dir($this->mapImageDir)) {
            return [];
        }

        $names = scandir($this->mapImageDir);
        \assert(\is_array($names));

        return array_values(array_filter(
            $names,
            static fn (string $filename): bool => !str_starts_with($filename, '.'),
        ));
    }
}
