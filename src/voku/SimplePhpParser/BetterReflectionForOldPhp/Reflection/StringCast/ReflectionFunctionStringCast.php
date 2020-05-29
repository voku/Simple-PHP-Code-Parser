<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionParameter;

/**
 * @internal
 */
final class ReflectionFunctionStringCast
{
    /**
     * @param ReflectionFunction $functionReflection
     *
     * @return string
     */
    public static function toString(ReflectionFunction $functionReflection): string
    {
        $parametersFormat = $functionReflection->getNumberOfParameters() > 0 ? "\n\n  - Parameters [%d] {%s\n  }" : '';

        return \sprintf(
            'Function [ <%s> function %s ] {%s' . $parametersFormat . "\n}",
            self::sourceToString($functionReflection),
            $functionReflection->getName(),
            self::fileAndLinesToString($functionReflection),
            \count($functionReflection->getParameters()),
            self::parametersToString($functionReflection)
        );
    }

    /**
     * @param ReflectionFunction $functionReflection
     *
     * @return string
     */
    private static function sourceToString(ReflectionFunction $functionReflection): string
    {
        if ($functionReflection->isUserDefined()) {
            return 'user';
        }

        return \sprintf('internal:%s', $functionReflection->getExtensionName());
    }

    /**
     * @param ReflectionFunction $functionReflection
     *
     * @return string
     */
    private static function fileAndLinesToString(ReflectionFunction $functionReflection): string
    {
        if ($functionReflection->isInternal()) {
            return '';
        }

        return \sprintf("\n  @@ %s %d - %d", $functionReflection->getFileName(), $functionReflection->getStartLine(), $functionReflection->getEndLine());
    }

    private static function parametersToString(ReflectionFunction $functionReflection): string
    {
        return \array_reduce($functionReflection->getParameters(), static function (string $string, ReflectionParameter $parameterReflection): string {
            return $string . "\n    " . ReflectionParameterStringCast::toString($parameterReflection);
        }, '');
    }
}
