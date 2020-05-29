<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator;

use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;

class FileChecker
{
    /**
     * @throws InvalidFileLocation
     */
    public static function assertReadableFile(string $filename): void
    {
        if (empty($filename)) {
            throw new InvalidFileLocation('Filename was empty');
        }

        if (!\file_exists($filename)) {
            throw new InvalidFileLocation(\sprintf('File "%s" does not exist', $filename));
        }

        if (!\is_readable($filename)) {
            throw new InvalidFileLocation(\sprintf('File "%s" is not readable', $filename));
        }

        if (!\is_file($filename)) {
            throw new InvalidFileLocation(\sprintf('"%s" is not a file', $filename));
        }
    }
}
