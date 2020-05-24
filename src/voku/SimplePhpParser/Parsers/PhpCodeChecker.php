<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

final class PhpCodeChecker
{
    public static function checkFromString(
        string $code,
        array $access = ['public', 'protected', 'private'],
        bool $skipMixedTypesAsError = false,
        bool $skipDeprecatedMethods = false,
        bool $skipFunctionsWithLeadingUnderscore = false
    ): array {
        return self::checkPhpFiles(
            $code,
            $access,
            $skipMixedTypesAsError,
            $skipDeprecatedMethods,
            $skipFunctionsWithLeadingUnderscore,
            false
        );
    }

    /**
     * @param string    $path
     * @param bool      $skipMixedTypesAsError
     * @param string[]  $access
     * @param bool      $skipDeprecatedMethods
     * @param bool      $skipFunctionsWithLeadingUnderscore
     * @param bool|null $usePhpReflection                   <p>
     *                                                      null = Php-Parser + PHP-Reflection<br>
     *                                                      true = PHP-Reflection<br>
     *                                                      false = Php-Parser<br>
     *                                                      <p>
     *
     * @return string[]
     */
    public static function checkPhpFiles(
        string $path,
        array $access = ['public', 'protected', 'private'],
        bool $skipMixedTypesAsError = false,
        bool $skipDeprecatedMethods = false,
        bool $skipFunctionsWithLeadingUnderscore = false,
        bool $usePhpReflection = null
    ): array {
        // init
        $error = [];

        $phpInfo = PhpCodeParser::getPhpFiles($path, $usePhpReflection);

        $error = self::checkFunctions(
            $phpInfo,
            $skipDeprecatedMethods,
            $skipFunctionsWithLeadingUnderscore,
            $skipMixedTypesAsError,
            $error
        );

        return self::checkClasses(
            $phpInfo,
            $access,
            $skipDeprecatedMethods,
            $skipFunctionsWithLeadingUnderscore,
            $skipMixedTypesAsError,
            $error
        );
    }

    /**
     * @param Helper\ParserContainer $phpInfo
     * @param string[]               $access
     * @param bool                   $skipDeprecatedMethods
     * @param bool                   $skipFunctionsWithLeadingUnderscore
     * @param bool                   $skipMixedTypesAsError
     * @param array                  $error
     *
     * @return array
     */
    private static function checkClasses(
        Helper\ParserContainer $phpInfo,
        array $access,
        bool $skipDeprecatedMethods,
        bool $skipFunctionsWithLeadingUnderscore,
        bool $skipMixedTypesAsError,
        array $error
    ): array {
        foreach ($phpInfo->getClasses() as $class) {
            foreach ($class->getMethodsInfo(
                $access,
                $skipDeprecatedMethods,
                $skipFunctionsWithLeadingUnderscore
            ) as $methodName => $methodInfo) {
                foreach ($methodInfo['paramsTypes'] as $paramName => $paramTypes) {
                    $typeFound = false;
                    foreach ($paramTypes as $key => $type) {
                        if ($key === 'typeMaybeWithComment' || $key === 'typeFromDefaultValue') {
                            continue;
                        }

                        if (
                            $type
                            &&
                            ($skipMixedTypesAsError || $type !== 'mixed')
                        ) {
                            $typeFound = true;
                        }
                    }
                    if (!$typeFound) {
                        $error[] = 'missing parameter type for ' . $class->name . '::' . $methodName . '() | parameter:' . $paramName;
                    }
                }

                if (
                    $methodName !== '__construct'
                    &&
                    $methodName !== '__destruct'
                ) {
                    $typeFound = false;
                    foreach ($methodInfo['returnTypes'] as $key => $type) {
                        if ($key === 'typeMaybeWithComment') {
                            continue;
                        }

                        if (
                            $type
                            &&
                            ($skipMixedTypesAsError || $type !== 'mixed')
                        ) {
                            $typeFound = true;
                        }
                    }
                    if (!$typeFound) {
                        $error[] = 'missing return type for ' . $class->name . '::' . $methodName . '()';
                    }

                    if ($methodInfo['error']) {
                        $error[] = $methodInfo['error'];
                    }
                }
            }

            foreach ($class->getPropertiesInfo(
                $access,
                $skipFunctionsWithLeadingUnderscore
            ) as $propertyName => $propertyTypes) {
                $typeFound = false;
                foreach ($propertyTypes as $key => $type) {
                    if ($key === 'typeMaybeWithComment' || $key === 'typeFromDefaultValue') {
                        continue;
                    }

                    if (
                        $type
                        &&
                        ($skipMixedTypesAsError || $type !== 'mixed')
                    ) {
                        $typeFound = true;
                    }
                }
                if (!$typeFound) {
                    $error[] = 'missing property type for ' . $class->name . '->$' . $propertyName;
                }
            }
        }

        return $error;
    }

    /**
     * @param Helper\ParserContainer $phpInfo
     * @param bool                   $skipDeprecatedMethods
     * @param bool                   $skipFunctionsWithLeadingUnderscore
     * @param bool                   $skipMixedTypesAsError
     * @param array                  $error
     *
     * @return array
     */
    private static function checkFunctions(
        Helper\ParserContainer $phpInfo,
        bool $skipDeprecatedMethods,
        bool $skipFunctionsWithLeadingUnderscore,
        bool $skipMixedTypesAsError,
        array $error
    ): array {
        foreach ($phpInfo->getFunctionsInfo(
            $skipDeprecatedMethods,
            $skipFunctionsWithLeadingUnderscore
        ) as $functionName => $functionInfo) {
            foreach ($functionInfo['paramsTypes'] as $paramName => $paramTypes) {
                $typeFound = false;
                foreach ($paramTypes as $key => $type) {
                    if ($key === 'typeMaybeWithComment' || $key === 'typeFromDefaultValue') {
                        continue;
                    }

                    if (
                        $type
                        &&
                        ($skipMixedTypesAsError || $type !== 'mixed')
                    ) {
                        $typeFound = true;
                    }
                }
                if (!$typeFound) {
                    $error[] = 'missing parameter type for ' . $functionName . '() | parameter:' . $paramName;
                }
            }

            $typeFound = false;
            foreach ($functionInfo['returnTypes'] as $key => $type) {
                if ($key === 'typeMaybeWithComment') {
                    continue;
                }

                if (
                    $type
                    &&
                    ($skipMixedTypesAsError || $type !== 'mixed')
                ) {
                    $typeFound = true;
                }
            }
            if (!$typeFound) {
                $error[] = 'missing return type for ' . $functionName . '()';
            }

            if ($functionInfo['error']) {
                $error[] = $functionInfo['error'];
            }
        }

        return $error;
    }
}
