<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

use Roave\BetterReflection\Reflection\ReflectionClass;

final class PhpCodeChecker
{
    public static function checkFromString(
        string $code,
        array $access = ['public', 'protected', 'private'],
        bool $skipMixedTypesAsError = false,
        bool $skipDeprecatedMethods = false,
        bool $skipFunctionsWithLeadingUnderscore = false,
        bool $skipParseErrorsAsError = true
    ): array {
        return self::checkPhpFiles(
            $code,
            $access,
            $skipMixedTypesAsError,
            $skipDeprecatedMethods,
            $skipFunctionsWithLeadingUnderscore,
            $skipParseErrorsAsError
        );
    }

    /**
     * @param string   $path
     * @param bool     $skipMixedTypesAsError
     * @param string[] $access
     * @param bool     $skipDeprecatedFunctions
     * @param bool     $skipFunctionsWithLeadingUnderscore
     * @param bool     $skipParseErrorsAsError
     * @param string[] $autoloaderProjectPaths
     * @param string[] $pathExcludeRegex
     *
     * @return string[][]
     */
    public static function checkPhpFiles(
        string $path,
        array $access = ['public', 'protected', 'private'],
        bool $skipMixedTypesAsError = false,
        bool $skipDeprecatedFunctions = false,
        bool $skipFunctionsWithLeadingUnderscore = false,
        bool $skipParseErrorsAsError = true,
        array $autoloaderProjectPaths = [],
        array $pathExcludeRegex = []
    ): array {
        $phpInfo = PhpCodeParser::getPhpFiles(
            $path,
            $autoloaderProjectPaths,
            $pathExcludeRegex
        );

        $errors = $phpInfo->getParseErrors();

        $errors = self::checkFunctions(
            $phpInfo,
            $skipDeprecatedFunctions,
            $skipFunctionsWithLeadingUnderscore,
            $skipMixedTypesAsError,
            $skipParseErrorsAsError,
            $errors
        );

        return self::checkClasses(
            $phpInfo,
            $access,
            $skipDeprecatedFunctions,
            $skipFunctionsWithLeadingUnderscore,
            $skipMixedTypesAsError,
            $skipParseErrorsAsError,
            $errors
        );
    }

