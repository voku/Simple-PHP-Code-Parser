<?php

declare(strict_types=1);

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\PhpCodeParser;
use voku\SimplePhpParser\Parsers\Visitors\ASTVisitor;

require __DIR__ . '/../vendor/autoload.php';

const STAGE2_MAX_WORKERS = 8;
const STAGE2_ROUNDS = 5;

/**
 * Match PhpCodeParser::getCode() directory iteration order.
 *
 * @return list<string>
 */
function stage2PhpFiles(string $root): array
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
function stage2Partitions(array $files, int $workers): array
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
 * @return array{
 *     fileName: string,
 *     ast: array<int, \PhpParser\Node>|null,
 *     errorHandler: ParserErrorHandler
 * }
 */
function stage2ParseFile(string $file): array
{
    $content = file_get_contents($file);
    if (!is_string($content)) {
        throw new RuntimeException('Could not read file: ' . $file);
    }

    $errorHandler = new ParserErrorHandler();
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $ast = $parser->parse($content, $errorHandler);

    return [
        'fileName' => $file,
        'ast' => $ast,
        'errorHandler' => $errorHandler,
    ];
}

/**
 * @param list<string> $files
 *
 * @return array{
 *     records: list<array{
 *         fileName: string,
 *         ast: array<int, \PhpParser\Node>|null,
 *         errorHandler: ParserErrorHandler
 *     }>,
 *     workers: int
 * }
 */
function stage2ParallelParse(array $files): array
{
    if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
        throw new RuntimeException('pcntl is unavailable');
    }

    $workers = max(1, min(STAGE2_MAX_WORKERS, Utils::getCpuCores(), count($files)));
    $partitions = stage2Partitions($files, $workers);
    $tmpRoot = sys_get_temp_dir() . '/simple-php-parser-stage2-' . getmypid() . '-' . bin2hex(random_bytes(4));

    if (!mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        throw new RuntimeException('Could not create temporary stage2 directory: ' . $tmpRoot);
    }

    /** @var list<array{pid: int, file: string}> $children */
    $children = [];

    try {
        foreach ($partitions as $index => $partition) {
            $resultFile = $tmpRoot . '/worker-' . $index . '.ser';
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('pcntl_fork() failed for stage2 worker ' . $index);
            }

            if ($pid === 0) {
                try {
                    $records = [];
                    foreach ($partition as $file) {
                        $records[] = stage2ParseFile($file);
                    }

                    $bytes = file_put_contents($resultFile, serialize($records), LOCK_EX);
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

        $records = [];
        foreach ($children as $child) {
            $status = 0;
            pcntl_waitpid($child['pid'], $status);

            if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                $errorFile = $child['file'] . '.error';
                $detail = is_file($errorFile) ? (string) file_get_contents($errorFile) : 'no child error detail';
                throw new RuntimeException(
                    'Stage2 worker failed: pid=' . $child['pid']
                    . ', status=' . $status
                    . ', detail=' . $detail
                );
            }

            $payload = file_get_contents($child['file']);
            if (!is_string($payload) || $payload === '') {
                throw new RuntimeException('Stage2 worker returned no payload: ' . $child['pid']);
            }

            $decoded = unserialize($payload, ['allowed_classes' => true]);
            if (!is_array($decoded)) {
                throw new RuntimeException('Stage2 worker payload was not an array: ' . $child['pid']);
            }

            foreach ($decoded as $record) {
                if (
                    !is_array($record)
                    || !isset($record['fileName'], $record['errorHandler'])
                    || !is_string($record['fileName'])
                    || !$record['errorHandler'] instanceof ParserErrorHandler
                    || (!is_array($record['ast']) && $record['ast'] !== null)
                ) {
                    throw new RuntimeException('Stage2 worker payload contained an invalid record');
                }

                $records[] = $record;
            }
        }

        return ['records' => $records, 'workers' => $workers];
    } finally {
        foreach (glob($tmpRoot . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tmpRoot);
    }
}

/**
 * Build the model in the parent process from raw parsed ASTs.
 *
 * This deliberately keeps name resolution, ParentConnector,
 * PhpDocContextConnector, ASTVisitor and cross-file finalisation in one shared
 * ParserContainer. Only lexing/parsing happened in workers.
 *
 * @param list<array{
 *     fileName: string,
 *     ast: array<int, \PhpParser\Node>|null,
 *     errorHandler: ParserErrorHandler
 * }> $records
 */
function stage2BuildContainer(array $records, ParserOptions $options): ParserContainer
{
    $container = new ParserContainer($options);
    $visitor = new ASTVisitor($container);

    $resolveAst = new ReflectionMethod(PhpCodeParser::class, 'resolveAst');
    $resolveAst->setAccessible(true);

    foreach ($records as $record) {
        $ast = $record['ast'];
        $errorHandler = $record['errorHandler'];

        if ($ast === null) {
            $container->setParseError($errorHandler);
            continue;
        }

        $resolveAst->invoke(null, $ast, $errorHandler);

        $visitor->fileName = $record['fileName'];

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
    }

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

    return $container;
}

/**
 * @return mixed
 */
function stage2NormalizeValue($value, SplObjectStorage $seen)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = stage2NormalizeValue($item, $seen);
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

        $result[$property] = stage2NormalizeValue($propertyValue, $seen);
    }

    return $result;
}

