<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Model\PHPAttributeExpression;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

final class AttributeArgumentExpressionTest extends TestCase
{
    public function testAstOnlyPreservesNestedAttributeExpressionIdentityWithoutAutoloading(): void
    {
        $code = <<<'PHP'
            <?php
            namespace voku\tests\AttributeArgumentFixture;

            class Rule {}

            #[Rule(
                'Foo',
                [
                    'literal' => 'Foo',
                    'enabled' => true,
                    'nested' => ['count' => 1 + 1],
                    'rule' => NotLoadable::VALUE,
                ],
            )]
            class Subject {}
            PHP;

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

        $attribute = $classes['voku\tests\AttributeArgumentFixture\Subject']->attributes[0];

        static::assertSame('voku\tests\AttributeArgumentFixture\Rule', $attribute->name);
        static::assertSame('Foo', $attribute->arguments[0]);
        static::assertSame('Foo', $attribute->arguments[1]['literal']);
        static::assertTrue($attribute->arguments[1]['enabled']);
        static::assertSame(['count' => 2], $attribute->arguments[1]['nested']);

        $rule = $attribute->arguments[1]['rule'];
        static::assertInstanceOf(PHPAttributeExpression::class, $rule);
        static::assertSame(
            '\\voku\\tests\\AttributeArgumentFixture\\NotLoadable::VALUE',
            $rule->expression
        );
        static::assertNotContains('voku\tests\AttributeArgumentFixture\NotLoadable', $loaded);
    }

    public function testDefaultModeKeepsLegacyRuntimeResolutionWhenItIsExplicitlyEnabled(): void
    {
        $code = <<<'PHP'
            <?php
            namespace voku\tests\AttributeArgumentFixture;

            #[\voku\tests\AttributeArgumentKnownValue(\voku\tests\AttributeArgumentKnownConstant::VALUE)]
            class Subject {}
            PHP;

        $classes = PhpCodeParser::getFromString($code)->getClasses();
        $attribute = $classes['voku\tests\AttributeArgumentFixture\Subject']->attributes[0];

        static::assertSame('resolved', $attribute->arguments[0]);
    }
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AttributeArgumentKnownValue
{
    public function __construct(public mixed $value)
    {
    }
}

final class AttributeArgumentKnownConstant
{
    public const VALUE = 'resolved';
}
