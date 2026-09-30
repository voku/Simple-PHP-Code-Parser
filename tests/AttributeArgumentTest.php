<?php

declare(strict_types=1);

namespace voku\tests;

use voku\SimplePhpParser\Model\PHPAttributeExpression;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class AttributeArgumentTest extends \PHPUnit\Framework\TestCase
{
    public function testAstOnlyPreservesUnresolvedAttributeExpressionIdentity(): void
    {
        $autoloaded = [];
        $autoload = static function (string $className) use (&$autoloaded): void {
            $autoloaded[] = $className;
        };
        \spl_autoload_register($autoload);

        try {
            $container = PhpCodeParser::getFromString(
                <<<'PHP'
<?php

namespace AttributeEvidence;

#[Rule(
    literal: 'Foo',
    className: Target::class,
    rule: ArchitectureRules::Foo,
    options: [
        'literal' => 'Foo',
        'rule' => ArchitectureRules::Bar,
        'nested' => ['enabled' => true],
    ],
)]
final class Example
{
}
PHP,
                [],
                ParserOptions::astOnly()
            );
        } finally {
            \spl_autoload_unregister($autoload);
        }

        $class = $container->getClasses()['AttributeEvidence\\Example'];
        $attribute = $class->attributes[0];

        static::assertSame('AttributeEvidence\\Rule', $attribute->name);
        static::assertSame('Foo', $attribute->arguments['literal']);
        static::assertSame('\\AttributeEvidence\\Target', $attribute->arguments['className']);

        $rule = $attribute->arguments['rule'];
        static::assertInstanceOf(PHPAttributeExpression::class, $rule);
        static::assertSame('\\AttributeEvidence\\ArchitectureRules::Foo', $rule->expression);

        static::assertIsArray($attribute->arguments['options']);
        static::assertSame('Foo', $attribute->arguments['options']['literal']);

        $arrayRule = $attribute->arguments['options']['rule'];
        static::assertInstanceOf(PHPAttributeExpression::class, $arrayRule);
        static::assertSame('\\AttributeEvidence\\ArchitectureRules::Bar', $arrayRule->expression);
        static::assertSame(['enabled' => true], $attribute->arguments['options']['nested']);

        static::assertNotContains('AttributeEvidence\\Rule', $autoloaded);
        static::assertNotContains('AttributeEvidence\\Target', $autoloaded);
        static::assertNotContains('AttributeEvidence\\ArchitectureRules', $autoloaded);
        static::assertNotContains('AttributeEvidence\\Example', $autoloaded);
    }

    public function testLiteralStringAndClassConstantRemainDistinct(): void
    {
        $container = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace AttributeEvidence;

#[Rule('Foo', ArchitectureRules::Foo)]
final class Example
{
}
PHP,
            [],
            ParserOptions::astOnly()
        );

        $arguments = $container->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments;

        static::assertSame('Foo', $arguments[0]);
        static::assertInstanceOf(PHPAttributeExpression::class, $arguments[1]);
        static::assertSame('\\AttributeEvidence\\ArchitectureRules::Foo', $arguments[1]->expression);
    }
}
