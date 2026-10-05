<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/** @internal */
final class OwnedSourceRangeTest extends TestCase
{
    private const SOURCE = <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo;

/**
 * Class docs.
 */
#[\Attribute]
#[\AllowDynamicProperties]
final class Subject
{
    /** Constant docs. */
    #[\Deprecated]
    private const SOLO = 1;

    private const A = 1, B = 2;

    /** Property docs. */
    #[\Deprecated]
    private int $solo = 0;

    public int $x = 0, $y = 0;

    private function bare(): void
    {
    }

    /**
     * Method docs.
     */
    private function documented(): void
    {
    }

    #[\Deprecated]
    #[\NoDiscard]
    private function attributed(): void
    {
    }

    /**
     * Method docs.
     */
    #[\Deprecated]
    private function both(): void
    {
    }

    #[\Deprecated]
    /** Docs after the attribute. */
    private function attributeThenDocs(): void
    {
    }
}

/** Function docs. */
#[\Deprecated]
function helper(): void
{
}
PHP;

    public function testAMethodWithoutMetadataOwnsExactlyItsNodeRange(): void
    {
        $method = $this->subject()->methods['bare'];

        static::assertSame($method->startFilePos, $method->sourceStartFilePos);
        static::assertSame($method->endFilePos, $method->sourceEndFilePos);
        static::assertSame($method->line, $method->sourceStartLine);
        static::assertSame($method->endLine, $method->sourceEndLine);
    }

    public function testAPhpDocCommentIsOwnedButTheNodeRangeIsUnchanged(): void
    {
        foreach ([
            'documented' => "/**\n     * Method docs.",
            'both' => "/**\n     * Method docs.\n     */\n    #[\\Deprecated]",
        ] as $name => $expectedPrefix) {
            $method = $this->subject()->methods[$name];

            static::assertNotNull($method->sourceStartFilePos, $name);
            static::assertNotNull($method->startFilePos, $name);
            static::assertLessThan($method->startFilePos, $method->sourceStartFilePos, $name);
            static::assertSame($method->endFilePos, $method->sourceEndFilePos, $name);
            static::assertStringStartsWith($expectedPrefix, $this->slice($method->sourceStartFilePos, $method->sourceEndFilePos), $name);
            static::assertLessThan($method->line, $method->sourceStartLine, $name);
            static::assertSame($method->endLine, $method->sourceEndLine, $name);
        }

        $documented = $this->subject()->methods['documented'];
        static::assertStringStartsWith('private function documented', $this->slice($documented->startFilePos, $documented->endFilePos));
    }

    public function testPhpAttributesAreAlreadyInsideTheNodeRange(): void
    {
        // Pins the php-parser behavior the owned range builds on: only the PHPDoc comment lies outside the node.
        foreach (['attributed', 'attributeThenDocs'] as $name) {
            $method = $this->subject()->methods[$name];

            static::assertSame($method->startFilePos, $method->sourceStartFilePos, $name);
            static::assertSame($method->line, $method->sourceStartLine, $name);
            static::assertStringStartsWith('#[\\Deprecated]', $this->slice($method->sourceStartFilePos, $method->sourceEndFilePos), $name);
        }
    }

    public function testAClassOwnsItsPhpDocInFrontOfItsAttributes(): void
    {
        $class = $this->subject();

        static::assertNotNull($class->sourceStartFilePos);
        static::assertStringStartsWith("/**\n * Class docs.\n */\n#[\\Attribute]\n#[\\AllowDynamicProperties]\nfinal class Subject", $this->slice($class->sourceStartFilePos, $class->sourceEndFilePos));
        static::assertStringStartsWith('#[\\Attribute]', $this->slice($class->startFilePos, $class->endFilePos));
        static::assertSame(7, $class->sourceStartLine);
    }

    public function testAFunctionOwnsItsPhpDocInFrontOfItsAttributes(): void
    {
        $function = PhpCodeParser::getFromString(self::SOURCE)->getFunctions()['Demo\\helper'];

        static::assertNotNull($function->sourceStartFilePos);
        static::assertStringStartsWith("/** Function docs. */\n#[\\Deprecated]\nfunction helper", $this->slice($function->sourceStartFilePos, $function->sourceEndFilePos));
        static::assertStringStartsWith('#[\\Deprecated]', $this->slice($function->startFilePos, $function->endFilePos));
    }

    public function testASingleConstantAndASinglePropertyOwnTheirWholeStatement(): void
    {
        $subject = $this->subject();
        $constant = $subject->constants['SOLO'];
        $property = $subject->properties['solo'];

        static::assertNotNull($constant->sourceStartFilePos);
        static::assertSame("/** Constant docs. */\n    #[\\Deprecated]\n    private const SOLO = 1;", $this->slice($constant->sourceStartFilePos, $constant->sourceEndFilePos));
        static::assertNotNull($property->sourceStartFilePos);
        static::assertSame("/** Property docs. */\n    #[\\Deprecated]\n    private int \$solo = 0;", $this->slice($property->sourceStartFilePos, $property->sourceEndFilePos));
    }

    public function testItemsOfAMultiItemStatementOwnNoDeclarationRange(): void
    {
        $subject = $this->subject();

        foreach ([$subject->constants['A'], $subject->constants['B'], $subject->properties['x'], $subject->properties['y']] as $element) {
            static::assertNotNull($element->startFilePos, $element->name);
            static::assertNull($element->sourceStartFilePos, $element->name);
            static::assertNull($element->sourceEndFilePos, $element->name);
            static::assertNull($element->sourceStartLine, $element->name);
            static::assertNull($element->sourceEndLine, $element->name);
        }
    }

    private function subject(): \voku\SimplePhpParser\Model\PHPClass
    {
        return PhpCodeParser::getFromString(self::SOURCE)->getClasses()['Demo\\Subject'];
    }

    private function slice(?int $start, ?int $end): string
    {
        static::assertNotNull($start);
        static::assertNotNull($end);

        return \substr(self::SOURCE, $start, $end - $start + 1);
    }
}
