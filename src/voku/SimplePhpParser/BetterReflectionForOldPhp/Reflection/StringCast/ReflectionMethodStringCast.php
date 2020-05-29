<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\MethodPrototypeNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionMethod;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionParameter;

/**
 * @internal
 */
final class ReflectionMethodStringCast
{
    /**
     * @param ReflectionMethod     $methodReflection
     * @param ReflectionClass|null $rootClassReflection
     *
     * @return string
     */
    public static function toString(ReflectionMethod $methodReflection, ?ReflectionClass $rootClassReflection = null): string
    {
        $parametersFormat = $methodReflection->getNumberOfParameters() > 0 ? "\n\n  - Parameters [%d] {%s\n  }" : '';

        return \sprintf(
            'Method [ <%s%s%s%s%s%s>%s%s%s %s method %s ] {%s' . $parametersFormat . "\n}",
            self::sourceToString($methodReflection),
            $methodReflection->isConstructor() ? ', ctor' : '',
            $methodReflection->isDestructor() ? ', dtor' : '',
            self::overwritesToString($methodReflection),
            self::inheritsToString($methodReflection, $rootClassReflection),
            self::prototypeToString($methodReflection),
            $methodReflection->isFinal() ? ' final' : '',
            $methodReflection->isStatic() ? ' static' : '',
            $methodReflection->isAbstract() ? ' abstract' : '',
            self::visibilityToString($methodReflection),
            $methodReflection->getName(),
            self::fileAndLinesToString($methodReflection),
            \count($methodReflection->getParameters()),
            self::parametersToString($methodReflection)
        );
    }

    /**
     * @param ReflectionMethod $methodReflection
     *
     * @return string
     */
    private static function sourceToString(ReflectionMethod $methodReflection): string
    {
        if ($methodReflection->isUserDefined()) {
            return 'user';
        }

        return \sprintf('internal:%s', $methodReflection->getExtensionName());
    }

    private static function overwritesToString(ReflectionMethod $methodReflection): string
    {
        $parentClass = $methodReflection->getDeclaringClass()->getParentClass();

        if (!$parentClass) {
            return '';
        }

        if (!$parentClass->hasMethod($methodReflection->getName())) {
            return '';
        }

        return \sprintf(', overwrites %s', $parentClass->getName());
    }

    private static function inheritsToString(ReflectionMethod $methodReflection, ?ReflectionClass $rootClassReflection): string
    {
        if (!$rootClassReflection) {
            return '';
        }

        if ($methodReflection->getDeclaringClass()->getName() === $rootClassReflection->getName()) {
            return '';
        }

        return \sprintf(', inherits %s', $methodReflection->getDeclaringClass()->getName());
    }

    /**
     * @param ReflectionMethod $methodReflection
     *
     * @return string
     */
    private static function prototypeToString(ReflectionMethod $methodReflection): string
    {
        try {
            return \sprintf(', prototype %s', $methodReflection->getPrototype()->getDeclaringClass()->getName());
        } catch (MethodPrototypeNotFound $e) {
            return '';
        }
    }

    /**
     * @param ReflectionMethod $methodReflection
     *
     * @return string
     */
    private static function visibilityToString(ReflectionMethod $methodReflection): string
    {
        if ($methodReflection->isProtected()) {
            return 'protected';
        }

        if ($methodReflection->isPrivate()) {
            return 'private';
        }

        return 'public';
    }

    /**
     * @param ReflectionMethod $methodReflection
     *
     * @return string
     */
    private static function fileAndLinesToString(ReflectionMethod $methodReflection): string
    {
        if ($methodReflection->isInternal()) {
            return '';
        }

        return \sprintf("\n  @@ %s %d - %d", $methodReflection->getFileName(), $methodReflection->getStartLine(), $methodReflection->getEndLine());
    }

    /**
     * @param ReflectionMethod $methodReflection
     *
     * @return string
     */
    private static function parametersToString(ReflectionMethod $methodReflection): string
    {
        return \array_reduce($methodReflection->getParameters(), static function (string $string, ReflectionParameter $parameterReflection): string {
            return $string . "\n    " . ReflectionParameterStringCast::toString($parameterReflection);
        }, '');
    }
}
