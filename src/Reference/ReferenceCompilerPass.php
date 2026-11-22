<?php

namespace App\Reference;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the Reference attributes of the container services into the
 * reference registry. Every problem fails the build naming the page:
 * a slug that breaks the pattern or that another page already carries,
 * a second Reference attribute on one class, an empty title or
 * summary, a detail that is not a ReferenceDetail, a related slug no
 * page carries, and a %parameter% the container does not define. The
 * pass also resolves every %parameter% citation, so the registry holds
 * the values the container had at build time.
 */
final class ReferenceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $pages = [];
        $slugs = [];

        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();

            if (null === $class || '' === $class || $definition->isAbstract()) {
                continue;
            }

            try {
                if (!class_exists($class)) {
                    continue;
                }

                $attributes = (new \ReflectionClass($class))->getAttributes(Reference::class);
            } catch (\Throwable) {
                // A class that fails to load carries no readable metadata.
                // The container could not instantiate it either: this
                // project ships without optional packages that some
                // vendor classes reference.
                continue;
            }

            if (\count($attributes) > 1) {
                throw new \InvalidArgumentException(\sprintf('The class "%s" declares more than one Reference attribute.', $class));
            }

            if ([] === $attributes) {
                continue;
            }

            $reference = $attributes[0]->newInstance();

            $validated = $this->assertPage($reference, $class, $slugs);

            $slugs[$reference->slug] = $class;

            $pages[] = [
                'slug' => $reference->slug,
                'title' => $this->resolve($reference->title, $reference->slug, $container),
                'summary' => $this->resolve($reference->summary, $reference->slug, $container),
                'group' => $this->resolve($reference->group, $reference->slug, $container),
                'details' => $this->resolveDetails($validated['details'], $reference->slug, $container),
                'related' => $validated['related'],
            ];
        }

        foreach ($pages as $page) {
            foreach ($page['related'] as $related) {
                if (!isset($slugs[$related])) {
                    throw new \InvalidArgumentException(\sprintf('The reference page "%s" is related to "%s", which no page carries.', $page['slug'], $related));
                }
            }
        }

        $container->register(ReferenceRegistry::class, ReferenceRegistry::class)
            ->addArgument($pages);
    }

    /**
     * Validates the page shape and returns its typed pieces: the
     * attribute arguments carry no runtime type check, so the arrays
     * are only trustworthy after this pass.
     *
     * @param array<string, string> $slugs the slugs already collected, keyed by slug
     *
     * @return array{details: list<ReferenceDetail>, related: list<string>}
     */
    private function assertPage(Reference $reference, string $class, array $slugs): array
    {
        if (!preg_match(Reference::SLUG_PATTERN, $reference->slug)) {
            throw new \InvalidArgumentException(\sprintf('The reference page of "%s" carries the slug "%s", which breaks the slug rule.', $class, $reference->slug));
        }

        if (isset($slugs[$reference->slug])) {
            throw new \InvalidArgumentException(\sprintf('The reference pages of "%s" and "%s" carry the same slug "%s".', $slugs[$reference->slug], $class, $reference->slug));
        }

        if ('' === $reference->title || '' === $reference->summary) {
            throw new \InvalidArgumentException(\sprintf('The reference page "%s" of "%s" needs a title and a summary.', $reference->slug, $class));
        }

        $details = [];
        foreach ($reference->details as $detail) {
            if (!$detail instanceof ReferenceDetail) {
                throw new \InvalidArgumentException(\sprintf('A detail of the reference page "%s" of "%s" is not a %s.', $reference->slug, $class, ReferenceDetail::class));
            }

            if ('' === $detail->label || '' === $detail->text) {
                throw new \InvalidArgumentException(\sprintf('A detail of the reference page "%s" of "%s" has an empty label or text.', $reference->slug, $class));
            }

            $details[] = $detail;
        }

        $related = [];
        foreach ($reference->related as $relatedSlug) {
            if (!\is_string($relatedSlug)) {
                throw new \InvalidArgumentException(\sprintf('A related entry of the reference page "%s" of "%s" is not a string.', $reference->slug, $class));
            }

            $related[] = $relatedSlug;
        }

        return [
            'details' => $details,
            'related' => $related,
        ];
    }

    /**
     * @param list<ReferenceDetail> $details
     *
     * @return list<array{label: string, text: string}>
     */
    private function resolveDetails(array $details, string $slug, ContainerBuilder $container): array
    {
        $resolved = [];
        foreach ($details as $detail) {
            $resolved[] = [
                'label' => $this->resolve($detail->label, $slug, $container),
                'text' => $this->resolve($detail->text, $slug, $container),
            ];
        }

        return $resolved;
    }

    /**
     * Replaces every %parameter% citation with the container value at
     * build time.
     *
     * @throws \InvalidArgumentException when a citation names no defined parameter, or names one whose value is not plain text
     */
    private function resolve(string $text, string $slug, ContainerBuilder $container): string
    {
        return (string) preg_replace_callback(
            '/%([a-zA-Z0-9_.]+)%/',
            static function (array $match) use ($slug, $container): string {
                $name = $match[1];

                if (!$container->hasParameter($name)) {
                    throw new \InvalidArgumentException(\sprintf('The reference page "%s" cites the parameter "%%%s%%", which the container does not define.', $slug, $name));
                }

                $value = $container->getParameter($name);

                if (!\is_scalar($value)) {
                    throw new \InvalidArgumentException(\sprintf('The reference page "%s" cites the parameter "%%%s%%", whose value is not plain text.', $slug, $name));
                }

                $resolved = (string) $value;

                // A parameter whose value still holds a placeholder, such as an
                // env() indirection, would leak it into the page.
                if (str_contains($resolved, '%')) {
                    throw new \InvalidArgumentException(\sprintf('The reference page "%s" cites the parameter "%%%s%%", whose value holds a placeholder. Cite a plain parameter.', $slug, $name));
                }

                return $resolved;
            },
            $text,
        );
    }
}
