<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\Composer\Psr\Exception;

use InvalidArgumentException;

class InvalidPrefixMapping extends InvalidArgumentException implements Exception
{
    public static function emptyPrefixGiven(): self
    {
        return new self('An invalid empty string provided as a PSR mapping prefix');
    }

    public static function emptyPrefixMappingGiven(string $prefix): self
    {
        return new self(\sprintf(
            'An invalid empty list of paths was provided for PSR mapping prefix "%s"',
            $prefix
        ));
    }

    public static function prefixMappingIsNotADirectory(string $prefix, string $path): self
    {
        return new self(\sprintf(
            'Provided path "%s" for prefix "%s" is not a directory',
            $prefix,
            $path
        ));
    }
}
