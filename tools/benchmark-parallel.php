<?php

declare(strict_types=1);

use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php benchmark-parallel.php <checkout> <corpus>\n");
    exit(64);
}

$checkout = realpath($argv[1]);
$corpus = realpath($argv[2]);

if ($checkout === false || $corpus === false) {
    fwrite(STDERR, "Checkout or corpus does not exist.\n");
    exit(66);
}

require $checkout . '/vendor/autoload.php';

if (
    !function_exists('pcntl_fork')
    || !function_exists('posix_kill')
    || Utils::getCpuCores() < 2
) {
    fwrite(STDERR, "Parallel runtime unavailable.\n");
    exit(69);
}

$startedAt = hrtime(true);
$result = PhpCodeParser::getPhpFiles(
    $corpus,
    options: ParserOptions::astOnlyParallel()
);
$elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

if ($result->getParseErrors() !== []) {
    fwrite(STDERR, "Benchmark corpus produced parse errors.\n");
    exit(70);
}

printf("%.6f\n", $elapsedMs);