    /**
     * @param \voku\SimplePhpParser\Parsers\Helper\ParserContainer $phpInfo
     * @param string[]                                             $access
     * @param bool                                                 $skipDeprecatedMethods
     * @param bool                                                 $skipMethodsWithLeadingUnderscore
     * @param bool                                                 $skipMixedTypesAsError
     * @param bool                                                 $skipParseErrorsAsError
     * @param array                                                $error
     *
     * @return array
     */
    private static function checkClasses(
        Helper\ParserContainer $phpInfo,
        array $access,
        bool $skipDeprecatedMethods,
        bool $skipMethodsWithLeadingUnderscore,
        bool $skipMixedTypesAsError,
        bool $skipParseErrorsAsError,
        array $error
    ): array {
        foreach ($phpInfo->getClasses() as $class) {
            foreach ($class->getPropertiesInfo(
                $access,
                $skipMethodsWithLeadingUnderscore
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

                if ($typeFound) {
                    if ($propertyTypes['typeFromPhpDocSimple'] && $propertyTypes['type']) {
                        $error = self::checkPhpDocType(
                            $propertyTypes,
                            ['file' => $class->file, 'line' => $class->line],
                            $class->name,
                            $error,
                            $class->name,
                            null,
                            $propertyName
                        );
                    }
                } else {
                    $error[$class->file][] = '[' . $class->line . ']: missing property type for ' . $class->name . '->$' . $propertyName;
                }
            }

            foreach ($class->getMethodsInfo(
                $access,
                $skipDeprecatedMethods,
                $skipMethodsWithLeadingUnderscore
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
                    if ($typeFound) {
                        if ($paramTypes['typeFromPhpDocSimple'] && $paramTypes['type']) {
                            $error = self::checkPhpDocType(
                                $paramTypes,
                                $methodInfo,
                                $class->name . ($methodInfo['is_static'] ? '::' : '->') . $methodName . '()',
                                $error,
                                $class->name,
                                $paramName
                            );
                        }
                    } else {
                        $error[$methodInfo['file']][] = '[' . $methodInfo['line'] . ']: missing parameter type for ' . $class->name . ($methodInfo['is_static'] ? '::' : '->') . $methodName . '() | parameter:' . $paramName;
                    }
                }

                /** @noinspection InArrayCanBeUsedInspection */
                if (
                    $methodName !== '__construct'
                    &&
                    $methodName !== '__destruct'
                    &&
                    $methodName !== '__unset'
                    &&
                    $methodName !== '__wakeup'
                    &&
                    $methodName !== '__clone'
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
                    if ($typeFound) {
                        if ($methodInfo['returnTypes']['typeFromPhpDocSimple'] && $methodInfo['returnTypes']['type']) {
                            /** @noinspection ArgumentEqualsDefaultValueInspection */
                            $error = self::checkPhpDocType(
                                $methodInfo['returnTypes'],
                                $methodInfo,
                                $class->name . ($methodInfo['is_static'] ? '::' : '->') . $methodName . '()',
                                $error,
                                $class->name,
                                null
                            );
                        }
                    } else {
                        $error[$methodInfo['file']][] = '[' . $methodInfo['line'] . ']: missing return type for ' . $class->name . ($methodInfo['is_static'] ? '::' : '->') . $methodName . '()';
                    }

                    if (!$skipParseErrorsAsError && $methodInfo['error']) {
                        $error[$methodInfo['file']][] = '[' . $methodInfo['line'] . ']: ' . $methodInfo['error'];
                    }
                }
            }
        }

        return $error;
    }

    /**
     * @param \voku\SimplePhpParser\Parsers\Helper\ParserContainer $phpInfo
     * @param bool                                                 $skipDeprecatedFunctions
     * @param bool                                                 $skipFunctionsWithLeadingUnderscore
     * @param bool                                                 $skipMixedTypesAsError
     * @param bool                                                 $skipParseErrorsAsError
     * @param array                                                $error
     *
     * @return string[][]
     */
    private static function checkFunctions(
        Helper\ParserContainer $phpInfo,
        bool $skipDeprecatedFunctions,
        bool $skipFunctionsWithLeadingUnderscore,
        bool $skipMixedTypesAsError,
        bool $skipParseErrorsAsError,
        array $error
    ): array {
        foreach ($phpInfo->getFunctionsInfo(
            $skipDeprecatedFunctions,
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
                if ($typeFound) {
                    if ($paramTypes['typeFromPhpDocSimple'] && $paramTypes['type']) {
                        $error = self::checkPhpDocType(
                            $paramTypes,
                            $functionInfo,
                            $functionName . '()',
                            $error,
                            null,
                            $paramName
                        );
                    }
                } else {
                    $error[$functionInfo['file']][] = '[' . $functionInfo['line'] . ']: missing parameter type for ' . $functionName . '() | parameter:' . $paramName;
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
            if ($typeFound) {
                if ($functionInfo['returnTypes']['typeFromPhpDocSimple'] && $functionInfo['returnTypes']['type']) {
                    /** @noinspection ArgumentEqualsDefaultValueInspection */
                    $error = self::checkPhpDocType(
                        $functionInfo['returnTypes'],
                        $functionInfo,
                        $functionName . '()',
                        $error,
                        null,
                        null
                    );
                }
            } else {
                $error[$functionInfo['file']][] = '[' . $functionInfo['line'] . ']: missing return type for ' . $functionName . '()';
            }

            if (!$skipParseErrorsAsError && $functionInfo['error']) {
                $error[$functionInfo['file']][] = '[' . $functionInfo['line'] . ']: ' . $functionInfo['error'];
            }
        }

        return $error;
    }

    /**
     * @param array       $types
     * @param array       $fileInfo
     * @param string[][]  $error
     * @param string      $name
     * @param string|null $className
     * @param string|null $paramName
     * @param string|null $propertyName
     *
     * @psalm-param array{type: null|string, typeFromPhpDoc: null|string, typeFromPhpDocPslam: null|string, typeFromPhpDocSimple: null|string, typeMaybeWithComment: null|string, ?typeFromDefaultValue: null|string} $types
     * @psalm-param array{file: null|string, line: null|string}
     *
     * @return array
     */
    private static function checkPhpDocType(
        array $types,
        array $fileInfo,
        string $name,
        array $error,
        string $className = null,
        string $paramName = null,
        string $propertyName = null
    ): array {
        // init
        $typeFromPhpWithoutNull = null;
        $typeFromPhpDocInput = $types['typeFromPhpDocSimple'];
        $typeFromPhpInput = $types['type'];

        $removeEmptyStringFunc = static function ($tmp) {
            return $tmp !== '';
        };
        $typeFromPhpDoc = \array_filter(
            \explode('|', $typeFromPhpDocInput ?? ''),
            $removeEmptyStringFunc
        );
        /** @noinspection AlterInForeachInspection */
        foreach ($typeFromPhpDoc as $keyTmp => $typeFromPhpDocSingle) {
            /** @noinspection InArrayCanBeUsedInspection */
            if (
                $typeFromPhpDocSingle === '$this'
                ||
                $typeFromPhpDocSingle === 'static'
                ||
                $typeFromPhpDocSingle === 'self'
            ) {
                $typeFromPhpDoc[$keyTmp] = $className;
            }

            if (\is_string($typeFromPhpDoc[$keyTmp])) {
                $typeFromPhpDoc[$keyTmp] = \ltrim($typeFromPhpDoc[$keyTmp], '\\');
            }
        }
        $typeFromPhp = \array_filter(
            \explode('|', $typeFromPhpInput ?? ''),
            $removeEmptyStringFunc
        );
        /** @noinspection AlterInForeachInspection */
        foreach ($typeFromPhp as $keyTmp => $typeFromPhpSingle) {
            /** @noinspection InArrayCanBeUsedInspection */
            if (
                $typeFromPhpSingle === '$this'
                ||
                $typeFromPhpSingle === 'static'
                ||
                $typeFromPhpSingle === 'self'
            ) {
                $typeFromPhp[$keyTmp] = $className;
            }

            if (\is_string($typeFromPhp[$keyTmp])) {
                $typeFromPhp[$keyTmp] = \ltrim($typeFromPhp[$keyTmp], '\\');
            }

            if ($typeFromPhpSingle && \strtolower($typeFromPhpSingle) !== 'null') {
                $typeFromPhpWithoutNull = $typeFromPhp[$keyTmp];
            }
        }

        if (
            \count($typeFromPhpDoc) > 0
            &&
            \count($typeFromPhp) > 0
        ) {
            foreach ($typeFromPhp as $typeFromPhpSingle) {
                // reset
                $checked = null;

                /** @noinspection SuspiciousBinaryOperationInspection */
                if (
                    $typeFromPhpSingle
                    &&
                    $typeFromPhpDocInput
                    &&
                    !\in_array($typeFromPhpSingle, $typeFromPhpDoc, true)
                    &&
                    (
                        $typeFromPhpSingle === 'array' && \strpos($typeFromPhpDocInput, '[]') === false
                        ||
                        $typeFromPhpSingle !== 'array'
                    )
                ) {
                    $checked = false;

                    /** @noinspection ArgumentEqualsDefaultValueInspection */
                    if (
                        $typeFromPhpSingle
                        &&
                        (
                            \class_exists($typeFromPhpSingle, true)
                            ||
                            \interface_exists($typeFromPhpSingle, true)
                        )
                    ) {
                        foreach ($typeFromPhpDoc as $typeFromPhpDocTmp) {
                            /** @noinspection ArgumentEqualsDefaultValueInspection */
                            if (
                                $typeFromPhpDocTmp
                                &&
                                (
                                    \class_exists($typeFromPhpDocTmp, true)
                                    ||
                                    \interface_exists($typeFromPhpDocTmp, true)
                                )
                                &&
                                (
                                    /** @phpstan-ignore-next-line */
                                    ($typeFromPhpDocReflectionClass = ReflectionClass::createFromName($typeFromPhpDocTmp))
                                    &&
                                    (
                                        $typeFromPhpDocReflectionClass->isSubclassOf($typeFromPhpSingle)
                                        ||
                                        $typeFromPhpDocReflectionClass->implementsInterface($typeFromPhpSingle)
                                    )
                                )
                            ) {
                                $checked = true;

                                break;
                            }
                        }
                    }

                    if (!$checked) {
                        if ($propertyName) {
                            $error[$fileInfo['file']][] = '[' . $fileInfo['line'] . ']: missing property type "' . $typeFromPhpSingle . '" in phpdoc from ' . $name . ' | property:' . $propertyName;
                        } elseif ($paramName) {
                            $error[$fileInfo['file']][] = '[' . $fileInfo['line'] . ']: missing parameter type "' . $typeFromPhpSingle . '" in phpdoc from ' . $name . ' | parameter:' . $paramName;
                        } else {
                            $error[$fileInfo['file']][] = '[' . $fileInfo['line'] . ']: missing return type "' . $typeFromPhpSingle . '" in phpdoc from ' . $name;
                        }
                    }
                }
            }

            foreach ($typeFromPhpDoc as $typeFromPhpDocSingle) {
                // reset
                /** @noinspection SuspiciousBinaryOperationInspection */
                /** @noinspection NotOptimalIfConditionsInspection */
                if (
                    (
                        $typeFromPhpDocSingle === 'null'
                        &&
                        !\in_array($typeFromPhpDocSingle, $typeFromPhp, true)
                    )
                    ||
                    (
                        $typeFromPhpDocSingle !== 'null'
                        &&
                        $typeFromPhpWithoutNull
                        &&
                        $typeFromPhpDocSingle !== $typeFromPhpWithoutNull
                    )
                ) {
                    // reset
                    $checked = null;

                    if (
                        $typeFromPhpWithoutNull === 'bool'
                        &&
                        (
                            $typeFromPhpDocSingle === 'true'
                            ||
                            $typeFromPhpDocSingle === 'false'
                        )
                    ) {
                        $checked = true;
                    }

                    if (
                        $typeFromPhpDocSingle
                        &&
                        $typeFromPhpWithoutNull
                        &&
                        (
                            $typeFromPhpWithoutNull === 'array'
                            ||
                            \ltrim($typeFromPhpWithoutNull, '\\') === 'Generator'
                        )
                        &&
                        \strpos($typeFromPhpDocSingle, '[]') !== false
                    ) {
                        $checked = true;
                    }

                    if (
                        !$checked
                        &&
                        $typeFromPhpWithoutNull
                    ) {
                        $checked = false;

                        /** @noinspection ArgumentEqualsDefaultValueInspection */
                        if (
                            $typeFromPhpWithoutNull
                            &&
                            $typeFromPhpDocSingle
                            &&
                            (
                                \class_exists($typeFromPhpWithoutNull, true)
                                ||
                                \interface_exists($typeFromPhpWithoutNull, true)
                            )
                            &&
                            (
                                \class_exists($typeFromPhpDocSingle, true)
                                ||
                                \interface_exists($typeFromPhpDocSingle, true)
                            )
                        ) {
                            $typeFromPhpDocReflectionClass = ReflectionClass::createFromName($typeFromPhpDocSingle);
                            if (
                                $typeFromPhpDocReflectionClass->isSubclassOf($typeFromPhpWithoutNull)
                                ||
                                $typeFromPhpDocReflectionClass->implementsInterface($typeFromPhpWithoutNull)
                            ) {
                                $checked = true;
                            }
                        }
                    }

                    if (!$checked) {
                        if ($propertyName) {
                            $error[$fileInfo['file']][] = '[' . $fileInfo['line'] . ']: wrong property type "' . $typeFromPhpDocSingle . '" in phpdoc from ' . $name . '  | property:' . $propertyName;
                        } elseif ($paramName) {
                            $error[$fileInfo['file']][] = '[' . $fileInfo['line'] . ']: wrong parameter type "' . $typeFromPhpDocSingle . '" in phpdoc from ' . $name . '  | parameter:' . $paramName;
                        } else {
                            $error[$fileInfo['file']][] = '[' . $fileInfo['line'] . ']: wrong return type "' . $typeFromPhpDocSingle . '" in phpdoc from ' . $name;
                        }
                    }
                }
            }
        }

        return $error;
    }
}
