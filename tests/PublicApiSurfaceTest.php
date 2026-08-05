<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the public API of every shipped class.
 *
 * Public properties and public methods are the contract of this library, so a
 * removed property, a renamed method, a changed property type or a newly
 * required parameter is a breaking change. Additions are fine and stay allowed
 * on purpose -- this test only fails on removals and on incompatible changes.
 *
 * @internal
 */
final class PublicApiSurfaceTest extends TestCase
{
    /**
     * @var array<class-string, array{properties: array<string, string>, methods: array<string, array{static: bool, required: int, total: int}>}>
     */
    private const PUBLIC_API = [
        'voku\\SimplePhpParser\\Model\\BasePHPClass' => [
            'properties' => [
                'attributes' => 'array',
                'constants' => 'array',
                'deprecatedTags' => 'array',
                'hasDeprecatedTag' => 'bool',
                'hasInternalTag' => 'bool',
                'hasLinkTag' => 'bool',
                'hasMetaTag' => 'bool',
                'hasRemovedTag' => 'bool',
                'hasSeeTag' => 'bool',
                'hasSinceTag' => 'bool',
                'internalTags' => 'array',
                'is_abstract' => '?bool',
                'is_anonymous' => '?bool',
                'is_cloneable' => '?bool',
                'is_final' => '?bool',
                'is_instantiable' => '?bool',
                'is_iterable' => '?bool',
                'is_readonly' => '?bool',
                'linkTags' => 'array',
                'metaTags' => 'array',
                'methods' => 'array',
                'properties' => 'array',
                'removedTags' => 'array',
                'seeTags' => 'array',
                'sinceTags' => 'array',
                'tagNames' => 'array',
                'traitAdaptations' => 'array',
                'traitUses' => 'array',
            ],
            'methods' => [
            ],
        ],
        'voku\\SimplePhpParser\\Model\\BasePHPElement' => [
            'properties' => [
                'endFilePos' => '?int',
                'endLine' => '?int',
                'file' => '?string',
                'line' => '?int',
                'name' => 'string',
                'parseError' => 'array',
                'parserContainer' => 'voku\\SimplePhpParser\\Parsers\\Helper\\ParserContainer',
                'pos' => '?int',
                'startFilePos' => '?int',
            ],
            'methods' => [
                '__construct' => ['static' => false, 'required' => 1, 'total' => 1],
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPAttribute' => [
            'properties' => [
                'arguments' => 'array',
                'name' => 'string',
            ],
            'methods' => [
                '__construct' => ['static' => false, 'required' => 1, 'total' => 2],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPClass' => [
            'properties' => [
                'interfaces' => 'array',
                'name' => 'string',
                'parentClass' => '?string',
            ],
            'methods' => [
                'getMethodsInfo' => ['static' => false, 'required' => 0, 'total' => 3],
                'getPropertiesInfo' => ['static' => false, 'required' => 0, 'total' => 2],
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPConst' => [
            'properties' => [
                'attributes' => 'array',
                'deprecatedTags' => 'array',
                'hasDeprecatedTag' => 'bool',
                'hasInternalTag' => 'bool',
                'hasLinkTag' => 'bool',
                'hasMetaTag' => 'bool',
                'hasRemovedTag' => 'bool',
                'hasSeeTag' => 'bool',
                'hasSinceTag' => 'bool',
                'internalTags' => 'array',
                'is_final' => '?bool',
                'linkTags' => 'array',
                'metaTags' => 'array',
                'parentName' => '?string',
                'removedTags' => 'array',
                'seeTags' => 'array',
                'sinceTags' => 'array',
                'tagNames' => 'array',
                'type' => '?string',
                'typeFromDeclaration' => '?string',
                'value' => 'mixed',
                'visibility' => '?string',
            ],
            'methods' => [
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPDefineConstant' => [
            'properties' => [
            ],
            'methods' => [
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPDocElement' => [
            'properties' => [
                'deprecatedTags' => 'array',
                'hasDeprecatedTag' => 'bool',
                'hasInternalTag' => 'bool',
                'hasLinkTag' => 'bool',
                'hasMetaTag' => 'bool',
                'hasRemovedTag' => 'bool',
                'hasSeeTag' => 'bool',
                'hasSinceTag' => 'bool',
                'internalTags' => 'array',
                'linkTags' => 'array',
                'metaTags' => 'array',
                'removedTags' => 'array',
                'seeTags' => 'array',
                'sinceTags' => 'array',
                'tagNames' => 'array',
            ],
            'methods' => [
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPEnum' => [
            'properties' => [
                'caseDetails' => 'array',
                'cases' => 'array',
                'interfaces' => 'array',
                'name' => 'string',
                'scalarType' => '?string',
            ],
            'methods' => [
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPEnumCase' => [
            'properties' => [
                'attributes' => 'array',
                'value' => 'mixed',
            ],
            'methods' => [
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPFileInfo' => [
            'properties' => [
                'file' => '?string',
                'namespaces' => 'array',
            ],
            'methods' => [
                '__construct' => ['static' => false, 'required' => 2, 'total' => 2],
                'fromAst' => ['static' => true, 'required' => 1, 'total' => 2],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPFunction' => [
            'properties' => [
                'attributes' => 'array',
                'deprecatedTags' => 'array',
                'description' => 'string',
                'hasDeprecatedTag' => 'bool',
                'hasInternalTag' => 'bool',
                'hasLinkTag' => 'bool',
                'hasMetaTag' => 'bool',
                'hasRemovedTag' => 'bool',
                'hasSeeTag' => 'bool',
                'hasSinceTag' => 'bool',
                'internalTags' => 'array',
                'is_returned_by_ref' => '?bool',
                'linkTags' => 'array',
                'metaTags' => 'array',
                'parameters' => 'array',
                'removedTags' => 'array',
                'returnPhpDocRaw' => '?string',
                'returnType' => '?string',
                'returnTypeFromPhpDoc' => '?string',
                'returnTypeFromPhpDocExtended' => '?string',
                'returnTypeFromPhpDocMaybeWithComment' => '?string',
                'returnTypeFromPhpDocResolved' => '?string',
                'returnTypeFromPhpDocSimple' => '?string',
                'seeTags' => 'array',
                'sinceTags' => 'array',
                'summary' => 'string',
                'tagNames' => 'array',
                'throws' => 'array',
            ],
            'methods' => [
                'getReturnType' => ['static' => false, 'required' => 0, 'total' => 0],
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPInterface' => [
            'properties' => [
                'name' => 'string',
                'parentInterfaces' => 'array',
            ],
            'methods' => [
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPMethod' => [
            'properties' => [
                'access' => 'string',
                'is_abstract' => '?bool',
                'is_final' => '?bool',
                'is_inheritdoc' => '?bool',
                'is_override' => '?bool',
                'is_static' => '?bool',
                'parentName' => '?string',
            ],
            'methods' => [
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPParameter' => [
            'properties' => [
                'attributes' => 'array',
                'defaultValue' => 'mixed',
                'is_inheritdoc' => '?bool',
                'is_passed_by_ref' => '?bool',
                'is_promoted' => '?bool',
                'is_vararg' => '?bool',
                'phpDocRaw' => '?string',
                'type' => '?string',
                'typeFromDefaultValue' => '?string',
                'typeFromPhpDoc' => '?string',
                'typeFromPhpDocExtended' => '?string',
                'typeFromPhpDocMaybeWithComment' => '?string',
                'typeFromPhpDocResolved' => '?string',
                'typeFromPhpDocSimple' => '?string',
            ],
            'methods' => [
                'getType' => ['static' => false, 'required' => 0, 'total' => 0],
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 3],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPProperty' => [
            'properties' => [
                'access' => 'string',
                'access_set' => 'string',
                'attributes' => 'array',
                'defaultValue' => 'mixed',
                'hooks' => 'array',
                'is_abstract' => '?bool',
                'is_final' => '?bool',
                'is_inheritdoc' => '?bool',
                'is_readonly' => '?bool',
                'is_static' => '?bool',
                'phpDocRaw' => '?string',
                'type' => '?string',
                'typeFromDefaultValue' => '?string',
                'typeFromPhpDoc' => '?string',
                'typeFromPhpDocExtended' => '?string',
                'typeFromPhpDocMaybeWithComment' => '?string',
                'typeFromPhpDocResolved' => '?string',
                'typeFromPhpDocSimple' => '?string',
            ],
            'methods' => [
                'getType' => ['static' => false, 'required' => 0, 'total' => 0],
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 3],
                'readObjectFromPromotedParam' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Model\\PHPTrait' => [
            'properties' => [
                'name' => 'string',
            ],
            'methods' => [
                'getMethodsInfo' => ['static' => false, 'required' => 0, 'total' => 3],
                'getPropertiesInfo' => ['static' => false, 'required' => 0, 'total' => 2],
                'readObjectFromPhpNode' => ['static' => false, 'required' => 1, 'total' => 2],
                'readObjectFromReflection' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Helper\\DocFactoryProvider' => [
            'properties' => [
            ],
            'methods' => [
                'getDocFactory' => ['static' => true, 'required' => 0, 'total' => 0],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Helper\\ParserContainer' => [
            'properties' => [
            ],
            'methods' => [
                'addClass' => ['static' => false, 'required' => 1, 'total' => 1],
                'addConstant' => ['static' => false, 'required' => 1, 'total' => 1],
                'addEnum' => ['static' => false, 'required' => 1, 'total' => 1],
                'addException' => ['static' => false, 'required' => 1, 'total' => 1],
                'addFunction' => ['static' => false, 'required' => 1, 'total' => 1],
                'addInterface' => ['static' => false, 'required' => 1, 'total' => 1],
                'addTrait' => ['static' => false, 'required' => 1, 'total' => 1],
                'getClass' => ['static' => false, 'required' => 1, 'total' => 1],
                'getClasses' => ['static' => false, 'required' => 0, 'total' => 0],
                'getClassesByReference' => ['static' => false, 'required' => 0, 'total' => 0],
                'getConstants' => ['static' => false, 'required' => 0, 'total' => 0],
                'getEnum' => ['static' => false, 'required' => 1, 'total' => 1],
                'getEnums' => ['static' => false, 'required' => 0, 'total' => 0],
                'getFunctions' => ['static' => false, 'required' => 0, 'total' => 0],
                'getFunctionsInfo' => ['static' => false, 'required' => 0, 'total' => 2],
                'getInterface' => ['static' => false, 'required' => 1, 'total' => 1],
                'getInterfaces' => ['static' => false, 'required' => 0, 'total' => 0],
                'getParseErrors' => ['static' => false, 'required' => 0, 'total' => 0],
                'getTrait' => ['static' => false, 'required' => 1, 'total' => 1],
                'getTraits' => ['static' => false, 'required' => 0, 'total' => 0],
                'setClasses' => ['static' => false, 'required' => 1, 'total' => 1],
                'setConstants' => ['static' => false, 'required' => 1, 'total' => 1],
                'setEnums' => ['static' => false, 'required' => 1, 'total' => 1],
                'setFunctions' => ['static' => false, 'required' => 1, 'total' => 1],
                'setInterfaces' => ['static' => false, 'required' => 1, 'total' => 1],
                'setParseError' => ['static' => false, 'required' => 1, 'total' => 1],
                'setTraits' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Helper\\ParserErrorHandler' => [
            'properties' => [
            ],
            'methods' => [
                'handleError' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Helper\\Utils' => [
            'properties' => [
            ],
            'methods' => [
                'createClassReflectionInstance' => ['static' => true, 'required' => 1, 'total' => 1],
                'createDocBlockInstance' => ['static' => true, 'required' => 0, 'total' => 1],
                'createFunctionReflectionInstance' => ['static' => true, 'required' => 1, 'total' => 1],
                'extractAttributesFromAstNode' => ['static' => true, 'required' => 1, 'total' => 1],
                'extractAttributesFromReflection' => ['static' => true, 'required' => 1, 'total' => 1],
                'flattenArray' => ['static' => true, 'required' => 2, 'total' => 2],
                'getCpuCores' => ['static' => true, 'required' => 0, 'total' => 0],
                'getPhpParserValueFromNode' => ['static' => true, 'required' => 1, 'total' => 3],
                'modernPhpdoc' => ['static' => true, 'required' => 1, 'total' => 1],
                'modernPhpdocTokens' => ['static' => true, 'required' => 1, 'total' => 1],
                'normalizePhpType' => ['static' => true, 'required' => 1, 'total' => 2],
                'parseDocTypeObject' => ['static' => true, 'required' => 1, 'total' => 1],
                'recoverBrokenPhpdocType' => ['static' => true, 'required' => 1, 'total' => 1],
                'splitTypeAndVariable' => ['static' => true, 'required' => 1, 'total' => 1],
                'typeNodeToString' => ['static' => true, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\PhpCodeParser' => [
            'properties' => [
            ],
            'methods' => [
                'getAstFromFile' => ['static' => true, 'required' => 1, 'total' => 1],
                'getAstFromString' => ['static' => true, 'required' => 1, 'total' => 1],
                'getFileInfoFromFile' => ['static' => true, 'required' => 1, 'total' => 1],
                'getFileInfoFromString' => ['static' => true, 'required' => 1, 'total' => 1],
                'getFromClassName' => ['static' => true, 'required' => 1, 'total' => 2],
                'getFromString' => ['static' => true, 'required' => 1, 'total' => 2],
                'getPhpFiles' => ['static' => true, 'required' => 1, 'total' => 4],
                'process' => ['static' => true, 'required' => 4, 'total' => 4],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Visitors\\ASTVisitor' => [
            'properties' => [
                'fileName' => '?string',
            ],
            'methods' => [
                '__construct' => ['static' => false, 'required' => 1, 'total' => 1],
                'combineImplementedInterfaces' => ['static' => false, 'required' => 1, 'total' => 1],
                'combineParentInterfaces' => ['static' => false, 'required' => 1, 'total' => 1],
                'enterNode' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Visitors\\ParentConnector' => [
            'properties' => [
            ],
            'methods' => [
                '__construct' => ['static' => false, 'required' => 0, 'total' => 0],
                'beforeTraverse' => ['static' => false, 'required' => 1, 'total' => 1],
                'enterNode' => ['static' => false, 'required' => 1, 'total' => 1],
                'leaveNode' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
        'voku\\SimplePhpParser\\Parsers\\Visitors\\PhpDocContextConnector' => [
            'properties' => [
            ],
            'methods' => [
                'beforeTraverse' => ['static' => false, 'required' => 1, 'total' => 1],
                'enterNode' => ['static' => false, 'required' => 1, 'total' => 1],
            ],
        ],
    ];

    public function testEveryDocumentedClassStillExists(): void
    {
        foreach (\array_keys(self::PUBLIC_API) as $className) {
            static::assertTrue(
                \class_exists($className) || \interface_exists($className) || \trait_exists($className),
                'missing class: ' . $className
            );
        }
    }

    public function testEveryPublicPropertyStillExistsWithTheSameType(): void
    {
        foreach (self::PUBLIC_API as $className => $api) {
            $reflection = new \ReflectionClass($className);

            foreach ($api['properties'] as $propertyName => $expectedType) {
                static::assertTrue(
                    $reflection->hasProperty($propertyName),
                    'missing property: ' . $className . '::$' . $propertyName
                );

                $property = $reflection->getProperty($propertyName);

                static::assertTrue(
                    $property->isPublic(),
                    'property is no longer public: ' . $className . '::$' . $propertyName
                );

                $actualType = $property->hasType() ? (string) $property->getType() : 'mixed';

                static::assertSame(
                    $expectedType,
                    $actualType,
                    'changed property type: ' . $className . '::$' . $propertyName
                );
            }
        }
    }

    public function testEveryPublicMethodStillExistsWithACompatibleSignature(): void
    {
        foreach (self::PUBLIC_API as $className => $api) {
            $reflection = new \ReflectionClass($className);

            foreach ($api['methods'] as $methodName => $expected) {
                static::assertTrue(
                    $reflection->hasMethod($methodName),
                    'missing method: ' . $className . '::' . $methodName . '()'
                );

                $method = $reflection->getMethod($methodName);

                static::assertTrue(
                    $method->isPublic(),
                    'method is no longer public: ' . $className . '::' . $methodName . '()'
                );

                static::assertSame(
                    $expected['static'],
                    $method->isStatic(),
                    'changed static-ness: ' . $className . '::' . $methodName . '()'
                );

                // a caller that satisfied the old signature must still be able to call it
                static::assertLessThanOrEqual(
                    $expected['required'],
                    $method->getNumberOfRequiredParameters(),
                    'method requires more arguments than before: ' . $className . '::' . $methodName . '()'
                );

                static::assertGreaterThanOrEqual(
                    $expected['total'],
                    $method->getNumberOfParameters(),
                    'method accepts fewer arguments than before: ' . $className . '::' . $methodName . '()'
                );
            }
        }
    }

    /**
     * The model classes are meant to be read directly, so all of their state
     * has to stay reachable from the outside.
     */
    public function testModelClassesDoNotHidePreviouslyPublicState(): void
    {
        foreach (self::PUBLIC_API as $className => $api) {
            if (\strpos($className, '\\Model\\') === false) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                if (!isset($api['properties'][$property->getName()])) {
                    // new properties are allowed, but they must not be a
                    // visibility downgrade of a previously public one
                    continue;
                }

                static::assertTrue(
                    $property->isPublic(),
                    'property visibility downgraded: ' . $className . '::$' . $property->getName()
                );
            }
        }
    }
}
