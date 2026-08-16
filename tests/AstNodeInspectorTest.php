<?php

declare(strict_types=1);

namespace voku\tests;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\AstNodeInspector;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/** @internal */
final class AstNodeInspectorTest extends TestCase
{
    public function testReadsSourceTextAndStartColumn(): void
    {
        $source = <<<'PHP'
<?php
function demo(): void
{
    $value = build_value(123);
}
PHP;
        $assign = (new NodeFinder())->findFirstInstanceOf(
            PhpCodeParser::getAstFromString($source),
            Assign::class
        );

        static::assertInstanceOf(Assign::class, $assign);
        static::assertSame('$value = build_value(123)', AstNodeInspector::sourceText($assign, $source));
        static::assertSame(5, AstNodeInspector::startColumn($assign, $source));
    }

    public function testShapeFingerprintIgnoresValuesButKeepsSyntax(): void
    {
        $first = <<<'PHP'
<?php
$left = build_value(123);
PHP;
        $sameShape = <<<'PHP'
<?php
$right = other_factory(999);
PHP;
        $differentShape = <<<'PHP'
<?php
$right = other_factory([999]);
PHP;

        $finder = new NodeFinder();
        $firstNode = $finder->findFirstInstanceOf(PhpCodeParser::getAstFromString($first), Expression::class);
        $sameShapeNode = $finder->findFirstInstanceOf(PhpCodeParser::getAstFromString($sameShape), Expression::class);
        $differentShapeNode = $finder->findFirstInstanceOf(PhpCodeParser::getAstFromString($differentShape), Expression::class);

        static::assertInstanceOf(Expression::class, $firstNode);
        static::assertInstanceOf(Expression::class, $sameShapeNode);
        static::assertInstanceOf(Expression::class, $differentShapeNode);

        $fingerprint = AstNodeInspector::shapeFingerprint($firstNode, 5);
        static::assertSame($fingerprint, AstNodeInspector::shapeFingerprint($sameShapeNode, 5));
        static::assertNotSame($fingerprint, AstNodeInspector::shapeFingerprint($differentShapeNode, 5));
    }
}
