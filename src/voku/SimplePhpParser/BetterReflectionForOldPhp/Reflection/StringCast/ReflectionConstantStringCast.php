<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionConstant;

/**
 * Implementation of ReflectionConstant::__toString()
 *
 * @internal
 */
final class ReflectionConstantStringCast
{
    /**
     * @param ReflectionConstant $constantReflection
     *
     * @return string
     */
    public static function toString(ReflectionConstant $constantReflection): string
    {
        $value = $constantReflection->getValue();
        \assert($value === null || \is_scalar($value));

        return \sprintf(
            'Constant [ <%s> %s %s ] {%s %s }',
            self::sourceToString($constantReflection),
            \gettype($value),
            $constantReflection->getName(),
            self::fileAndLinesToString($constantReflection),
            (string) $value
        );
    }

    /**
     * @param ReflectionConstant $constantReflection
     *
     * @return string
     */
    private static function sourceToString(ReflectionConstant $constantReflection): string
    {
        if ($constantReflection->isUserDefined()) {
            return 'user';
        }

        return \sprintf('internal:%s', $constantReflection->getExtensionName());
    }

    /**
     * @param ReflectionConstant $constantReflection
     *
     * @return string
     */
    private static function fileAndLinesToString(ReflectionConstant $constantReflection): string
    {
        if ($constantReflection->isInternal()) {
            return '';
        }

        return \sprintf("\n  @@ %s %d - %d\n", $constantReflection->getFileName(), $constantReflection->getStartLine(), $constantReflection->getEndLine());
    }
}
