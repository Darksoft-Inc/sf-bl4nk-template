<?php

namespace App\Dataset\Type;

use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\Announcement;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The "announcements" section. The author is a "users" reference.
 */
#[AutoconfigureTag('app.dataset_type')]
final class AnnouncementDatasetType implements DatasetTypeInterface
{
    private const FIELDS = ['title', 'content', 'publishedAt', 'author'];

    public function getName(): string
    {
        return 'announcements';
    }

    public function getEntityClass(): string
    {
        return Announcement::class;
    }

    public function getFieldNames(): array
    {
        return self::FIELDS;
    }

    public function getRequiredFieldNames(): array
    {
        return self::FIELDS;
    }

    public function create(DatasetRecord $record, DatasetResolver $resolver): object
    {
        $author = $this->author($record, $resolver);

        $announcement = new Announcement(
            $record->string('title'),
            $record->string('content'),
            $record->dateTime('publishedAt'),
            $author,
        );
        $announcement->setDatasetSlug($record->slug);

        $this->apply($announcement, $record, $resolver);

        return $announcement;
    }

    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
    {
        \assert($entity instanceof Announcement);

        $this->apply($entity, $record, $resolver);
    }

    private function apply(Announcement $announcement, DatasetRecord $record, DatasetResolver $resolver): void
    {
        if ($record->has('title')) {
            $announcement->setTitle($record->string('title'));
        }

        if ($record->has('content')) {
            $announcement->setContent($record->string('content'));
        }

        if ($record->has('publishedAt')) {
            $announcement->setPublishedAt($record->dateTime('publishedAt'));
        }

        if ($record->has('author')) {
            $announcement->setAuthor($this->author($record, $resolver));
        }
    }

    private function author(DatasetRecord $record, DatasetResolver $resolver): User
    {
        $author = $record->entity('author', 'users', $resolver);
        \assert($author instanceof User);

        return $author;
    }
}
