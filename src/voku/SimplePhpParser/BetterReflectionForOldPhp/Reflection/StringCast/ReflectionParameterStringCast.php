<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionParameter;

/**
 * @internal
 */
final class ReflectionParameterStringCast
{
    /**
     * @param ReflectionParameter $parameterReflection
     *
     * @return string
     */
    public static function toString(ReflectionParameter $parameterReflection): string
    {
        return \sprintf(
            'Parameter #%d [ %s %s%s%s$%s%s ]',
            $parameterReflection->getPosition(),
            $parameterReflection->isOptional() ? '<optional>' : '<required>',
            self::typeToString($parameterReflection),
            $parameterReflection->isVariadic() ? '...' : '',
            $parameterReflection->isPassedByReference() ? '&' : '',
            $parameterReflection->getName(),
            self::valueToString($parameterReflection)
        );
    }

    /**
     * @param ReflectionParameter $parameterReflection
     *
     * @return string
     */
    private static function typeToString(ReflectionParameter $parameterReflection): string
    {
        if (!$parameterReflection->hasType()) {
            return '';
        }

        $mapping = [
            'int'  => 'integer',
            'bool' => 'boolean',
        ];

        $originalType = (string) $parameterReflection->getType();

        $type = $mapping[$originalType] ?? $originalType;

        if (!$parameterReflection->allowsNull()) {
            return $type . ' ';
        }

        return $type . ' or NULL ';
    }

    /**
     * @param ReflectionParameter $parameterReflection
     *
     * @return string
     */
    private static function valueToString(ReflectionParameter $parameterReflection): string
    {
        if (!($parameterReflection->isOptional() && $parameterReflection->isDefaultValueAvailable())) {
            return '';
        }

        $defaultValue = $parameterReflection->getDefaultValue();

        if (\is_array($defaultValue)) {
            return ' = Array';
        }

        if (\is_string($defaultValue) && \strlen($defaultValue) > 15) {
            return ' = ' . \var_export(\substr($defaultValue, 0, 15) . '...', true);
        }

        return ' = ' . \var_export($defaultValue, true);
    }
}
