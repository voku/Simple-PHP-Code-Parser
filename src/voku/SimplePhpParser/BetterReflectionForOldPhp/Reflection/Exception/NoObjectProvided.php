<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use InvalidArgumentException;

class NoObjectProvided extends InvalidArgumentException
{
    public static function create(): self
    {
        return new self('No object provided');
    }
}
