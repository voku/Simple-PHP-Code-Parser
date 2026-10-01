<?php

declare(strict_types=1);

use voku\SimplePhpParser\Model\BasePHPElement;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

require __DIR__ . '/../vendor/autoload.php';

const MAX_WORKERS = 8;
const ROUNDS = 3;

/**
 * @return list<string>
 */
function phpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $path = $file->getRealPath();
        if (!is_string($path) || !str_ends_with($path, '.php')) {
            continue;
        }

        $files[] = $path;
    }

    sort($files, SORT_STRING);

    return $files;
}

/**
 * @param list<string> $files
 *
 * @return list<list<string>>
 */
function partitions(array $files, int $workers): array
{
    if ($files === []) {
        return [];
    }

    $workers = max(1, min($workers, count($files)));
    $baseSize = intdiv(count($files), $workers);
    $remainder = count($files) % $workers;
    $result = [];
    $offset = 0;

    for ($i = 0; $i < $workers; ++$i) {
        $size = $baseSize + ($i < $remainder ? 1 : 0);
        if ($size > 0) {
            $result[] = array_slice($files, $offset, $size);
        }
        $offset += $size;
    }

    return $result;
}

/**
 * @param mixed $value
 */
function rebindParserContainer(&$value, ParserContainer $target, SplObjectStorage $seen): void
{
    if (is_array($value)) {
        foreach ($value as &$item) {
            rebindParserContainer($item, $target, $seen);
        }
        unset($item);

        return;
    }

    if (!$value instanceof BasePHPElement) {
        return;
    }

    if ($seen->contains($value)) {
        return;
    }
    $seen->attach($value);

    $value->parserContainer = $target;

    foreach (get_object_vars($value) as $property => $_) {
        if ($property === 'parserContainer') {
            continue;
        }

        rebindParserContainer($value->{$property}, $target, $seen);
    }
}

/**
 * @param list<ParserContainer> $containers
 *
 * @return array{container: ParserContainer, errors: list<string>}
 */
function mergeContainers(array $containers, ParserOptions $options): array
{
    $target = new ParserContainer($options);
    $errors = [];

    foreach ($containers as $source) {
        $target->setTraits($source->getTraits());
        $target->setClasses($source->getClasses());
        $target->setInterfaces($source->getInterfaces());
        $target->setEnums($source->getEnums());
        $target->setConstants($source->getConstants());
        $target->setFunctions($source->getFunctions());

        foreach ($source->getParseErrors() as $error) {
            $errors[] = $error;
        }
    }

    $seen = new SplObjectStorage();
    foreach ([
        $target->getTraits(),
        $target->getClasses(),
        $target->getInterfaces(),
        $target->getEnums(),
        $target->getConstants(),
        $target->getFunctions(),
    ] as $models) {
        rebindParserContainer($models, $target, $seen);
    }

    return ['container' => $target, 'errors' => $errors];
}

/**
 * @return array{container: ParserContainer, errors: list<string>, workers: int}
 */
