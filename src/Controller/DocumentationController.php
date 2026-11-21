<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Serves the documentation pages. A page is a template named
 * documentation/{page}.{locale}.html.twig that holds its prose directly, as
 * recorded for approach A in docs/DECISIONS.md. Adding a page or a locale
 * variant needs no code: drop the template file and the route finds it.
 */
class DocumentationController extends AbstractController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    #[Route('/docs/{page}', name: 'app_documentation_show', requirements: ['page' => '[a-z0-9-]+'])]
    public function show(string $page, Request $request): Response
    {
        $locales = array_unique([$request->getLocale(), $request->getDefaultLocale()]);

        foreach ($locales as $locale) {
            $template = \sprintf('documentation/%s.%s.html.twig', $page, $locale);

            if ($this->twig->getLoader()->exists($template)) {
                return $this->render($template);
            }
        }

        throw $this->createNotFoundException(\sprintf('No documentation page named "%s" exists.', $page));
    }
}
