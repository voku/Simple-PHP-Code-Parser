<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Model\BasePHPClass;
use voku\SimplePhpParser\Model\BasePHPElement;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * Parses a wide corpus (the library itself plus every fixture) and checks the
 * invariants that hold for every produced model, instead of asserting one
 * hand-picked value at a time.
 *
 * @internal
 */
final class ModelInvariantsRegressionTest extends TestCase
{
    /**
     * Types that are rendered without a leading backslash on purpose.
     *
     * @var string[]
     */
    private const NON_CLASS_TYPES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'parent',
        'self',
        'static',
        'string',
        'true',
        'void',
        '$this',
    ];

    /**
     * Fixtures that need a php-parser which understands PHP 8.4 property hooks.
     *
     * @var string[]
     */
    private const PROPERTY_HOOK_FIXTURES = [
        'DummyPromotedPropertyHooks.php',
        'DummyPropertyHooks.php',
    ];

    private static ?ParserContainer $ownSourceContainer = null;

    public function testTheLibraryItselfParsesWithoutErrors(): void
    {
        $container = self::ownSource();

        static::assertSame([], $container->getParseErrors());
        static::assertNotEmpty($container->getClasses());
        static::assertNotEmpty($container->getTraits());

        $this->assertContainerInvariants($container);
    }

    public function testEveryFixtureParsesWithoutErrors(): void
    {
        $fixtures = \glob(__DIR__ . '/*.php');
        static::assertIsArray($fixtures);
        static::assertNotEmpty($fixtures);

        // nikic/php-parser v4 cannot tokenize property hooks / asymmetric visibility
        $supportsPropertyHooks = \class_exists('PhpParser\\Node\\PropertyHook');

        $parsed = 0;
        foreach ($fixtures as $fixture) {
            $basename = \basename($fixture);
            if ($basename === 'bootstrap.php' || \substr($basename, -8) === 'Test.php') {
                continue;
            }

            if (!$supportsPropertyHooks && \in_array($basename, self::PROPERTY_HOOK_FIXTURES, true)) {
                continue;
            }

            $container = PhpCodeParser::getPhpFiles($fixture);

            static::assertSame([], $container->getParseErrors(), $basename);

            $this->assertContainerInvariants($container, $basename);
            ++$parsed;
        }

        static::assertGreaterThan(20, $parsed);
    }

    /**
     * The regression this guards against: the reflection path used to drop the
     * leading backslash whenever `class_exists()` was false, e.g. for every
     * interface type and for every class that could not be autoloaded.
     */
    public function testEveryClassLikeTypeIsFullyQualified(): void
    {
        $containers = [
            'src'   => self::ownSource(),
            'tests' => PhpCodeParser::getPhpFiles(__DIR__ . '/ReflectionTypeFixture.php'),
        ];

        $checked = 0;
        foreach ($containers as $label => $container) {
            foreach ($this->allClassLikes($container) as $classLike) {
                foreach ($classLike->properties ?? [] as $propertyName => $property) {
                    $checked += $this->assertTypeIsFullyQualified(
                        $property->type,
                        $label . ' ' . $classLike->name . '::$' . $propertyName
                    );
                }

                foreach ($classLike->methods as $methodName => $method) {
                    foreach ($method->parameters as $parameterName => $parameter) {
                        $checked += $this->assertTypeIsFullyQualified(
                            $parameter->type,
                            $label . ' ' . $classLike->name . '::' . $methodName . '($' . $parameterName . ')'
                        );
                    }
                }
            }
        }

        static::assertGreaterThan(100, $checked, 'the corpus did not contain enough types to be meaningful');
    }

    public function testEveryElementKnowsWhereItCameFrom(): void
    {
        $container = self::ownSource();

        foreach ($this->allClassLikes($container) as $classLike) {
            $this->assertHasSourceRange($classLike, $classLike->name);

            foreach ($classLike->methods as $methodName => $method) {
                $this->assertHasSourceRange($method, $classLike->name . '::' . $methodName . '()');
            }
        }
    }

    private static function ownSource(): ParserContainer
    {
        if (self::$ownSourceContainer === null) {
            self::$ownSourceContainer = PhpCodeParser::getPhpFiles(__DIR__ . '/../src/');
        }

        return self::$ownSourceContainer;
    }

    private function assertContainerInvariants(ParserContainer $container, string $label = ''): void
    {
        foreach ($this->allClassLikes($container) as $name => $classLike) {
            static::assertNotSame('', $classLike->name, $label);
            static::assertSame($name, $classLike->name, $label);

            foreach ($classLike->methods as $methodName => $method) {
                static::assertSame($methodName, $method->name, $label . ' ' . $name);
                static::assertNotSame('', $method->name, $label . ' ' . $name);

                foreach ($method->parameters as $parameterName => $parameter) {
                    static::assertSame($parameterName, $parameter->name, $label . ' ' . $name . '::' . $methodName);
                }
            }

            foreach ($classLike->properties ?? [] as $propertyName => $property) {
                static::assertSame($propertyName, $property->name, $label . ' ' . $name);
            }

            foreach ($classLike->constants as $constantName => $constant) {
                static::assertSame($constantName, $constant->name, $label . ' ' . $name);
            }
        }

        foreach ($container->getFunctions() as $functionName => $function) {
            static::assertSame($functionName, $function->name, $label);
        }
    }

    /**
     * @return array<string, BasePHPClass>
     */
    private function allClassLikes(ParserContainer $container): array
    {
        return \array_merge(
            $container->getClasses(),
            $container->getInterfaces(),
            $container->getTraits(),
            $container->getEnums()
        );
    }

    private function assertTypeIsFullyQualified(?string $type, string $label): int
    {
        if ($type === null || $type === '') {
            return 0;
        }

        // intersection types come straight from ReflectionIntersectionType::__toString()
        if (\strpos($type, '&') !== false) {
            return 0;
        }

        $checked = 0;
        foreach (\explode('|', $type) as $singleType) {
            if ($singleType === '' || \in_array(\strtolower($singleType), self::NON_CLASS_TYPES, true)) {
                continue;
            }

            // generic-ish or otherwise non-plain phpdoc types are not normalized here
            if (\strpbrk($singleType, '<>[](){}\'" ') !== false) {
                continue;
            }

            static::assertStringStartsWith(
                '\\',
                $singleType,
                'class-like type without leading backslash in ' . $label . ': ' . $type
            );
            ++$checked;
        }

        return $checked;
    }

    /**
     * @param BasePHPElement|PHPFunction $element
     */
    private function assertHasSourceRange($element, string $label): void
    {
        static::assertNotNull($element->file, $label);
        static::assertNotNull($element->line, $label);
        static::assertGreaterThan(0, $element->line, $label);

        if ($element->endLine !== null) {
            static::assertGreaterThanOrEqual($element->line, $element->endLine, $label);
        }

        if ($element->startFilePos !== null && $element->endFilePos !== null) {
            static::assertGreaterThan($element->startFilePos, $element->endFilePos, $label);
        }
    }
}
