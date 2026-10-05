<?php

declare(strict_types=1);

namespace voku\tests;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassMethod;
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

    public function testOwnedRangeStartsAtThePhpDocCommentAndKeepsTheNodeEnd(): void
    {
        $source = <<<'PHP'
<?php
final class Subject
{
    /**
     * Docs.
     */
    #[\Deprecated]
    private function documented(): void
    {
    }

    #[\Deprecated]
    private function attributesOnly(): void
    {
    }

    private function bare(): void
    {
    }
}
PHP;
        $methods = [];
        foreach ((new NodeFinder())->findInstanceOf(PhpCodeParser::getAstFromString($source), ClassMethod::class) as $method) {
            $methods[$method->name->toString()] = $method;
        }

        $documented = AstNodeInspector::ownedRange($methods['documented']);
        static::assertNotNull($documented);
        static::assertSame("/**\n     * Docs.\n     */\n    #[\\Deprecated]\n    private function documented(): void\n    {\n    }", \substr($source, $documented['startFilePos'], $documented['endFilePos'] - $documented['startFilePos'] + 1));
        static::assertSame(4, $documented['startLine']);
        static::assertSame($methods['documented']->getEndFilePos(), $documented['endFilePos']);

        // php-parser's node range already starts at the first attribute.
        $attributesOnly = AstNodeInspector::ownedRange($methods['attributesOnly']);
        static::assertNotNull($attributesOnly);
        static::assertSame($methods['attributesOnly']->getStartFilePos(), $attributesOnly['startFilePos']);
        static::assertSame($methods['attributesOnly']->getStartLine(), $attributesOnly['startLine']);

        $bare = AstNodeInspector::ownedRange($methods['bare']);
        static::assertNotNull($bare);
        static::assertSame($methods['bare']->getStartFilePos(), $bare['startFilePos']);
        static::assertSame($methods['bare']->getEndLine(), $bare['endLine']);
    }

    public function testOwnedRangeIsNullForANodeWithoutSourcePositions(): void
    {
        static::assertNull(AstNodeInspector::ownedRange(new ClassMethod('detached')));
    }
}
