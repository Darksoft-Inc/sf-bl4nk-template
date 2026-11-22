<?php

namespace App\Tests\Unit;

use App\Reference\Reference;
use App\Reference\ReferenceCompilerPass;
use App\Reference\ReferenceDetail;
use App\Reference\ReferenceRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ReferenceCompilerPassTest extends TestCase
{
    public function testCollectsPagesAndResolvesEveryCitation(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.retention_days', 90);
        $container->register(RetentionService::class, RetentionService::class);
        $container->register(UploadService::class, UploadService::class);

        (new ReferenceCompilerPass())->process($container);

        /**
         * The exact array shape the pass hands to the registry.
         *
         * @var list<array{slug: string, title: string, summary: string, group: string, details: list<array{label: string, text: string}>, related: list<string>}> $pages
         */
        $pages = $container->getDefinition(ReferenceRegistry::class)->getArgument(0);
        self::assertCount(2, $pages);

        $bySlug = [];
        foreach ($pages as $page) {
            $bySlug[$page['slug']] = $page;
        }

        // A dotted slug is a valid page name, the naming convention the
        // slug rule allows.
        self::assertArrayHasKey('account.retention', $bySlug);
        self::assertArrayHasKey('files.upload', $bySlug);

        $retention = $bySlug['account.retention'];
        self::assertSame('Data', $retention['group']);
        self::assertSame(['files.upload'], $retention['related']);
        self::assertSame('Rows older than 90 days are removed.', $retention['details'][0]['text']);

        $upload = $bySlug['files.upload'];
        self::assertSame('At most 2M per file.', $upload['details'][0]['text']);
    }

    public function testRejectsADuplicateSlug(): void
    {
        $container = new ContainerBuilder();
        $container->register(FirstDuplicatedService::class, FirstDuplicatedService::class);
        $container->register(SecondDuplicatedService::class, SecondDuplicatedService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('carry the same slug "twice"');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsAnInvalidSlug(): void
    {
        $container = new ContainerBuilder();
        $container->register(BadSlugService::class, BadSlugService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('carries the slug "Bad Slug", which breaks the slug rule');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsAnUnknownParameter(): void
    {
        $container = new ContainerBuilder();
        $container->register(UnknownParameterService::class, UnknownParameterService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cites the parameter "%app.missing%", which the container does not define');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsAParameterHoldingAPlaceholder(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.indirect', '%env(SOME_VAR)%');
        $container->register(PlaceholderParameterService::class, PlaceholderParameterService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('whose value holds a placeholder');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsARelatedSlugNoPageCarries(): void
    {
        $container = new ContainerBuilder();
        $container->register(LonelyService::class, LonelyService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is related to "nowhere", which no page carries');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsASecondAttributeOnOneClass(): void
    {
        $container = new ContainerBuilder();
        $container->register(TwiceDocumentedService::class, TwiceDocumentedService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('declares more than one Reference attribute');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsADetailThatIsNotAReferenceDetail(): void
    {
        $container = new ContainerBuilder();
        $container->register(PlainDetailService::class, PlainDetailService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a App\Reference\ReferenceDetail');

        (new ReferenceCompilerPass())->process($container);
    }

    public function testRejectsAnEmptyTitle(): void
    {
        $container = new ContainerBuilder();
        $container->register(TitlelessService::class, TitlelessService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a title and a summary');

        (new ReferenceCompilerPass())->process($container);
    }
}

#[Reference(
    slug: 'account.retention',
    title: 'Retention',
    summary: 'How long the records stay.',
    group: 'Data',
    details: [
        new ReferenceDetail('Window', 'Rows older than %app.retention_days% days are removed.'),
    ],
    related: ['files.upload'],
)]
final class RetentionService
{
}

#[Reference(
    slug: 'files.upload',
    title: 'Upload',
    summary: 'The upload rules.',
    group: 'Files',
    details: [
        new ReferenceDetail('Size limit', 'At most '.UploadService::MAX_SIZE.' per file.'),
    ],
)]
final class UploadService
{
    public const MAX_SIZE = '2M';
}

#[Reference(slug: 'twice', title: 'First', summary: 'One.')]
final class FirstDuplicatedService
{
}

#[Reference(slug: 'twice', title: 'Second', summary: 'Two.')]
final class SecondDuplicatedService
{
}

#[Reference(slug: 'Bad Slug', title: 'Bad', summary: 'A slug outside the rule.')]
final class BadSlugService
{
}

#[Reference(slug: 'unknown-parameter', title: 'Missing', summary: 'Cites a parameter that does not exist.', details: [
    new ReferenceDetail('Window', 'Rows older than %app.missing% days are removed.'),
])]
final class UnknownParameterService
{
}

#[Reference(slug: 'placeholder-parameter', title: 'Indirect', summary: 'Cites a parameter holding a placeholder.', details: [
    new ReferenceDetail('Window', 'Kept %app.indirect%.'),
])]
final class PlaceholderParameterService
{
}

#[Reference(slug: 'lonely', title: 'Lonely', summary: 'Points at a page that does not exist.', related: ['nowhere'])]
final class LonelyService
{
}

#[Reference(slug: 'first', title: 'First', summary: 'One.')]
#[Reference(slug: 'second', title: 'Second', summary: 'Two.')]
final class TwiceDocumentedService
{
}

#[Reference(slug: 'plain-detail', title: 'Plain', summary: 'A detail that is not a ReferenceDetail.', details: ['a plain string'])]
final class PlainDetailService
{
}

#[Reference(slug: 'titleless', title: '', summary: 'The title is empty.')]
final class TitlelessService
{
}
