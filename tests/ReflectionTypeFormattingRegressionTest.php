<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * Locks down how native PHP types are rendered by reflection-only and AST-backed parsing.
 *
 * PHP 8.5 resolves lexical `self` to the declaring class in ReflectionNamedType. Pure
 * reflection therefore cannot recover whether the source said `self` or the explicit
 * current class name. AST-backed parsing still owns the source syntax and must preserve
 * the established source-level `self` representation.
 *
 * @internal
 */
final class ReflectionTypeFormattingRegressionTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private const EXPECTED_PROPERTY_TYPES = [
        'builtin'               => 'int',
        'nullableBuiltin'       => 'null|string',
        'arrayType'             => 'array',
        'iterableType'          => 'iterable',
        'untyped'               => null,
        'interfaceType'         => '\\voku\\tests\\ReflectionTypeFixtureInterface',
        'nullableInterfaceType' => 'null|\\voku\\tests\\ReflectionTypeFixtureInterface',
        'classType'             => '\\voku\\tests\\ReflectionTypeFixture',
        'enumType'              => '\\voku\\tests\\DummyEnum',
        'selfType'              => 'self',
        'globalInterfaceType'   => '\\Traversable',
        'unionType'             => 'int|string',
        // intersection types are rendered by ReflectionIntersectionType::__toString(),
        // which does not emit leading backslashes -- kept as-is on purpose.
        'intersectionType'      => 'Countable&ArrayAccess',
    ];

    /**
     * @var array<string, string|null>
     */
    private const EXPECTED_PARAMETER_TYPES = [
        'builtin'               => 'int',
        'nullableBuiltin'       => 'null|string',
        'arrayType'             => 'array',
        'interfaceType'         => '\\voku\\tests\\ReflectionTypeFixtureInterface',
        'nullableInterfaceType' => 'null|\\voku\\tests\\ReflectionTypeFixtureInterface',
        'classType'             => '\\voku\\tests\\ReflectionTypeFixture',
        'enumType'              => '\\voku\\tests\\DummyEnum',
        'selfType'              => 'self',
        'globalInterfaceType'   => '\\Traversable',
        'unionType'             => 'int|string',
        'intersectionType'      => 'Countable&ArrayAccess',
        'callableType'          => 'callable',
        'untyped'               => null,
    ];

    public function testTypesFromPureReflection(): void
    {
        $class = (new PHPClass(new ParserContainer()))->readObjectFromReflection(
            new \ReflectionClass(ReflectionTypeFixture::class)
        );

        $expectedPropertyTypes = self::EXPECTED_PROPERTY_TYPES;
        $expectedParameterTypes = self::EXPECTED_PARAMETER_TYPES;
        if (\PHP_VERSION_ID >= 80500) {
            // PHP 8.5 resolves both `self` and an explicit current-class declaration to the
            // same ReflectionNamedType name. Pretending we can recover the source token here
            // would also turn the explicit classType into `self`.
            $expectedPropertyTypes['selfType'] = '\\voku\\tests\\ReflectionTypeFixture';
            $expectedParameterTypes['selfType'] = '\\voku\\tests\\ReflectionTypeFixture';
        }

        foreach ($expectedPropertyTypes as $propertyName => $expectedType) {
            static::assertArrayHasKey($propertyName, $class->properties);
            static::assertSame($expectedType, $class->properties[$propertyName]->type, 'property: ' . $propertyName);
        }

        foreach ($expectedParameterTypes as $parameterName => $expectedType) {
            static::assertArrayHasKey($parameterName, $class->methods['method']->parameters);
            static::assertSame(
                $expectedType,
                $class->methods['method']->parameters[$parameterName]->type,
                'parameter: ' . $parameterName
            );
        }

        $expectedReturnType = \PHP_VERSION_ID >= 80500
            ? ReflectionTypeFixture::class
            : 'self';
        static::assertSame($expectedReturnType, $class->methods['method']->returnType);
    }

    public function testFullParsePreservesSourceLevelRelativeTypes(): void
    {
        $class = PhpCodeParser::getPhpFiles(__DIR__ . '/ReflectionTypeFixture.php')
            ->getClasses()[ReflectionTypeFixture::class];

        foreach (self::EXPECTED_PROPERTY_TYPES as $propertyName => $expectedType) {
            static::assertSame($expectedType, $class->properties[$propertyName]->type, 'property: ' . $propertyName);
        }

        foreach (self::EXPECTED_PARAMETER_TYPES as $parameterName => $expectedType) {
            static::assertSame(
                $expectedType,
                $class->methods['method']->parameters[$parameterName]->type,
                'parameter: ' . $parameterName
            );
        }

        static::assertSame('self', $class->methods['method']->returnType);
    }

    public function testPhp85ReflectionCannotDistinguishSelfFromExplicitCurrentClass(): void
    {
        if (\PHP_VERSION_ID < 80500) {
            static::markTestSkipped('PHP 8.5 changed relative ReflectionNamedType resolution.');
        }

        $reflection = new \ReflectionClass(ReflectionTypeFixture::class);
        $classType = $reflection->getProperty('classType')->getType();
        $selfType = $reflection->getProperty('selfType')->getType();

        static::assertInstanceOf(\ReflectionNamedType::class, $classType);
        static::assertInstanceOf(\ReflectionNamedType::class, $selfType);
        static::assertSame($classType->getName(), $selfType->getName());
        static::assertSame((string) $classType, (string) $selfType);
    }

    /**
     * The very same declarations, but in a namespace that cannot be autoloaded,
     * so only the AST path runs. Source-level relative types must stay lexical.
     */
    public function testAstOnlyPathRendersSourceLevelTypes(): void
    {
        $source = <<<'PHP'
<?php

namespace NotLoadable\TypeFormatting;

interface Contract {}

enum Backed: string { case A = 'a'; }

class Subject
{
    public int $builtin = 0;
    public ?string $nullableBuiltin = null;
    public array $arrayType = [];
    public iterable $iterableType;
    public $untyped;
    public Contract $interfaceType;
    public ?Contract $nullableInterfaceType = null;
    public Subject $classType;
    public Backed $enumType;
    public self $selfType;
    public \Traversable $globalInterfaceType;
    public int|string $unionType = 0;

    public function method(
        int $builtin,
        ?string $nullableBuiltin,
        array $arrayType,
        Contract $interfaceType,
        ?Contract $nullableInterfaceType,
        Subject $classType,
        Backed $enumType,
        self $selfType,
        \Traversable $globalInterfaceType,
        int|string $unionType,
        callable $callableType,
        $untyped = null
    ): self {}
}
PHP;

        $class = PhpCodeParser::getFromString($source)->getClasses()['NotLoadable\\TypeFormatting\\Subject'];

        $expectedProperties = [
            'builtin'               => 'int',
            'nullableBuiltin'       => 'null|string',
            'arrayType'             => 'array',
            'iterableType'          => 'iterable',
            'untyped'               => null,
            'interfaceType'         => '\\NotLoadable\\TypeFormatting\\Contract',
            'nullableInterfaceType' => 'null|\\NotLoadable\\TypeFormatting\\Contract',
            'classType'             => '\\NotLoadable\\TypeFormatting\\Subject',
            'enumType'              => '\\NotLoadable\\TypeFormatting\\Backed',
            'selfType'              => 'self',
            'globalInterfaceType'   => '\\Traversable',
            'unionType'             => 'int|string',
        ];

        foreach ($expectedProperties as $propertyName => $expectedType) {
            static::assertSame($expectedType, $class->properties[$propertyName]->type, 'property: ' . $propertyName);
        }

        $expectedParameters = [
            'builtin'               => 'int',
            'nullableBuiltin'       => 'null|string',
            'arrayType'             => 'array',
            'interfaceType'         => '\\NotLoadable\\TypeFormatting\\Contract',
            'nullableInterfaceType' => 'null|\\NotLoadable\\TypeFormatting\\Contract',
            'classType'             => '\\NotLoadable\\TypeFormatting\\Subject',
            'enumType'              => '\\NotLoadable\\TypeFormatting\\Backed',
            'selfType'              => 'self',
            'globalInterfaceType'   => '\\Traversable',
            'unionType'             => 'int|string',
            'callableType'          => 'callable',
            'untyped'               => null,
        ];

        foreach ($expectedParameters as $parameterName => $expectedType) {
            static::assertSame(
                $expectedType,
                $class->methods['method']->parameters[$parameterName]->type,
                'parameter: ' . $parameterName
            );
        }

        static::assertSame('self', $class->methods['method']->returnType);
    }

    /**
     * Formatting a reflected type must never autoload the referenced class,
     * because the referenced class can contain syntax the current runtime
     * cannot even compile.
     */
    public function testFormattingNeverAutoloadsTheReferencedType(): void
    {
        $autoloaded = [];
        $autoloader = static function (string $className) use (&$autoloaded): void {
            $autoloaded[] = $className;
        };

        \spl_autoload_register($autoloader, true, true);
        try {
            $class = (new PHPClass(new ParserContainer()))->readObjectFromReflection(
                new \ReflectionClass(ReflectionTypeFixture::class)
            );
        } finally {
            \spl_autoload_unregister($autoloader);
        }

        static::assertNotContains(ReflectionTypeFixtureInterface::class, $autoloaded);
        static::assertSame(
            '\\voku\\tests\\ReflectionTypeFixtureInterface',
            $class->properties['interfaceType']->type
        );
    }

    /**
     * Types that resolve to nothing at all still have to be rendered, and the
     * result may not depend on whether the class happens to be loaded.
     */
    public function testUnresolvableTypesAreStillFullyQualified(): void
    {
        $source = <<<'PHP'
<?php

namespace NotLoadable\Unresolvable;

class Subject
{
    public \Vendor\Never\Existing $missing;

    public function method(\Vendor\Never\Existing $missing, ?\Vendor\Never\Existing $nullableMissing = null) {}
}
PHP;

        $class = PhpCodeParser::getFromString($source)->getClasses()['NotLoadable\\Unresolvable\\Subject'];

        static::assertSame('\\Vendor\\Never\\Existing', $class->properties['missing']->type);
        static::assertSame('\\Vendor\\Never\\Existing', $class->methods['method']->parameters['missing']->type);
        static::assertSame(
            'null|\\Vendor\\Never\\Existing',
            $class->methods['method']->parameters['nullableMissing']->type
        );
    }
}
