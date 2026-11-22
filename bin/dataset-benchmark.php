#!/usr/bin/env php
<?php

// Measures what a dataset load costs at scale, on any environment, and
// leaves no bench row behind. Not part of the test suite: a run takes
// seconds to a minute and writes to the database it points at.
//
//   php bin/dataset-benchmark.php --env=test
//   symfony php bin/dataset-benchmark.php --env=dev
//   symfony php bin/dataset-benchmark.php --env=prod --count=40000
//
// Run dev and prod through the Symfony CLI, so the compose DATABASE_URL
// is injected; plain php sees the .env URL only. The load runs with
// debug off, the state a production import runs in, and through the
// console command the way an operator runs it. The bench corpus is one
// map plus N points, the shape of the dimensioning measurements in
// docs/DECISIONS.md, under slugs no real dataset uses ("bench-map" and
// "bench-pNNNNN").

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

$environment = 'test';
$count = 30000;

$rawArgs = $_SERVER['argv'] ?? [];

if (!\is_array($rawArgs)) {
    $rawArgs = [];
}

$args = array_values(array_filter($rawArgs, 'is_string'));
array_shift($args);

for ($i = 0, $n = \count($args); $i < $n; ++$i) {
    if (str_starts_with($args[$i], '--env=')) {
        $environment = substr($args[$i], 6);
    } elseif (str_starts_with($args[$i], '--count=')) {
        $count = max(1, (int) substr($args[$i], 8));
    } elseif ('--env' === $args[$i] && isset($args[$i + 1])) {
        $environment = $args[++$i];
    } elseif ('--count' === $args[$i] && isset($args[$i + 1])) {
        $count = max(1, (int) $args[++$i]);
    }
}

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ?? $environment;
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '0';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($environment, false);
$kernel->boot();

/** @var EntityManagerInterface $em */
$em = $kernel->getContainer()->get('doctrine')->getManager();

$countRows = static function (string $dql) use ($em): int {
    $count = $em->createQuery($dql)->getSingleScalarResult();

    return \is_int($count) ? $count : 0;
};

$countsBefore = [
    'map' => $countRows('SELECT COUNT(m.id) FROM App\Entity\Map m'),
    'map_point' => $countRows('SELECT COUNT(p.id) FROM App\Entity\MapPoint p'),
];

$benchFile = sys_get_temp_dir().'/dataset-benchmark-'.$count.'.yaml';

$file = fopen($benchFile, 'w');

if (false === $file) {
    fwrite(STDERR, "The bench file cannot be written: $benchFile\n");
    exit(1);
}
fwrite($file, "maps:\n    bench-map:\n        name: Bench map\nmap_points:\n");

for ($i = 1; $i <= $count; ++$i) {
    fwrite($file, sprintf(
        "    bench-p%05d:\n        map: '@maps.bench-map'\n        title: Bench point %d\n        x: %d.5\n        y: %d.0\n",
        $i,
        $i,
        $i % 512,
        ($i * 7) % 512,
    ));
}

fclose($file);

echo "dataset load benchmark\n";
echo sprintf("environment: %s   corpus: 1 map + %d points\n", $environment, $count);
echo sprintf("kernel boot peak: %.1f MB\n", memory_get_peak_usage(true) / 1024 / 1024);

$application = new Application($kernel);
$application->setAutoExit(false);

$command = $application->find('app:dataset:load');
$output = new BufferedOutput();

// The update mode makes the benchmark re-runnable: a second run upserts
// the bench rows instead of failing the insert collision check.
$start = hrtime(true);
$exit = $command->run(new ArrayInput(['paths' => [$benchFile], '--update' => true]), $output);
$seconds = (hrtime(true) - $start) / 1e9;

unlink($benchFile);

if (0 !== $exit) {
    fwrite(STDERR, $output->fetch());
    exit(1);
}

echo $output->fetch();
echo sprintf("load: %.1f s   peak: %.1f MB\n", $seconds, memory_get_peak_usage(true) / 1024 / 1024);

$removedPoints = $em->createQuery('DELETE FROM App\Entity\MapPoint p WHERE p.datasetSlug LIKE :prefix')
    ->setParameter('prefix', 'bench-p%')
    ->execute();
$removedPoints = \is_int($removedPoints) ? $removedPoints : 0;

$removedMaps = $em->createQuery('DELETE FROM App\Entity\Map m WHERE m.datasetSlug = :slug')
    ->setParameter('slug', 'bench-map')
    ->execute();
$removedMaps = \is_int($removedMaps) ? $removedMaps : 0;

$em->clear();

$countsAfter = [
    'map' => $countRows('SELECT COUNT(m.id) FROM App\Entity\Map m'),
    'map_point' => $countRows('SELECT COUNT(p.id) FROM App\Entity\MapPoint p'),
];

echo sprintf("cleanup: %d bench point(s) and %d map(s) removed\n", $removedPoints, $removedMaps);
echo sprintf("counts restored: map %d -> %d, map_point %d -> %d\n", $countsBefore['map'], $countsAfter['map'], $countsBefore['map_point'], $countsAfter['map_point']);

exit($countsBefore === $countsAfter ? 0 : 1);
