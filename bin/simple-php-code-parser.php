<?php

/** @noinspection InvertedIfElseConstructsInspection */
/** @noinspection TransitiveDependenciesUsageInspection */

declare(strict_types=1);

namespace voku\SimplePhpParser;

use Symfony\Component\Console\Application;
use voku\SimplePhpParser\CliCommand\PhpCodeCheckerCommand;

(static function () {
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');
    gc_disable(); // performance boost

    \define('__SIMPLE_PHP_CODE_PARSER_RUNNING__', true);

    /** @noinspection UsingInclusionOnceReturnValueInspection */
    /** @noinspection UsingInclusionReturnValueInspection */
    $devOrPharLoader = require_once __DIR__ . '/../vendor/autoload.php';
    $devOrPharLoader->unregister();

    $autoloaderInWorkingDirectory = getcwd() . '/vendor/autoload.php';
    $autoloaderProjectPaths = [];
    if (is_file($autoloaderInWorkingDirectory)) {
        $autoloaderProjectPaths[] = \dirname($autoloaderInWorkingDirectory, 2);

        /** @noinspection PhpIncludeInspection */
        require_once $autoloaderInWorkingDirectory;
    }

    $autoloadProjectAutoloaderFile = static function (string $file) use (&$autoloaderProjectPaths): void {
        $path = \dirname(__DIR__) . $file;
        if (!\extension_loaded('phar')) {
            if (is_file($path)) {
                $autoloaderProjectPaths[] = \dirname($path, 2);

                /** @noinspection PhpIncludeInspection */
                require_once $path;
            }
        } else {
            $pharPath = \Phar::running(false);
            if ($pharPath === '') {
                if (\is_file($path)) {
                    $autoloaderProjectPaths[] = \dirname($path, 2);

                    /** @noinspection PhpIncludeInspection */
                    require_once $path;
                }
            } else {
                $path = \dirname($pharPath) . $file;
                if (\is_file($path)) {
                    $autoloaderProjectPaths[] = \dirname($path, 2);

                    /** @noinspection PhpIncludeInspection */
                    require_once $path;
                }
            }
        }
    };

    $autoloadProjectAutoloaderFile('/../../autoload.php');

    $devOrPharLoader->register(true);

    $reversedAutoloaderProjectPaths = array_reverse($autoloaderProjectPaths);

    $app = new Application('simple-php-code-parser');

    /** @noinspection UnusedFunctionResultInspection */
    $app->add(new PhpCodeCheckerCommand($reversedAutoloaderProjectPaths));


    /** @noinspection UnusedFunctionResultInspection */
    $app->add(new \voku\SimplePhpParser\CliCommand\PhpCodeDumpApi($reversedAutoloaderProjectPaths));

    /** @noinspection PhpUnhandledExceptionInspection */
    $app->run();
})();
