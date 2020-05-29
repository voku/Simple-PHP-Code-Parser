<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use UnexpectedValueException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

class NotAClassReflection extends UnexpectedValueException
{
    /**
     * @param ReflectionClass $class
     *
     * @return static
     */
    public static function fromReflectionClass(ReflectionClass $class): self
    {
        $type = 'interface';

        if ($class->isTrait()) {
            $type = 'trait';
        }

        return new self(\sprintf('Provided node "%s" is not class, but "%s"', $class->getName(), $type));
    }
}
