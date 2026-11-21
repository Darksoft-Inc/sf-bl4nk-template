<?php

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SetupSmokeTest extends KernelTestCase
{
    public function testSassOutputExists(): void
    {
        $generatedStyles = glob(\dirname(__DIR__).'/var/sass/*.output.css');

        self::assertIsArray($generatedStyles);
        self::assertNotEmpty(
            $generatedStyles,
            'No generated CSS found in var/sass/. Run `symfony console sass:build` once before the test suite. See docs/SETUP.md.',
        );
    }

    public function testDatabaseSchemaExists(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $tableNames = $connection->createSchemaManager()->listTableNames();

        self::assertContains(
            'user',
            $tableNames,
            'The table "user" is missing from the test database. Run `php bin/console doctrine:schema:create --env=test` once before the test suite. See docs/SETUP.md.',
        );
        self::assertContains(
            'reset_password_request',
            $tableNames,
            'The table "reset_password_request" is missing from the test database. Run `php bin/console doctrine:schema:create --env=test` once before the test suite. See docs/SETUP.md.',
        );
        self::assertContains(
            'announcement',
            $tableNames,
            'The table "announcement" is missing from the test database. Run `php bin/console doctrine:schema:create --env=test` once before the test suite. See docs/SETUP.md.',
        );
    }
}
