<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use UnexpectedValueException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

class NotAnInterfaceReflection extends UnexpectedValueException
{
    /**
     * @param ReflectionClass $class
     *
     * @return static
     */
    public static function fromReflectionClass(ReflectionClass $class): self
    {
        $type = 'class';

        if ($class->isTrait()) {
            $type = 'trait';
        }

        return new self(\sprintf('Provided node "%s" is not interface, but "%s"', $class->getName(), $type));
    }
}
