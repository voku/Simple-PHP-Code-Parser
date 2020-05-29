<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber\Exception;

use RuntimeException;

class CouldNotFindPhpStormStubs extends RuntimeException
{
    public static function create(): self
    {
        return new self('Could not find PhpStorm stubs');
    }
}
