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
            // Referenced classes intentionally do not exist: AST-only extraction must not autoload them.
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

        $options = $attribute->arguments['options'];
        static::assertIsArray($options);
        /** @var array<string, mixed> $options */
        static::assertSame('Foo', $options['literal']);

        $arrayRule = $options['rule'];
        static::assertInstanceOf(PHPAttributeExpression::class, $arrayRule);
        static::assertSame('\\AttributeEvidence\\ArchitectureRules::Bar', $arrayRule->expression);
        static::assertSame(['enabled' => true], $options['nested']);

        static::assertNotContains('AttributeEvidence\\Rule', $autoloaded);
        static::assertNotContains('AttributeEvidence\\Target', $autoloaded);
        static::assertNotContains('AttributeEvidence\\ArchitectureRules', $autoloaded);
        static::assertNotContains('AttributeEvidence\\Example', $autoloaded);
    }

    public function testParserModesKeepTheirClassConstantBoundary(): void
    {
        $source = <<<'PHP'
<?php

namespace AttributeEvidence;

#[Rule(\voku\tests\AttributeArgumentKnownConstants::VALUE)]
final class Example
{
}
PHP;

        $defaultContainer = PhpCodeParser::getFromString(
            $source,
            [],
            ParserOptions::default()
        );
        static::assertSame(
            'resolved-value',
            $defaultContainer->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments[0]
        );

        $astOnlyContainer = PhpCodeParser::getFromString(
            $source,
            [],
            ParserOptions::astOnly()
        );
        $astOnlyValue = $astOnlyContainer->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments[0];

        static::assertInstanceOf(PHPAttributeExpression::class, $astOnlyValue);
        static::assertSame(
            '\\voku\\tests\\AttributeArgumentKnownConstants::VALUE',
            $astOnlyValue->expression
        );
    }

    public function testAstOnlyPreservesCompositeExpressionsWithUnresolvedOperands(): void
    {
        $container = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace AttributeEvidence;

#[Rule(
    negated: !ArchitectureRules::Foo,
    ternary: ArchitectureRules::Foo ? 'yes' : 'no',
    arithmetic: 1 + 1,
    nested: ['negated' => !ArchitectureRules::Bar],
)]
final class Example
{
}
PHP,
            [],
            ParserOptions::astOnly()
        );

        $arguments = $container->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments;

        $negated = $arguments['negated'];
        static::assertInstanceOf(PHPAttributeExpression::class, $negated);
        static::assertSame('!\\AttributeEvidence\\ArchitectureRules::Foo', $negated->expression);

        $ternary = $arguments['ternary'];
        static::assertInstanceOf(PHPAttributeExpression::class, $ternary);
        static::assertSame(
            "\\AttributeEvidence\\ArchitectureRules::Foo ? 'yes' : 'no'",
            $ternary->expression
        );

        static::assertSame(2, $arguments['arithmetic']);

        $nested = $arguments['nested'];
        static::assertIsArray($nested);
        $nestedNegated = $nested['negated'];
        static::assertInstanceOf(PHPAttributeExpression::class, $nestedNegated);
        static::assertSame('!\\AttributeEvidence\\ArchitectureRules::Bar', $nestedNegated->expression);
    }

    public function testAstOnlyDoesNotResolveRuntimeGlobalConstants(): void
    {
        if (!\defined('ATTRIBUTE_EVIDENCE_RUNTIME_VALUE')) {
            \define('ATTRIBUTE_EVIDENCE_RUNTIME_VALUE', 'runtime-value');
        }

        $source = <<<'PHP'
<?php

namespace AttributeEvidence;

#[Rule(\ATTRIBUTE_EVIDENCE_RUNTIME_VALUE)]
final class Example
{
}
PHP;

        $defaultValue = PhpCodeParser::getFromString(
            $source,
            [],
            ParserOptions::default()
        )->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments[0];

        static::assertSame('runtime-value', $defaultValue);

        $astOnlyValue = PhpCodeParser::getFromString(
            $source,
            [],
            ParserOptions::astOnly()
        )->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments[0];

        static::assertInstanceOf(PHPAttributeExpression::class, $astOnlyValue);
        static::assertSame('\\ATTRIBUTE_EVIDENCE_RUNTIME_VALUE', $astOnlyValue->expression);
    }

    public function testDynamicClassConstantExpressionIsPreservedWithoutAssertion(): void
    {
        $container = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace AttributeEvidence;

#[Rule($className::Foo)]
final class Example
{
}
PHP,
            [],
            ParserOptions::astOnly()
        );

        $argument = $container->getClasses()['AttributeEvidence\\Example']->attributes[0]->arguments[0];

        static::assertInstanceOf(PHPAttributeExpression::class, $argument);
        static::assertSame('$className::Foo', $argument->expression);
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
        $classConstant = $arguments[1];
        static::assertInstanceOf(PHPAttributeExpression::class, $classConstant);
        static::assertSame('\\AttributeEvidence\\ArchitectureRules::Foo', $classConstant->expression);
    }
}


final class AttributeArgumentKnownConstants
{
    public const VALUE = 'resolved-value';
}
