<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Node\Expr\UnaryMinus;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use voku\SimplePhpParser\Model\PHPAttribute;

final class Utils
{
    public const GET_PHP_PARSER_VALUE_FROM_NODE_HELPER = '!!!_SIMPLE_PHP_CODE_PARSER_HELPER_!!!';

    /**
     * @param array<mixed> $arr
     * @param bool  $group
     *
     * @return array<int|string, mixed>
     */
    public static function flattenArray(array $arr, bool $group): array
    {
        return \iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($arr)), $group);
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tag $parsedParamTag
     *
     * @return array{parsedParamTagStr: string, variableName: null|string}
     */
    public static function splitTypeAndVariable(\phpDocumentor\Reflection\DocBlock\Tag $parsedParamTag): array
    {
        $parsedParamTagStr = $parsedParamTag . '';
        $variableName = null;

        if (\strpos($parsedParamTagStr, '$') !== false) {
            \preg_match('#\$(?<variableName>[^ ]*)#u', $parsedParamTagStr, $variableNameHelper);
            if (isset($variableNameHelper['variableName'])) {
                $variableName = $variableNameHelper['variableName'];
            }
            $parsedParamTagStr = \str_replace(
                (string) $variableName,
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
     * @phpstan-param class-string|null                                         $classStr
     *
     * @return mixed
     *               Will return "Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER" if we can't get the default value
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

                if (
                    \in_array('value', $node->value->getSubNodeNames(), true)
                    &&
                    \property_exists($node->value, 'value')
                ) {
                    return $node->value->value;
                }

                if (
                    \in_array('expr', $node->value->getSubNodeNames(), true)
                    &&
                    \property_exists($node->value, 'expr')
                ) {
                    $exprTmp = $node->value->expr;
                    if (\property_exists($exprTmp, 'value')) {
                        if ($node->value instanceof UnaryMinus) {
                            return -$exprTmp->value;
                        }

                        return $exprTmp->value;
                    }
                }

                if (
                    \in_array('name', $node->value->getSubNodeNames(), true)
                    &&
                    \property_exists($node->value, 'name')
                    &&
                    $node->value->name
                ) {
                    if ($node->value->name instanceof \PhpParser\Node\Name) {
                        $value = $node->value->name->toString();
                    } else {
                        $value = \is_string($node->value->name) ? $node->value->name : (string) $node->value->name;
                    }
                    return $value === 'null' ? null : $value;
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
                if ($item === null) {
                    continue;
                }

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

            if ($node->name->name === 'class') {
                return $className;
            }

            if (\class_exists($className, true)) {
                return \constant($className . '::' . $node->name->name);
            }
        }

        if ($node instanceof \PhpParser\Node\Expr\ConstFetch) {
            $nameStr = $node->name->toString();
            $parts = explode('\\', $nameStr);

            $returnTmp = \strtolower($parts[0]);
            if ($returnTmp === 'true') {
                return true;
            }
            if ($returnTmp === 'false') {
                return false;
            }
            if ($returnTmp === 'null') {
                return null;
            }

            $constantNameTmp = '\\' . $nameStr;
            if (\defined($constantNameTmp)) {
                return \constant($constantNameTmp);
            }
        }

        return self::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER;
    }

    public static function normalizePhpType(string $type_string, bool $sort = false): ?string
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
            case 'never':
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

        if ($sort && \strpos($type_string, '|') !== false) {
            $type_string_exploded = \explode('|', $type_string);
            sort($type_string_exploded);
            $type_string = \implode('|', $type_string_exploded);
        }

        return $type_string;
    }

    /**
     * @param \PhpParser\Node|null $typeNode
     *
     * @return string|null
     */
    public static function typeNodeToString($typeNode): ?string
    {
        if ($typeNode === null) {
            return null;
        }

        if ($typeNode instanceof \PhpParser\Node\NullableType) {
            $innerType = self::typeNodeToString($typeNode->type);

            return $innerType !== null ? 'null|' . $innerType : 'null|mixed';
        }

        if ($typeNode instanceof \PhpParser\Node\UnionType) {
            $parts = [];

            foreach ($typeNode->types as $innerType) {
                if ($innerType instanceof \PhpParser\Node\IntersectionType) {
                    $innerIntersection = self::typeNodeToString($innerType);
                    $parts[] = $innerIntersection !== null ? '(' . $innerIntersection . ')' : 'mixed';

                    continue;
                }

                $parts[] = self::typeNodeToString($innerType) ?? 'mixed';
            }

            return \implode('|', $parts);
        }

        if ($typeNode instanceof \PhpParser\Node\IntersectionType) {
            $parts = [];

            foreach ($typeNode->types as $innerType) {
                $parts[] = self::typeNodeToString($innerType) ?? 'mixed';
            }

            return \implode('&', $parts);
        }

        if ($typeNode instanceof \PhpParser\Node\Name) {
            $typeString = $typeNode->toString();
            if (
                $typeString === 'self'
                ||
                $typeString === 'static'
                ||
                $typeString === 'parent'
            ) {
                return $typeString;
            }

            return '\\' . \ltrim($typeString, '\\');
        }

        if ($typeNode instanceof \PhpParser\Node\Identifier) {
            return self::normalizePhpType($typeNode->name) ?? $typeNode->name;
        }

        if (\method_exists($typeNode, 'toString')) {
            $typeString = $typeNode->toString();

            return self::normalizePhpType($typeString) ?? $typeString;
        }

        if (\property_exists($typeNode, 'name') && $typeNode->name) {
            $typeString = (string) $typeNode->name;

            return self::normalizePhpType($typeString) ?? $typeString;
        }

        return null;
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
            return $type->__toString();
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

        if ($type instanceof \phpDocumentor\Reflection\PseudoTypes\Scalar) {
            return 'string|int|float|bool';
        }

        if ($type instanceof \phpDocumentor\Reflection\PseudoTypes\True_) {
            return 'true';
        }

        if ($type instanceof \phpDocumentor\Reflection\PseudoTypes\False_) {
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

    public static function createFunctionReflectionInstance(string $functionName): ReflectionFunction
    {
        static $FUNCTION_REFLECTION_INSTANCE = [];

        if (isset($FUNCTION_REFLECTION_INSTANCE[$functionName])) {
            return $FUNCTION_REFLECTION_INSTANCE[$functionName];
        }

        $reflection = new \ReflectionFunction($functionName);
        $FUNCTION_REFLECTION_INSTANCE[$functionName] = $reflection;

        return $reflection;
    }

    /**
     * @phpstan-param class-string $className
     *
     * @phpstan-return ReflectionClass<object>
     */
    public static function createClassReflectionInstance(string $className): ReflectionClass
    {
        static $CLASS_REFLECTION_INSTANCE = [];

        if (isset($CLASS_REFLECTION_INSTANCE[$className])) {
            return $CLASS_REFLECTION_INSTANCE[$className];
        }

        $reflection = new ReflectionClass($className);
        $CLASS_REFLECTION_INSTANCE[$className] = $reflection;

        return $reflection;
    }

    /**
     * Factory method for easy instantiation.
     *
     * @param string[] $additionalTags
     *
     * @phpstan-param array<string, class-string<\phpDocumentor\Reflection\DocBlock\Tag>> $additionalTags
     */
    public static function createDocBlockInstance(array $additionalTags = []): \phpDocumentor\Reflection\DocBlockFactoryInterface
    {
        static $DOC_BLOCK_FACTORY_INSTANCE = null;

        if ($DOC_BLOCK_FACTORY_INSTANCE !== null) {
            return $DOC_BLOCK_FACTORY_INSTANCE;
        }

        $DOC_BLOCK_FACTORY_INSTANCE = \phpDocumentor\Reflection\DocBlockFactory::createInstance($additionalTags);

        return $DOC_BLOCK_FACTORY_INSTANCE;
    }

    public static function modernPhpdocTokens(string $input): \PHPStan\PhpDocParser\Parser\TokenIterator
    {
        static $LAXER = null;

        if ($LAXER === null) {
            $config = new \PHPStan\PhpDocParser\ParserConfig([]);
            $LAXER = new \PHPStan\PhpDocParser\Lexer\Lexer($config);
        }

        return new \PHPStan\PhpDocParser\Parser\TokenIterator($LAXER->tokenize($input));
    }

    /**
     * @throws \PHPStan\PhpDocParser\Parser\ParserException
     */
    public static function modernPhpdoc(string $input): string
    {
        static $TYPE_PARSER = null;

        if ($TYPE_PARSER === null) {
            $config = new \PHPStan\PhpDocParser\ParserConfig([]);
            $TYPE_PARSER = new \PHPStan\PhpDocParser\Parser\TypeParser($config, new \PHPStan\PhpDocParser\Parser\ConstExprParser($config));
        }

        $tokens = self::modernPhpdocTokens($input);
        $typeNode = $TYPE_PARSER->parse($tokens);

        return \str_replace(
            [
                ' | ',
            ],
            [
                '|',
            ],
            \trim((string) $typeNode, ')(')
        );
    }

    public static function recoverBrokenPhpdocType(string $input): ?string
    {
        $parts = [];
        foreach (self::modernPhpdocTokens($input)->getTokens() as $token) {
            if (($token[0] ?? '') !== '') {
                $parts[] = $token[0];
            }
        }

        while ($parts !== []) {
            $candidate = \trim(\implode('', $parts));
            if ($candidate === '') {
                return null;
            }

            try {
                return self::modernPhpdoc($candidate);
            } catch (\Exception $e) {
                array_pop($parts);
            }
        }

        return null;
    }

    /**
     * Returns number of cpu cores available for parallelisation.
     *
     * @return int<1, max>
     */
    public static function getCpuCores(): int
    {
        static $cores = null;
        if ($cores === null) {
            $cores = (new \Fidry\CpuCoreCounter\CpuCoreCounter())->getAvailableForParallelisation()->availableCpus;
        }

        return $cores;
    }

    /**
     * Extract PHPAttribute instances from AST node attribute groups.
     *
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     *
     * @return PHPAttribute[]
     */
    public static function extractAttributesFromAstNode(array $attrGroups): array
    {
        $result = [];
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                // If NameResolver has already resolved the name to FullyQualified,
                // use that; otherwise check resolvedName attribute, then fall back
                if ($attr->name instanceof \PhpParser\Node\Name\FullyQualified) {
                    $name = $attr->name->toString();
                } else {
                    $resolvedName = $attr->name->getAttribute('resolvedName');
                    if ($resolvedName instanceof \PhpParser\Node\Name) {
                        $name = $resolvedName->toString();
                    } else {
                        $name = $attr->name->toString();
                    }
                }

                $arguments = [];
                foreach ($attr->args as $arg) {
                    $argValue = self::getPhpParserValueFromNode($arg);
                    if ($argValue === self::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                        $argValue = null;
                    }

                    if ($arg->name !== null) {
                        $arguments[$arg->name->name] = $argValue;
                    } else {
                        $arguments[] = $argValue;
                    }
                }

                $result[] = new PHPAttribute($name, $arguments);
            }
        }

        return $result;
    }

    /**
     * Extract PHPAttribute instances from a Reflection object that supports getAttributes().
     *
     * @param \ReflectionClass<object>|\ReflectionMethod|\ReflectionProperty|\ReflectionClassConstant|\ReflectionParameter|\ReflectionFunction $reflection
     *
     * @return PHPAttribute[]
     */
    public static function extractAttributesFromReflection($reflection): array
    {
        $result = [];
        foreach ($reflection->getAttributes() as $attr) {
            $result[] = new PHPAttribute($attr->getName(), $attr->getArguments());
        }

        return $result;
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
