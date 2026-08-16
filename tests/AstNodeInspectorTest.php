<?php

declare(strict_types=1);

namespace voku\tests;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\AstNodeInspector;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class AstNodeInspectorTest extends TestCase
{
    public function testReadsExactSourceTextAndOneBasedStartColumn(): void
    {
        $source = <<<'PHP'
<?php
function demo(): void
{
    $value = build_value(123);
}
PHP;
        $ast = PhpCodeParser::getAstFromString($source);
        $assign = (new NodeFinder())->findFirstInstanceOf($ast, Assign::class);

        static::assertInstanceOf(Assign::class, $assign);

        $inspector = new AstNodeInspector($source);
        static::assertSame('$value = build_value(123)', $inspector->sourceText($assign));
        static::assertSame(5, $inspector->startColumn($assign));
    }

    public function testShapeFingerprintIgnoresNamesAndLiteralValues(): void
    {
        $first = <<<'PHP'
<?php
$left = build_value(123);
PHP;
        $second = <<<'PHP'
<?php
$right = other_factory(999);
PHP;

        $firstStatement = (new NodeFinder())->findFirstInstanceOf(
            PhpCodeParser::getAstFromString($first),
            Expression::class
        );
        $secondStatement = (new NodeFinder())->findFirstInstanceOf(
            PhpCodeParser::getAstFromString($second),
            Expression::class
        );

        static::assertInstanceOf(Expression::class, $firstStatement);
        static::assertInstanceOf(Expression::class, $secondStatement);

        static::assertSame(
            (new AstNodeInspector($first))->shapeFingerprint($firstStatement, 5),
            (new AstNodeInspector($second))->shapeFingerprint($secondStatement, 5)
        );
    }

    public function testShapeFingerprintStillDistinguishesDifferentSyntax(): void
    {
        $scalar = <<<'PHP'
<?php
$value = build_value(123);
PHP;
        $array = <<<'PHP'
<?php
$value = build_value([123]);
PHP;

        $scalarStatement = (new NodeFinder())->findFirstInstanceOf(
            PhpCodeParser::getAstFromString($scalar),
            Expression::class
        );
        $arrayStatement = (new NodeFinder())->findFirstInstanceOf(
            PhpCodeParser::getAstFromString($array),
            Expression::class
        );

        static::assertInstanceOf(Expression::class, $scalarStatement);
        static::assertInstanceOf(Expression::class, $arrayStatement);

        static::assertNotSame(
            (new AstNodeInspector($scalar))->shapeFingerprint($scalarStatement, 5),
            (new AstNodeInspector($array))->shapeFingerprint($arrayStatement, 5)
        );
    }
}
