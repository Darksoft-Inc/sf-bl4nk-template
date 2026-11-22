<?php

namespace App\Controller;

use App\Reference\Reference;
use App\Reference\ReferencePage;
use App\Reference\ReferenceRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The generated reference pages: every page comes from the Reference
 * attribute of a service, collected and validated at build time. The
 * controller only resolves slugs, nothing is computed here.
 */
#[Route('/reference')]
final class ReferenceController extends AbstractController
{
    #[Route('', name: 'app_reference_index')]
    public function index(ReferenceRegistry $references): Response
    {
        return $this->render('reference/index.html.twig', [
            'groups' => $references->grouped(),
        ]);
    }

    #[Route('/{slug}', name: 'app_reference_show', requirements: ['slug' => Reference::SLUG_REQUIREMENT])]
    public function show(string $slug, ReferenceRegistry $references): Response
    {
        $page = $references->find($slug);

        if (null === $page) {
            throw $this->createNotFoundException('The reference page does not exist.');
        }

        $relatedPages = [];
        foreach ($page->related as $relatedSlug) {
            // The pass validated every related slug at build time.
            $relatedPage = $references->find($relatedSlug);
            \assert($relatedPage instanceof ReferencePage);

            $relatedPages[] = $relatedPage;
        }

        return $this->render('reference/show.html.twig', [
            'page' => $page,
            'relatedPages' => $relatedPages,
        ]);
    }
}
