<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use RuntimeException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;

class ClassDoesNotExist extends RuntimeException
{
    /**
     * @param Reflection $reflection
     *
     * @return static
     */
    public static function forDifferentReflectionType(Reflection $reflection): self
    {
        return new self(\sprintf('The reflected type "%s" is not a class', $reflection->getName()));
    }
}
