<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\Exception;

use LogicException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

final class ClassAlreadyLoaded extends LogicException
{
    public static function fromReflectionClass(ReflectionClass $reflectionClass): self
    {
        return new self(\sprintf(
            'Class %s has already been loaded into memory so cannot be modified',
            $reflectionClass->getName()
        ));
    }
}
