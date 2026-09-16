<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * Covers `ParserOptions::astOnly()`: the parse describes the source text and
 * nothing else.
 *
 * The default mode autoloads every parsed class-like and merges reflection over
 * the AST. That is how inherited members become visible - and how a single test
 * class pulls its whole vendor base hierarchy into the result. A consumer that
 * indexes declarations per file needs the opposite guarantee.
 *
 * @internal
 */
final class ParserOptionsAstOnlyTest extends TestCase
{
    public function testAstOnlyReportsOnlyTheClassDeclaredInTheSource(): void
    {
        $code = <<<'CODE'
            <?php
            namespace voku\tests\AstOnlyFixture;

            class Child extends \voku\tests\Dummy
            {
                public function ownMethod(): string
                {
                    return 'own';
                }
            }
            CODE;

        $astOnly = PhpCodeParser::getFromString($code, [], ParserOptions::astOnly());
        $classes = $astOnly->getClasses();

        static::assertSame(['voku\tests\AstOnlyFixture\Child'], \array_keys($classes));
        static::assertSame(['ownMethod'], \array_keys($classes['voku\tests\AstOnlyFixture\Child']->methods));
        static::assertSame('voku\tests\Dummy', $classes['voku\tests\AstOnlyFixture\Child']->parentClass);
    }

    public function testDefaultModeStillMergesTheReflectedParentIntoTheResult(): void
    {
        // Dummy4 extends Dummy, and both are autoloadable from this test suite.
        $code = \file_get_contents(__DIR__ . '/Dummy4.php');
        static::assertIsString($code);

        $default = PhpCodeParser::getFromString($code)->getClasses();
        $astOnly = PhpCodeParser::getFromString($code, [], ParserOptions::astOnly())->getClasses();

        static::assertArrayHasKey('voku\tests\Dummy4', $default);
        static::assertSame(['voku\tests\Dummy4'], \array_keys($astOnly));

        $defaultMethods = \array_keys($default['voku\tests\Dummy4']->methods);
        $astOnlyMethods = \array_keys($astOnly['voku\tests\Dummy4']->methods);

        // Reflection sees the inherited surface; the AST sees only what Dummy4 writes down.
        static::assertGreaterThan(\count($astOnlyMethods), \count($defaultMethods));
        foreach ($astOnlyMethods as $method) {
            static::assertContains($method, $defaultMethods);
        }
    }

    public function testAstOnlyNeverAutoloadsTheParsedCode(): void
    {
        $className = 'voku\tests\AstOnlyNeverAutoloaded';
        static::assertFalse(\class_exists($className, false));

        $loaded = [];
        $spy = static function (string $class) use (&$loaded): void {
            $loaded[] = $class;
        };
        \spl_autoload_register($spy, true, true);

        try {
            PhpCodeParser::getFromString(
                '<?php namespace voku\tests; class AstOnlyNeverAutoloaded { public function f(): void {} }',
                [],
                ParserOptions::astOnly()
            );
        } finally {
            \spl_autoload_unregister($spy);
        }

        static::assertNotContains($className, $loaded);
        static::assertFalse(\class_exists($className, false));
    }

    public function testAstOnlyLeavesAnExternalConstantDefaultUnresolvedInsteadOfAutoloading(): void
    {
        $code = <<<'CODE'
            <?php
            namespace voku\tests\AstOnlyFixture;

            class WithConstantDefault
            {
                public function __construct(
                    public int $limit = \voku\tests\AstOnlyFixture\NotLoadable::VALUE
                ) {
                }
            }
            CODE;

        $loaded = [];
        $spy = static function (string $class) use (&$loaded): void {
            $loaded[] = $class;
        };
        \spl_autoload_register($spy, true, true);

        try {
            $classes = PhpCodeParser::getFromString($code, [], ParserOptions::astOnly())->getClasses();
        } finally {
            \spl_autoload_unregister($spy);
        }

        static::assertArrayHasKey('voku\tests\AstOnlyFixture\WithConstantDefault', $classes);
        static::assertNotContains('voku\tests\AstOnlyFixture\NotLoadable', $loaded);
    }

    public function testInterfacesTraitsAndEnumsHonourAstOnlyToo(): void
    {
        $code = <<<'CODE'
            <?php
            namespace voku\tests\AstOnlyFixture;

            interface Contract extends \Countable {}
            trait Helper { public function help(): void {} }
            enum Colour: string { case Red = 'red'; }
            CODE;

        $container = PhpCodeParser::getFromString($code, [], ParserOptions::astOnly());

        static::assertSame(['voku\tests\AstOnlyFixture\Contract'], \array_keys($container->getInterfaces()));
        static::assertSame(['voku\tests\AstOnlyFixture\Helper'], \array_keys($container->getTraits()));
        static::assertSame(['voku\tests\AstOnlyFixture\Colour'], \array_keys($container->getEnums()));
        static::assertSame(['help'], \array_keys($container->getTraits()['voku\tests\AstOnlyFixture\Helper']->methods));
    }
}
