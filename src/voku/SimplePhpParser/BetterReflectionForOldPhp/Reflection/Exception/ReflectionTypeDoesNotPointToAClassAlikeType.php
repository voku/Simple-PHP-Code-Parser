<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use LogicException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionType;

class ReflectionTypeDoesNotPointToAClassAlikeType extends LogicException
{
    /**
     * @param ReflectionType $type
     *
     * @return static
     */
    public static function for(ReflectionType $type): self
    {
        return new self(\sprintf(
            'Provided %s instance does not point to a class-alike type, but to "%s"',
            \get_class($type),
            $type->__toString()
        ));
    }
}