/**
 * @return array<string, mixed>
 */
function stage2NormalizeContainer(ParserContainer $container): array
{
    $seen = new SplObjectStorage();

    return [
        'classes' => stage2NormalizeValue($container->getClasses(), $seen),
        'interfaces' => stage2NormalizeValue($container->getInterfaces(), $seen),
        'traits' => stage2NormalizeValue($container->getTraits(), $seen),
        'enums' => stage2NormalizeValue($container->getEnums(), $seen),
        'constants' => stage2NormalizeValue($container->getConstants(), $seen),
        'functions' => stage2NormalizeValue($container->getFunctions(), $seen),
        'parse_errors' => $container->getParseErrors(),
    ];
}

/**
 * @return array{path: string, left: mixed, right: mixed}|null
 */
function stage2FirstDiff($left, $right, string $path = '$'): ?array
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
        $diff = stage2FirstDiff($leftValue, $right[$key], $path . '[' . var_export($key, true) . ']');
        if ($diff !== null) {
            return $diff;
        }
    }

    return null;
}

/**
 * @return array{value: mixed, ms: float}
 */
function stage2Timed(callable $callback): array
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
function stage2Median(array $values): float
{
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);

    if ($count % 2 === 1) {
        return $values[$middle];
    }

    return ($values[$middle - 1] + $values[$middle]) / 2;
}

function stage2CreateFixture(): string
{
    $root = sys_get_temp_dir() . '/simple-php-parser-stage2-fixture-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Could not create stage2 fixture directory');
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

function stage2RemoveTree(string $root): void
{
    foreach (glob($root . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($root);
}

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Stage2 experiment must run in CLI');
}

if (!function_exists('pcntl_fork')) {
    throw new RuntimeException('pcntl_fork() is required for stage2 experiment');
}

$options = ParserOptions::astOnly();
$sourceRoot = realpath(__DIR__ . '/../src/voku/SimplePhpParser');
if (!is_string($sourceRoot)) {
    throw new RuntimeException('Could not resolve parser source root');
}

$fixtureRoot = stage2CreateFixture();

try {
    $cases = [
        'repository-source' => $sourceRoot,
        'cross-file-inheritance' => $fixtureRoot,
    ];

    $report = [
        'php' => PHP_VERSION,
        'cpu_cores' => Utils::getCpuCores(),
        'max_workers' => STAGE2_MAX_WORKERS,
        'rounds' => STAGE2_ROUNDS,
        'boundary' => 'parallel raw parse; sequential shared resolve/model/finalize',
        'cases' => [],
    ];

    foreach ($cases as $name => $root) {
        $files = stage2PhpFiles($root);

        $sequential = stage2Timed(
            static fn (): ParserContainer => PhpCodeParser::getPhpFiles($root, options: $options)
        );

        $parallel = stage2Timed(
            static function () use ($files, $options): array {
                $parsed = stage2ParallelParse($files);

                return [
                    'container' => stage2BuildContainer($parsed['records'], $options),
                    'workers' => $parsed['workers'],
                ];
            }
        );

        /** @var ParserContainer $sequentialContainer */
        $sequentialContainer = $sequential['value'];
        /** @var array{container: ParserContainer, workers: int} $parallelValue */
        $parallelValue = $parallel['value'];

        $left = stage2NormalizeContainer($sequentialContainer);
        $right = stage2NormalizeContainer($parallelValue['container']);
        $diff = stage2FirstDiff($left, $right);

        $sequentialTimes = [$sequential['ms']];
        $parallelTimes = [$parallel['ms']];

        for ($round = 1; $round < STAGE2_ROUNDS; ++$round) {
            $sequentialRound = stage2Timed(
                static fn (): ParserContainer => PhpCodeParser::getPhpFiles($root, options: $options)
            );
            $parallelRound = stage2Timed(
                static function () use ($files, $options): ParserContainer {
                    $parsed = stage2ParallelParse($files);

                    return stage2BuildContainer($parsed['records'], $options);
                }
            );

            $sequentialTimes[] = $sequentialRound['ms'];
            $parallelTimes[] = $parallelRound['ms'];
        }

        $sequentialMedian = stage2Median($sequentialTimes);
        $parallelMedian = stage2Median($parallelTimes);

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

    $outputDir = __DIR__ . '/../build';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Could not create build directory');
    }

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($outputDir . '/parallel-parser-stage2-experiment.json', $json . PHP_EOL);
    echo $json . PHP_EOL;

    if ($report['all_equal'] !== true) {
        exit(2);
    }
} finally {
    stage2RemoveTree($fixtureRoot);
}