function parseParallel(array $files, ParserOptions $options): array
{
    if (
        !function_exists('pcntl_fork')
        || !function_exists('pcntl_waitpid')
    ) {
        throw new RuntimeException('pcntl is unavailable');
    }

    $workers = max(1, min(MAX_WORKERS, Utils::getCpuCores(), count($files)));
    $partitions = partitions($files, $workers);
    $tmpRoot = sys_get_temp_dir() . '/simple-php-parser-parallel-' . getmypid() . '-' . bin2hex(random_bytes(4));

    if (!mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        throw new RuntimeException('Could not create temporary experiment directory: ' . $tmpRoot);
    }

    /** @var list<array{pid: int, file: string}> $children */
    $children = [];

    try {
        foreach ($partitions as $index => $partition) {
            $resultFile = $tmpRoot . '/worker-' . $index . '.ser';
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('pcntl_fork() failed for worker ' . $index);
            }

            if ($pid === 0) {
                try {
                    $containers = [];
                    foreach ($partition as $file) {
                        $containers[] = PhpCodeParser::getPhpFiles(
                            $file,
                            options: $options
                        );
                    }

                    $bytes = file_put_contents($resultFile, serialize($containers), LOCK_EX);
                    exit($bytes === false ? 12 : 0);
                } catch (Throwable $throwable) {
                    file_put_contents(
                        $resultFile . '.error',
                        $throwable::class . ': ' . $throwable->getMessage(),
                        LOCK_EX
                    );
                    exit(11);
                }
            }

            $children[] = ['pid' => $pid, 'file' => $resultFile];
        }

        $containers = [];
        foreach ($children as $child) {
            $status = 0;
            pcntl_waitpid($child['pid'], $status);

            if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                $errorFile = $child['file'] . '.error';
                $detail = is_file($errorFile) ? (string) file_get_contents($errorFile) : 'no child error detail';
                throw new RuntimeException(
                    'Parallel worker failed: pid=' . $child['pid']
                    . ', status=' . $status
                    . ', detail=' . $detail
                );
            }

            $payload = file_get_contents($child['file']);
            if (!is_string($payload) || $payload === '') {
                throw new RuntimeException('Parallel worker returned no payload: ' . $child['pid']);
            }

            $decoded = unserialize($payload, ['allowed_classes' => true]);
            if (!is_array($decoded)) {
                throw new RuntimeException('Parallel worker payload was not an array: ' . $child['pid']);
            }

            foreach ($decoded as $container) {
                if (!$container instanceof ParserContainer) {
                    throw new RuntimeException('Parallel worker payload contained an unexpected value');
                }
                $containers[] = $container;
            }
        }

        $merged = mergeContainers($containers, $options);

        return [
            'container' => $merged['container'],
            'errors' => $merged['errors'],
            'workers' => $workers,
        ];
    } finally {
        foreach (glob($tmpRoot . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tmpRoot);
    }
}

/**
 * @return mixed
 */
function normalizeValue($value, SplObjectStorage $seen)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = normalizeValue($item, $seen);
        }

        return $result;
    }

    if (!is_object($value)) {
        return $value;
    }

    if ($value instanceof ParserContainer) {
        return '__parser_container__';
    }

    if ($seen->contains($value)) {
        return '__cycle__';
    }
    $seen->attach($value);

    $result = ['__class' => $value::class];
    foreach (get_object_vars($value) as $property => $propertyValue) {
        if ($property === 'parserContainer') {
            continue;
        }

        $result[$property] = normalizeValue($propertyValue, $seen);
    }

    return $result;
}

/**
 * @param list<string>|null $errors
 *
 * @return array<string, mixed>
 */
function normalizeContainer(ParserContainer $container, ?array $errors = null): array
{
    $seen = new SplObjectStorage();

    return [
        'classes' => normalizeValue($container->getClasses(), $seen),
        'interfaces' => normalizeValue($container->getInterfaces(), $seen),
        'traits' => normalizeValue($container->getTraits(), $seen),
        'enums' => normalizeValue($container->getEnums(), $seen),
        'constants' => normalizeValue($container->getConstants(), $seen),
        'functions' => normalizeValue($container->getFunctions(), $seen),
        'parse_errors' => $errors ?? $container->getParseErrors(),
    ];
}

/**
 * @return array{path: string, left: mixed, right: mixed}|null
 */
function firstDiff($left, $right, string $path = '$'): ?array
{
    if (gettype($left) !== gettype($right)) {
        return ['path' => $path, 'left' => $left, 'right' => $right];
    }

    if (!is_array($left)) {
        return $left === $right ? null : ['path' => $path, 'left' => $left, 'right' => $right];
    }

    if (array_keys($left) !== array_keys($right)) {
        return [
            'path' => $path . '.__keys',
            'left' => array_keys($left),
            'right' => array_keys($right),
        ];
    }

    foreach ($left as $key => $leftValue) {
        $diff = firstDiff($leftValue, $right[$key], $path . '[' . var_export($key, true) . ']');
        if ($diff !== null) {
            return $diff;
        }
    }

    return null;
}

/**
 * @return array{value: mixed, ms: float}
 */
function timed(callable $callback): array
{
    $start = hrtime(true);
    $value = $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;

    return ['value' => $value, 'ms' => $elapsed];
}

/**
 * @param list<float> $values
 */
