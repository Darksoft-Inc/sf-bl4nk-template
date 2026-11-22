<?php

namespace App\Command;

use App\Dataset\DatasetException;
use App\Dataset\DatasetLoader;
use App\Dataset\DatasetMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads the YAML datasets into the database. Without arguments it loads
 * "datasets/shared" plus the directory of the current environment: a
 * first run inserts, later runs need --update to apply edits, and
 * --clear rebuilds from empty tables. Production loads run --update,
 * which never touches a row without a dataset slug.
 */
#[AsCommand(
    name: 'app:dataset:load',
    description: 'Loads YAML datasets into the database, keyed by dataset slug',
)]
final class DatasetLoadCommand extends Command
{
    public function __construct(
        private readonly DatasetLoader $loader,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Dataset files or directories, relative to the project directory; defaults to "datasets/shared" plus the environment directory')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Applies the fields a record names on a known slug and accepts removal entries; the recommended production mode')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Empties every table, then inserts; refused in the prod environment and exclusive with --update')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Runs --clear without the confirmation question')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validates and reports without writing anything')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Records to write between two flushes: a lower value bounds memory, a higher value speeds the load', (string) DatasetLoader::DEFAULT_BATCH_SIZE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $update = true === $input->getOption('update');
        $clear = true === $input->getOption('clear');
        $dryRun = true === $input->getOption('dry-run');
        $batchSize = $input->getOption('batch-size');

        if (!\is_string($batchSize) || 1 !== preg_match('/^[1-9]\d*$/', $batchSize)) {
            $io->error('The --batch-size option must be a positive integer.');

            return Command::FAILURE;
        }

        if ($update && $clear) {
            $io->error('The --update and --clear options exclude each other: --clear starts from empty tables, so there is nothing to update.');

            return Command::FAILURE;
        }

        if ($clear && 'prod' === $this->environment) {
            $io->error('The --clear option is refused in the prod environment. Production loads run --update: a row without a dataset slug is never touched, and a removal entry names the row to delete.');

            return Command::FAILURE;
        }

        if ($clear && true !== $input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('The --clear option needs --force when the command runs non-interactively.');

                return Command::FAILURE;
            }

            if (!$io->confirm('Clear empties every table before loading. Continue?', false)) {
                $io->note('Aborted without changing anything.');

                return Command::SUCCESS;
            }
        }

        $paths = $this->paths($input, $io);

        if (null === $paths) {
            return Command::SUCCESS;
        }

        $mode = $clear ? DatasetMode::Clear : ($update ? DatasetMode::Update : DatasetMode::Insert);

        try {
            $result = $this->loader->load($paths, $mode, $dryRun, (int) $batchSize);
        } catch (DatasetException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (0 === $result->fileCount()) {
            $io->note(\sprintf('No YAML dataset files found in: %s. Nothing was loaded.', implode(', ', $paths)));

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($result->counts() as $type => $counts) {
            $rows[] = [$type, (string) $counts['created'], (string) $counts['updated'], (string) $counts['removed']];
        }

        $io->table(['type', 'created', 'updated', 'removed'], $rows);

        if ($result->skippedRemovals() > 0) {
            $io->note(\sprintf('Skipped %d removal entries: only the update mode removes rows.', $result->skippedRemovals()));
        }

        if ($result->cleared() > 0) {
            $io->note(\sprintf('Cleared %d table(s) before loading.', $result->cleared()));
        }

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');
        }

        return Command::SUCCESS;
    }

    /**
     * The paths to load, or null when the defaults do not exist and the
     * note was printed.
     *
     * @return list<string>|null
     */
    private function paths(InputInterface $input, SymfonyStyle $io): ?array
    {
        $paths = [];

        foreach ((array) $input->getArgument('paths') as $path) {
            if (\is_string($path)) {
                $paths[] = str_starts_with($path, '/') ? $path : $this->projectDir.'/'.$path;
            }
        }

        if ([] !== $paths) {
            return $paths;
        }

        $defaults = [
            $this->projectDir.'/datasets/shared',
            $this->projectDir.'/datasets/'.$this->environment,
        ];

        $existing = array_values(array_filter($defaults, \is_dir(...)));

        if ([] === $existing) {
            $io->note(\sprintf(
                'No default dataset directories exist for the "%s" environment (looked in %s). Nothing was loaded.',
                $this->environment,
                implode(' and ', $defaults),
            ));

            return null;
        }

        return $existing;
    }
}
