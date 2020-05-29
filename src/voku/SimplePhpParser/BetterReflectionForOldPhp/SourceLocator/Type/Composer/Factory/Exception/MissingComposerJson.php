<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\Composer\Factory\Exception;

use UnexpectedValueException;

final class MissingComposerJson extends UnexpectedValueException implements Exception
{
    public static function inProjectPath(string $path): self
    {
        return new self(\sprintf(
            'Could not locate a "composer.json" file in "%s"',
            $path
        ));
    }
}
