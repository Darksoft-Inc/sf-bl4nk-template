<?php

namespace App\Tests\Integration;

use App\Command\DatasetLoadCommand;
use App\Dataset\DatasetLoader;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs the load command the way an operator would, through the console
 * entry point. The loader service test covers the record rules; this
 * class covers the command surface: paths, purge, and dry run.
 */
final class DatasetLoadCommandTest extends KernelTestCase
{
    use ResetDatabase;

    private const FIXTURE = 'tests/fixtures/datasets/content.yaml';

    private EntityManagerInterface $em;
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->resetDatabase($this->em);

        $kernel = self::$kernel;
        \assert(null !== $kernel);

        $command = (new Application($kernel))->find('app:dataset:load');
        $this->tester = new CommandTester($command);
    }

    public function testLoadsAnExplicitFile(): void
    {
        $this->tester->execute(['paths' => [self::FIXTURE]], ['interactive' => false]);

        $this->tester->assertCommandIsSuccessful();

        $display = $this->tester->getDisplay();
        self::assertStringContainsString('users', $display);
        self::assertStringContainsString('1', $display);

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll());
    }

    public function testDefaultPathsReportNothingForTheTestEnvironment(): void
    {
        $this->tester->execute([], ['interactive' => false]);

        $this->tester->assertCommandIsSuccessful();

        $display = $this->tester->getDisplay();
        self::assertStringContainsString('No YAML dataset files found', $display);
        self::assertStringContainsString('datasets/shared', $display);
    }

    public function testPurgeNeedsForceWhenNonInteractive(): void
    {
        $this->tester->execute(['paths' => [self::FIXTURE], '--purge' => true], ['interactive' => false]);

        $this->tester->assertCommandFailed();
        self::assertStringContainsString('--purge option needs --force', $this->tester->getDisplay());
    }

    public function testPurgeRunsAfterConfirmation(): void
    {
        $this->tester->execute(['paths' => [self::FIXTURE]], ['interactive' => false]);

        $this->tester->setInputs(['y']);
        $this->tester->execute(['paths' => [self::FIXTURE], '--purge' => true], ['interactive' => true]);

        $this->tester->assertCommandIsSuccessful();
        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The purge confirmed by the operator runs.');

        $this->tester->setInputs(['n']);
        $this->tester->execute(['paths' => [self::FIXTURE], '--purge' => true], ['interactive' => true]);

        $this->tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Aborted without changing anything', $this->tester->getDisplay());
        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The purge declined by the operator changes nothing.');
    }

    public function testPurgeIsRefusedInTheProdEnvironment(): void
    {
        /** @var DatasetLoader $loader */
        $loader = static::getContainer()->get(DatasetLoader::class);

        $kernel = self::$kernel;
        \assert(null !== $kernel);

        $command = new DatasetLoadCommand($loader, 'prod', $kernel->getProjectDir());
        $tester = new CommandTester($command);

        $tester->execute(['paths' => [self::FIXTURE], '--purge' => true, '--force' => true], ['interactive' => false]);

        $tester->assertCommandFailed();
        self::assertStringContainsString('--purge option is refused in the prod environment', $tester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $this->tester->execute(['paths' => [self::FIXTURE], '--dry-run' => true], ['interactive' => false]);

        $this->tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Dry run: nothing was written', $this->tester->getDisplay());

        $this->em->clear();

        self::assertCount(0, $this->em->getRepository(User::class)->findAll());
    }

    public function testBatchSizeOptionIsAccepted(): void
    {
        $this->tester->execute(['paths' => [self::FIXTURE], '--batch-size' => '1'], ['interactive' => false]);

        $this->tester->assertCommandIsSuccessful();

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll());
    }

    public function testBatchSizeOptionRejectsNonPositiveValues(): void
    {
        $this->tester->execute(['paths' => [self::FIXTURE], '--batch-size' => '0'], ['interactive' => false]);

        $this->tester->assertCommandFailed();
        self::assertStringContainsString('--batch-size option must be a positive integer', $this->tester->getDisplay());
    }
}
