<?php

declare(strict_types=1);

use voku\SimplePhpParser\Model\BasePHPElement;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\PhpCodeParser;
use voku\SimplePhpParser\Parsers\Visitors\ASTVisitor;

require __DIR__ . '/../vendor/autoload.php';

const STAGE3_MAX_WORKERS = 8;
const STAGE3_ROUNDS = 5;

/**
 * Match PhpCodeParser::getCode() directory iteration order.
 *
 * @return list<string>
 */
function stage3PhpFiles(string $root): array
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

    return $files;
}

/**
 * @param list<string> $files
 *
 * @return list<list<string>>
 */
function stage3Partitions(array $files, int $workers): array
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
 * Parse, resolve names and extract file-local models into one worker-local container.
 *
 * Cross-file finalisation is intentionally omitted here.
 *
 * @param list<string> $files
 */
function stage3ExtractPartition(array $files, ParserOptions $options): ParserContainer
{
    $container = new ParserContainer($options);
    $visitor = new ASTVisitor($container);

    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new RuntimeException('Could not read file: ' . $file);
        }

        $result = PhpCodeParser::process(
            $content,
            $file,
            $container,
            $visitor
        );

        if ($result instanceof ParserErrorHandler) {
            $container->setParseError($result);
        }
    }

    return $container;
}

/**
 * @param mixed $value
 */
function stage3RebindParserContainer(&$value, ParserContainer $target, SplObjectStorage $seen): void
{
    if (is_array($value)) {
        foreach ($value as &$item) {
            stage3RebindParserContainer($item, $target, $seen);
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

        stage3RebindParserContainer($value->{$property}, $target, $seen);
    }
}

/**
 * @param list<ParserContainer> $containers
 */
function stage3MergeWorkerContainers(array $containers, ParserOptions $options): ParserContainer
{
    $target = new ParserContainer($options);

    foreach ($containers as $source) {
        $target->setTraits($source->getTraits());
        $target->setClasses($source->getClasses());
        $target->setInterfaces($source->getInterfaces());
        $target->setEnums($source->getEnums());
        $target->setConstants($source->getConstants());
        $target->setFunctions($source->getFunctions());

        foreach ($source->getParseErrors() as $error) {
            $target->addException(new RuntimeException($error));
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
        stage3RebindParserContainer($models, $target, $seen);
    }

    return $target;
}

/**
 * Run the same cross-file finalisation that getPhpFiles() performs after every
 * file has contributed to one shared ParserContainer.
 */
function stage3Finalize(ParserContainer $container): void
{
    $visitor = new ASTVisitor($container);

    $interfaces = $container->getInterfaces();
    foreach ($interfaces as &$interface) {
        $interface->parentInterfaces = $visitor->combineParentInterfaces($interface);
    }
    unset($interface);

    $classes = &$container->getClassesByReference();
    $mergeInheritdocData = new ReflectionMethod(PhpCodeParser::class, 'mergeInheritdocData');
    $mergeInheritdocData->setAccessible(true);

    foreach ($classes as &$class) {
        $class->interfaces = Utils::flattenArray(
            $visitor->combineImplementedInterfaces($class),
            false
        );

        $arguments = [$class, $classes, $interfaces, $container];
        $mergeInheritdocData->invokeArgs(null, $arguments);
    }
    unset($class);
}

/**
 * @param list<string> $files
 *
 * @return array{container: ParserContainer, workers: int}
 */
function stage3ParallelExtract(array $files, ParserOptions $options): array
{
    if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
        throw new RuntimeException('pcntl is unavailable');
    }

    $workers = max(1, min(STAGE3_MAX_WORKERS, Utils::getCpuCores(), count($files)));
    $partitions = stage3Partitions($files, $workers);
    $tmpRoot = sys_get_temp_dir() . '/simple-php-parser-stage3-' . getmypid() . '-' . bin2hex(random_bytes(4));

    if (!mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        throw new RuntimeException('Could not create temporary stage3 directory: ' . $tmpRoot);
    }

    /** @var list<array{pid: int, file: string}> $children */
    $children = [];

    try {
        foreach ($partitions as $index => $partition) {
            $resultFile = $tmpRoot . '/worker-' . $index . '.ser';
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('pcntl_fork() failed for stage3 worker ' . $index);
            }

            if ($pid === 0) {
                try {
                    $container = stage3ExtractPartition($partition, $options);
                    $bytes = file_put_contents($resultFile, serialize($container), LOCK_EX);
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
                    'Stage3 worker failed: pid=' . $child['pid']
                    . ', status=' . $status
                    . ', detail=' . $detail
                );
            }

            $payload = file_get_contents($child['file']);
            if (!is_string($payload) || $payload === '') {
                throw new RuntimeException('Stage3 worker returned no payload: ' . $child['pid']);
            }

            $decoded = unserialize($payload, ['allowed_classes' => true]);
            if (!$decoded instanceof ParserContainer) {
                throw new RuntimeException('Stage3 worker payload was not a ParserContainer');
            }

            $containers[] = $decoded;
        }

        $container = stage3MergeWorkerContainers($containers, $options);
        stage3Finalize($container);

        return [
            'container' => $container,
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
function stage3NormalizeValue($value, SplObjectStorage $seen)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = stage3NormalizeValue($item, $seen);
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

        $result[$property] = stage3NormalizeValue($propertyValue, $seen);
    }

    return $result;
}

/**
 * @return array<string, mixed>
 */
function stage3NormalizeContainer(ParserContainer $container): array
{
    $seen = new SplObjectStorage();

    return [
        'classes' => stage3NormalizeValue($container->getClasses(), $seen),
        'interfaces' => stage3NormalizeValue($container->getInterfaces(), $seen),
        'traits' => stage3NormalizeValue($container->getTraits(), $seen),
        'enums' => stage3NormalizeValue($container->getEnums(), $seen),
        'constants' => stage3NormalizeValue($container->getConstants(), $seen),
        'functions' => stage3NormalizeValue($container->getFunctions(), $seen),
        'parse_errors' => $container->getParseErrors(),
    ];
}

/**
 * @return array{path: string, left: mixed, right: mixed}|null
 */
function stage3FirstDiff($left, $right, string $path = '$'): ?array
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
        $diff = stage3FirstDiff($leftValue, $right[$key], $path . '[' . var_export($key, true) . ']');
        if ($diff !== null) {
            return $diff;
        }
    }

    return null;
}

