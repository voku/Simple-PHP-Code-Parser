<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Node\Expr\UnaryMinus;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

final class Utils
{
    public const GET_PHP_PARSER_VALUE_FROM_NODE_HELPER = '!!!_SIMPLE_PHP_CODE_PARSER_HELPER_!!!';

    /**
     * @param array $arr
     * @param bool  $group
     *
     * @return array
     */
    public static function flattenArray(array $arr, bool $group): array
    {
        return \iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($arr)), $group);
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
     *
     * @return array
     *
     * @paalm-return array{parsedParamTagStr: string, variableName: null|string}
     */
    public static function splitTypeAndVariable(
        \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
    ): array {
        $parsedParamTagStr = $parsedParamTag . '';
        $variableName = null;

        if (\strpos($parsedParamTagStr, '$') !== false) {
            $variableName = \mb_substr($parsedParamTagStr, (int) \mb_strpos($parsedParamTagStr, '$'));
            $parsedParamTagStr = \str_replace(
                $variableName,
                '',
                $parsedParamTagStr
            );
        }

        // clean-up
        if ($variableName) {
            $variableName = \str_replace('$', '', $variableName);
        }

        $parsedParamTagStr = \trim($parsedParamTagStr);

        return [
            'parsedParamTagStr' => $parsedParamTagStr,
            'variableName'      => $variableName,
        ];
    }

    /**
     * @param \PhpParser\Node\Arg|\PhpParser\Node\Const_|\PhpParser\Node\Expr $node
     * @param string|null                                                     $classStr
     * @param \voku\SimplePhpParser\Parsers\Helper\ParserContainer|null       $parserContainer
     *
     * @psalm-param class-string|null                                         $classStr
     *
     * @return mixed|string
     *                      Will return "Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER" if we can't get the default value
     */
    public static function getPhpParserValueFromNode(
        $node,
        ?string $classStr = null,
        ?ParserContainer $parserContainer = null
    ) {
        if (\property_exists($node, 'value')) {
            /** @psalm-suppress UndefinedPropertyFetch - false-positive ? */
            if (\is_object($node->value)) {
                \assert($node->value instanceof \PhpParser\Node);

                /** @psalm-suppress UndefinedPropertyFetch - false-positive ? */
                if (\property_exists($node->value, 'value')) {
                    /** @psalm-suppress NoInterfaceProperties - false-positive ? */
                    return $node->value->value;
                }

                if (\property_exists($node->value, 'expr')) {
                    if ($node->value instanceof UnaryMinus) {
                        /** @psalm-suppress UndefinedPropertyFetch - false-positive ? */
                        return -$node->value->expr->value;
                    }

                    /** @psalm-suppress NoInterfaceProperties - false-positive ? */
                    return $node->value->expr->value;
                }

                /** @psalm-suppress NoInterfaceProperties - false-positive ? */
                if (
                    \property_exists($node->value, 'name')
                    &&
                    \property_exists($node->value->name, 'parts')
                ) {
                    /** @psalm-suppress NoInterfaceProperties - false-positive ? */
                    return $node->value->name->parts[0];
                }
            }

            /**
             * @psalm-suppress UndefinedPropertyFetch - false-positive from psalm
             */
            return $node->value;
        }

        if ($node instanceof \PhpParser\Node\Expr\Array_) {
            $defaultValue = [];
            foreach ($node->items as $item) {
                /**
                 * @psalm-suppress PossiblyNullPropertyFetch - false-positive ?
                 * @psalm-suppress PossiblyNullArgument - false-positive ?
                 */
                $defaultValue[] = self::getPhpParserValueFromNode($item->value);
            }

            return $defaultValue;
        }

        if ($node instanceof \PhpParser\Node\Expr\ClassConstFetch) {
            \assert($node->class instanceof \PhpParser\Node\Name);
            \assert($node->name instanceof \PhpParser\Node\Identifier);
            $className = $node->class->toString();
            $constantName = $node->name->name;

            if ($className === 'self' || $className === 'static') {
                if ($classStr === null || $parserContainer === null) {
                    return self::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER;
                }

                $className = self::findParentClassDeclaringConstant($classStr, $constantName, $parserContainer);
            }

            $className = '\\' . \ltrim($className, '\\');

            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            return @\constant($className . '::' . $node->name->name);
        }

        if ($node instanceof \PhpParser\Node\Expr\ConstFetch) {
            $returnTmp = \strtolower($node->name->parts[0]);

            if ($returnTmp === 'true') {
                return true;
            }

            if ($returnTmp === 'false') {
                return false;
            }

            if ($returnTmp === 'null') {
                return null;
            }

            return $node->name->parts[0];
        }

        return self::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER;
    }

    public static function normalizePhpType(string $type_string): ?string
    {
        $type_string_lower = \strtolower($type_string);

        /** @noinspection PhpSwitchCaseWithoutDefaultBranchInspection */
        switch ($type_string_lower) {
            case 'int':
            case 'void':
            case 'float':
            case 'string':
            case 'bool':
            case 'callable':
            case 'iterable':
            case 'array':
            case 'object':
            case 'true':
            case 'false':
            case 'null':
            case 'mixed':
                return $type_string_lower;
        }

        /** @noinspection PhpSwitchCaseWithoutDefaultBranchInspection */
        switch ($type_string_lower) {
            case 'boolean':
                return 'bool';

            case 'integer':
                return 'int';

            case 'double':
            case 'real':
                return 'float';
        }

        if ($type_string === '') {
            return null;
        }

        return $type_string;
    }

    /**
     * @param \phpDocumentor\Reflection\Type|\phpDocumentor\Reflection\Type[]|null $type
     *
     * @return string
     *
     * @psalm-suppress InvalidReturnType - false-positive from psalm
     */
    public static function parseDocTypeObject($type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Object_) {
            $tmpObject = (string) $type->getFqsen();
            if ($tmpObject) {
                return $tmpObject;
            }

            return 'object';
        }

        if (\is_array($type) || $type instanceof \phpDocumentor\Reflection\Types\Compound) {
            $types = [];
            foreach ($type as $subType) {
                $types[] = self::parseDocTypeObject($subType);
            }

            /**
             * @psalm-suppress InvalidReturnStatement - false-positive from psalm
             */
            return \implode('|', $types);
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Array_) {
            $valueTypeTmp = $type->getValueType() . '';
            if ($valueTypeTmp !== 'mixed') {
                if (\strpos($valueTypeTmp, '|') !== false) {
                    $valueTypeTmpExploded = \explode('|', $valueTypeTmp);
                    $valueTypeTmp = '';
                    foreach ($valueTypeTmpExploded as $valueTypeTmpExplodedInner) {
                        $valueTypeTmp .= $valueTypeTmpExplodedInner . '[]|';
                    }

                    return \rtrim($valueTypeTmp, '|');
                }

                return $valueTypeTmp . '[]';
            }

            return 'array';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Null_) {
            return 'null';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Mixed_) {
            return 'mixed';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Scalar) {
            return 'string|int|float|bool';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\BooleanTrue) {
            return 'true';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\BooleanFalse) {
            return 'false';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Boolean) {
            return 'bool';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Callable_) {
            return 'callable';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Float_) {
            return 'float';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\String_) {
            return 'string';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Integer) {
            return 'int';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Void_) {
            return 'void';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Resource_) {
            return 'resource';
        }

        return $type . '';
    }

    /**
     * Factory method for easy instantiation.
     *
     * @param string[] $additionalTags
     *
     * @psalm-param array<string, class-string<\phpDocumentor\Reflection\DocBlock\Tag>> $additionalTags
     *
     * @return \phpDocumentor\Reflection\DocBlockFactory
     */
    public static function createDocBlockInstance(array $additionalTags = []): \phpDocumentor\Reflection\DocBlockFactory
    {
        $fqsenResolver = new \phpDocumentor\Reflection\FqsenResolver();
        $tagFactory = new \phpDocumentor\Reflection\DocBlock\StandardTagFactory($fqsenResolver);
        $descriptionFactory = new \phpDocumentor\Reflection\DocBlock\DescriptionFactory($tagFactory);
        $typeResolver = new \phpDocumentor\Reflection\TypeResolver($fqsenResolver);

        $typeResolver->addKeyword('array[]', \phpDocumentor\Reflection\Types\ArrayArray::class);
        $typeResolver->addKeyword('float[]', \phpDocumentor\Reflection\Types\ArrayFloat::class);
        $typeResolver->addKeyword('int[]', \phpDocumentor\Reflection\Types\ArrayInt::class);
        $typeResolver->addKeyword('string[]', \phpDocumentor\Reflection\Types\ArrayString::class);
        $typeResolver->addKeyword('false', \phpDocumentor\Reflection\Types\BooleanFalse::class);
        $typeResolver->addKeyword('true', \phpDocumentor\Reflection\Types\BooleanTrue::class);

        /**
         * @noinspection   PhpParamsInspection
         * @psalm-suppress InvalidArgument - false-positive from "ReflectionDocBlock" + PHP >= 7.2
         */
        $tagFactory->addService($descriptionFactory);

        /**
         * @noinspection   PhpParamsInspection
         * @psalm-suppress InvalidArgument - false-positive from "ReflectionDocBlock" + PHP >= 7.2
         */
        $tagFactory->addService($typeResolver);

        $docBlockFactory = new \phpDocumentor\Reflection\DocBlockFactory($descriptionFactory, $tagFactory);
        foreach ($additionalTags as $tagName => $tagHandler) {
            $docBlockFactory->registerTagHandler($tagName, $tagHandler);
        }

        return $docBlockFactory;
    }

    private static function findParentClassDeclaringConstant(
        string $classStr,
        string $constantName,
        ParserContainer $parserContainer
    ): string {
        do {
            $class = $parserContainer->getClass($classStr);
            if ($class && $class->name && isset($class->constants[$constantName])) {
                return $class->name;
            }

            if ($class && $class->parentClass) {
                $class = $parserContainer->getClass($class->parentClass);
            }
        } while ($class);

        return $classStr;
    }
}
