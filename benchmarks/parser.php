<?php

declare(strict_types=1);

use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

// Use one dependency installation for all revisions to isolate source changes.
// php benchmarks/parser.php LIBRARY_ROOT CORPUS_DIRECTORY [files|directory]
$library = realpath($argv[1] ?? dirname(__DIR__));
$corpus = realpath($argv[2] ?? dirname(__DIR__) . '/src');
$mode = $argv[3] ?? 'files';
if ($library === false || $corpus === false || !is_dir($corpus) || !in_array($mode, ['files', 'directory'], true)) {
    throw new InvalidArgumentException('Expected library root, corpus directory, and files|directory mode.');
}

$loader = require_once dirname(__DIR__) . '/vendor/autoload.php';
$loader->setPsr4('voku\\', $library . '/src/voku');
if ((new ReflectionClass(PhpCodeParser::class))->getFileName() !== $library . '/src/voku/SimplePhpParser/Parsers/PhpCodeParser.php') {
    throw new UnexpectedValueException('Autoload class map overrides the requested library revision.');
}

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($corpus)) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);
if ($files === []) {
    throw new InvalidArgumentException('Corpus contains no PHP files.');
}

// All public model fields, plus string representations of PHPDoc value objects.
// Exclude only the back-reference to the container to avoid cycles.
$normalize = static function ($value) use (&$normalize) {
    if (is_array($value)) {
        return array_map($normalize, $value);
    }
    if (is_object($value)) {
        $fields = get_object_vars($value);
        unset($fields['parserContainer']);
        return [
            'class' => get_class($value),
            'text' => $value instanceof Stringable ? (string) $value : null,
            'fields' => $normalize($fields),
        ];
    }
    return $value;
};
$snapshot = static function ($container) use ($normalize): array {
    return $normalize([
        'classes' => $container->getClasses(),
        'interfaces' => $container->getInterfaces(),
        'traits' => $container->getTraits(),
        'enums' => $container->getEnums(),
        'functions' => $container->getFunctions(),
        'constants' => $container->getConstants(),
        'errors' => $container->getParseErrors(),
    ]);
};

$options = ParserOptions::astOnly();
$results = [];
$elapsed = 0;
if ($mode === 'directory') {
    $started = hrtime(true);
    $container = PhpCodeParser::getFromDirectory($corpus, options: $options);
    $elapsed = hrtime(true) - $started;
    $results[] = $snapshot($container);
} else {
    foreach ($files as $file) {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new UnexpectedValueException('Cannot read ' . $file);
        }
        $started = hrtime(true);
        $container = PhpCodeParser::getFromString($source, options: $options);
        $elapsed += hrtime(true) - $started;
        $results[] = $snapshot($container);
        unset($container);
        gc_collect_cycles();
    }
}

if (isset($argv[4])) {
    file_put_contents($argv[4], json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}

echo json_encode([
    'php' => PHP_VERSION,
    'library' => $library,
    'corpus' => $corpus,
    'mode' => $mode,
    'files' => count($files),
    'milliseconds' => round($elapsed / 1e6, 2),
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'model_sha256' => hash('sha256', serialize($results)),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