/**
 * @return array{value: mixed, ms: float}
 */
function stage3Timed(callable $callback): array
{
    $start = hrtime(true);
    $value = $callback();

    return [
        'value' => $value,
        'ms' => (hrtime(true) - $start) / 1_000_000,
    ];
}

/**
 * @param list<float> $values
 */
function stage3Median(array $values): float
{
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);

    if ($count % 2 === 1) {
        return $values[$middle];
    }

    return ($values[$middle - 1] + $values[$middle]) / 2;
}

function stage3CreateFixture(): string
{
    $root = sys_get_temp_dir() . '/simple-php-parser-stage3-fixture-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Could not create stage3 fixture directory');
    }

    file_put_contents(
        $root . '/01-Contract.php',
        <<<'PHP'
<?php

namespace ParallelProof;

interface Contract
{
    /**
     * Contract summary.
     *
     * @return string
     */
    public function value(): string;
}
PHP
    );

    file_put_contents(
        $root . '/02-ParentThing.php',
        <<<'PHP'
<?php

namespace ParallelProof;

class ParentThing implements Contract
{
    public const LABEL = 'parent';

    /**
     * Parent summary.
     *
     * @return string
     */
    public function value(): string
    {
        return self::LABEL;
    }
}
PHP
    );

    file_put_contents(
        $root . '/03-ChildThing.php',
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

function stage3RemoveTree(string $root): void
{
    foreach (glob($root . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($root);
}

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Stage3 experiment must run in CLI');
}

if (!function_exists('pcntl_fork')) {
    throw new RuntimeException('pcntl_fork() is required for stage3 experiment');
}

$options = ParserOptions::astOnly();
$sourceRoot = realpath(__DIR__ . '/../src/voku/SimplePhpParser');
if (!is_string($sourceRoot)) {
    throw new RuntimeException('Could not resolve parser source root');
}

$fixtureRoot = stage3CreateFixture();

try {
    $cases = [
        'repository-source' => $sourceRoot,
        'cross-file-finalisation' => $fixtureRoot,
    ];

    $report = [
        'php' => PHP_VERSION,
        'cpu_cores' => Utils::getCpuCores(),
        'max_workers' => STAGE3_MAX_WORKERS,
        'rounds' => STAGE3_ROUNDS,
        'boundary' => 'parallel parse+resolve+file-local model extraction; sequential merged cross-file finalisation',
        'cases' => [],
    ];

    foreach ($cases as $name => $root) {
        $files = stage3PhpFiles($root);

        $sequential = stage3Timed(
            static fn (): ParserContainer => PhpCodeParser::getPhpFiles($root, options: $options)
        );

        $parallel = stage3Timed(
            static fn (): array => stage3ParallelExtract($files, $options)
        );

        /** @var ParserContainer $sequentialContainer */
        $sequentialContainer = $sequential['value'];
        /** @var array{container: ParserContainer, workers: int} $parallelValue */
        $parallelValue = $parallel['value'];

        $left = stage3NormalizeContainer($sequentialContainer);
        $right = stage3NormalizeContainer($parallelValue['container']);
        $diff = stage3FirstDiff($left, $right);

        $sequentialTimes = [$sequential['ms']];
        $parallelTimes = [$parallel['ms']];

        for ($round = 1; $round < STAGE3_ROUNDS; ++$round) {
            $sequentialRound = stage3Timed(
                static fn (): ParserContainer => PhpCodeParser::getPhpFiles($root, options: $options)
            );
            $parallelRound = stage3Timed(
                static fn (): array => stage3ParallelExtract($files, $options)
            );

            $sequentialTimes[] = $sequentialRound['ms'];
            $parallelTimes[] = $parallelRound['ms'];
        }

        $sequentialMedian = stage3Median($sequentialTimes);
        $parallelMedian = stage3Median($parallelTimes);

        $report['cases'][$name] = [
            'files' => count($files),
            'workers' => $parallelValue['workers'],
            'equal' => $diff === null,
            'first_diff' => $diff,
            'sequential_ms' => array_map(static fn (float $v): float => round($v, 3), $sequentialTimes),
            'parallel_ms' => array_map(static fn (float $v): float => round($v, 3), $parallelTimes),
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

    $report['threshold_sweep'] = [];
    $sourceFiles = stage3PhpFiles($sourceRoot);
    foreach ([4, 8, 12, 16, 20, 24, count($sourceFiles)] as $requestedSize) {
        $size = min($requestedSize, count($sourceFiles));
        if ($size < 2 || isset($report['threshold_sweep'][(string) $size])) {
            continue;
        }

        $files = array_slice($sourceFiles, 0, $size);
        $sequentialTimes = [];
        $parallelTimes = [];
        $sweepEqual = true;
        $sweepDiff = null;
        $workers = 1;

        for ($round = 0; $round < STAGE3_ROUNDS; ++$round) {
            $sequentialRound = stage3Timed(
                static function () use ($files, $options): ParserContainer {
                    $container = stage3ExtractPartition($files, $options);
                    stage3Finalize($container);

                    return $container;
                }
            );
            $parallelRound = stage3Timed(
                static fn (): array => stage3ParallelExtract($files, $options)
            );

            /** @var ParserContainer $sequentialContainer */
            $sequentialContainer = $sequentialRound['value'];
            /** @var array{container: ParserContainer, workers: int} $parallelValue */
            $parallelValue = $parallelRound['value'];
            $workers = $parallelValue['workers'];

            $diff = stage3FirstDiff(
                stage3NormalizeContainer($sequentialContainer),
                stage3NormalizeContainer($parallelValue['container'])
            );
            if ($diff !== null) {
                $sweepEqual = false;
                $sweepDiff ??= $diff;
            }

            $sequentialTimes[] = $sequentialRound['ms'];
            $parallelTimes[] = $parallelRound['ms'];
        }

        $sequentialMedian = stage3Median($sequentialTimes);
        $parallelMedian = stage3Median($parallelTimes);

        $report['threshold_sweep'][(string) $size] = [
            'files' => $size,
            'workers' => $workers,
            'equal' => $sweepEqual,
            'first_diff' => $sweepDiff,
            'sequential_median_ms' => round($sequentialMedian, 3),
            'parallel_median_ms' => round($parallelMedian, 3),
            'speedup' => $parallelMedian > 0.0 ? round($sequentialMedian / $parallelMedian, 3) : null,
        ];

        if (!$sweepEqual) {
            $report['all_equal'] = false;
        }
    }

    $outputDir = __DIR__ . '/../build';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Could not create build directory');
    }

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($outputDir . '/parallel-parser-stage3-experiment.json', $json . PHP_EOL);
    echo $json . PHP_EOL;

    if ($report['all_equal'] !== true) {
        exit(2);
    }
} finally {
    stage3RemoveTree($fixtureRoot);
}
