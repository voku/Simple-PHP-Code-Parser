<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionProperty;

/**
 * @internal
 */
final class ReflectionPropertyStringCast
{
    /**
     * @param ReflectionProperty $propertyReflection
     *
     * @return string
     */
    public static function toString(ReflectionProperty $propertyReflection): string
    {
        $stateModifier = '';

        if (!$propertyReflection->isStatic()) {
            $stateModifier = $propertyReflection->isDefault() ? ' <default>' : ' <dynamic>';
        }

        return \sprintf(
            'Property [%s %s%s $%s ]',
            $stateModifier,
            self::visibilityToString($propertyReflection),
            $propertyReflection->isStatic() ? ' static' : '',
            $propertyReflection->getName()
        );
    }

    /**
     * @param ReflectionProperty $propertyReflection
     *
     * @return string
     */
    private static function visibilityToString(ReflectionProperty $propertyReflection): string
    {
        if ($propertyReflection->isProtected()) {
            return 'protected';
        }

        if ($propertyReflection->isPrivate()) {
            return 'private';
        }

        return 'public';
    }
}
