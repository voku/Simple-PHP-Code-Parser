<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\Exception;

use LogicException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

final class ClassAlreadyRegistered extends LogicException
{
    public static function fromReflectionClass(ReflectionClass $reflectionClass): self
    {
        return new self(\sprintf('Class %s already registered', $reflectionClass->getName()));
    }
}