function median(array $values): float
{
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);

    if ($count % 2 === 1) {
        return $values[$middle];
    }

    return ($values[$middle - 1] + $values[$middle]) / 2;
}

function createCrossFileFixture(): string
{
    $root = sys_get_temp_dir() . '/simple-php-parser-cross-file-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Could not create cross-file fixture directory');
    }

    file_put_contents(
        $root . '/01-ParentThing.php',
        <<<'PHP'
<?php

namespace ParallelProof;

class ParentThing
{
    /**
     * Parent summary.
     *
     * @return string
     */
    public function value(): string
    {
        return 'parent';
    }
}
PHP
    );

    file_put_contents(
        $root . '/02-ChildThing.php',
        <<<'PHP'
<?php

namespace ParallelProof;

class ChildThing extends ParentThing
{
    /** {@inheritdoc} */
    public function value(): string
    {
        return parent::value();
    }
}
PHP
    );

    return $root;
}

function removeTree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    foreach (glob($root . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($root);
}

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Experiment must run in CLI');
}

if (!function_exists('pcntl_fork')) {
    throw new RuntimeException('pcntl_fork() is required for the experiment');
}

$options = ParserOptions::astOnly();
$sourceRoot = realpath(__DIR__ . '/../src/voku/SimplePhpParser');
if (!is_string($sourceRoot)) {
    throw new RuntimeException('Could not resolve parser source root');
}

$sourceFiles = phpFiles($sourceRoot);
$fixtureRoot = createCrossFileFixture();

try {
    $cases = [
        'repository-source' => [$sourceRoot, $sourceFiles],
        'cross-file-inheritance' => [$fixtureRoot, phpFiles($fixtureRoot)],
    ];

    $report = [
        'php' => PHP_VERSION,
        'cpu_cores' => Utils::getCpuCores(),
        'max_workers' => MAX_WORKERS,
        'rounds' => ROUNDS,
        'cases' => [],
    ];

    foreach ($cases as $name => [$root, $files]) {
        $sequential = timed(
            static fn (): ParserContainer => PhpCodeParser::getPhpFiles($root, options: $options)
        );
        $parallel = timed(
            static fn (): array => parseParallel($files, $options)
        );

        /** @var ParserContainer $sequentialContainer */
        $sequentialContainer = $sequential['value'];
        /** @var array{container: ParserContainer, errors: list<string>, workers: int} $parallelValue */
        $parallelValue = $parallel['value'];

        $left = normalizeContainer($sequentialContainer);
        $right = normalizeContainer($parallelValue['container'], $parallelValue['errors']);
        $diff = firstDiff($left, $right);

        $sequentialTimes = [$sequential['ms']];
        $parallelTimes = [$parallel['ms']];

        for ($round = 1; $round < ROUNDS; ++$round) {
            $sequentialRound = timed(
                static fn (): ParserContainer => PhpCodeParser::getPhpFiles($root, options: $options)
            );
            $parallelRound = timed(
                static fn (): array => parseParallel($files, $options)
            );

            $sequentialTimes[] = $sequentialRound['ms'];
            $parallelTimes[] = $parallelRound['ms'];
        }

        $sequentialMedian = median($sequentialTimes);
        $parallelMedian = median($parallelTimes);

        $report['cases'][$name] = [
            'files' => count($files),
            'workers' => $parallelValue['workers'],
            'equal' => $diff === null,
            'first_diff' => $diff,
            'sequential_ms' => array_map(static fn (float $value): float => round($value, 3), $sequentialTimes),
            'parallel_ms' => array_map(static fn (float $value): float => round($value, 3), $parallelTimes),
            'sequential_median_ms' => round($sequentialMedian, 3),
            'parallel_median_ms' => round($parallelMedian, 3),
            'speedup' => $parallelMedian > 0.0 ? round($sequentialMedian / $parallelMedian, 3) : null,
        ];
    }

    $report['all_equal'] = !in_array(
        false,
        array_map(static fn (array $case): bool => $case['equal'], $report['cases']),
        true
    );

    $outputDir = __DIR__ . '/../build';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Could not create build directory');
    }

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($outputDir . '/parallel-parser-experiment.json', $json . PHP_EOL);

    echo $json . PHP_EOL;
} finally {
    removeTree($fixtureRoot);
}
