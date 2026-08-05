<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Model\PHPEnum;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class PHPEnumRegressionTest extends TestCase
{
    public function testEnumCasesDoNotLeakIntoConstantsDuringReflectionAugmentation(): void
    {
        $enumClass = __NAMESPACE__ . '\\EnumReflectionFixture';
        $fixture = __DIR__ . '/EnumReflectionFixture.php';

        require_once $fixture;

        $enum = PhpCodeParser::getPhpFiles($fixture)->getEnums()[$enumClass];

        static::assertSame('ready', $enum->cases['Ready']);
        static::assertArrayHasKey('Ready', $enum->caseDetails);
        static::assertArrayNotHasKey('Ready', $enum->constants);
        static::assertArrayHasKey('LABEL', $enum->constants);
        static::assertSame('label', $enum->constants['LABEL']->value);
    }

    public function testEnumSemanticsAreAvailableWithoutReflection(): void
    {
        $source = <<<'PHP'
<?php

namespace UnloadedEnumModel;

enum State
{
    case Ready;
}
PHP;

        $enum = PhpCodeParser::getFromString($source)->getEnums()['UnloadedEnumModel\\State'];

        static::assertTrue($enum->is_final);
        static::assertFalse($enum->is_abstract);
        static::assertFalse($enum->is_anonymous);
    }

    public function testBackedStringEnumIsIdenticalAcrossAllReadPaths(): void
    {
        $this->assertEnumIsIdenticalAcrossAllReadPaths(
            DummyEnum::class,
            __DIR__ . '/DummyEnum.php',
            'string',
            ['Hearts' => 'H', 'Diamonds' => 'D', 'Clubs' => 'C', 'Spades' => 'S'],
            []
        );
    }

    public function testBackedIntEnumIsIdenticalAcrossAllReadPaths(): void
    {
        $this->assertEnumIsIdenticalAcrossAllReadPaths(
            DummyEnumInt::class,
            __DIR__ . '/DummyEnumInt.php',
            'int',
            ['Low' => 1, 'Medium' => 2, 'High' => 3],
            []
        );
    }

    public function testUnitEnumIsIdenticalAcrossAllReadPaths(): void
    {
        $this->assertEnumIsIdenticalAcrossAllReadPaths(
            DummyEnumUnit::class,
            __DIR__ . '/DummyEnumUnit.php',
            null,
            ['Pending' => null, 'Active' => null, 'Closed' => null],
            []
        );
    }

    public function testEnumWithConstantIsIdenticalAcrossAllReadPaths(): void
    {
        $this->assertEnumIsIdenticalAcrossAllReadPaths(
            EnumReflectionFixture::class,
            __DIR__ . '/EnumReflectionFixture.php',
            'string',
            ['Ready' => 'ready'],
            ['LABEL']
        );
    }

    public function testLoadedEnumsExposeTheirImplicitInterfaces(): void
    {
        $backed = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnum.php')->getEnums()[DummyEnum::class];
        static::assertContains(\UnitEnum::class, $backed->interfaces);
        static::assertContains(\BackedEnum::class, $backed->interfaces);

        $unit = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnumUnit.php')->getEnums()[DummyEnumUnit::class];
        static::assertContains(\UnitEnum::class, $unit->interfaces);
        static::assertNotContains(\BackedEnum::class, $unit->interfaces);
    }

    public function testLoadedEnumsKeepTheirOwnMethods(): void
    {
        $enum = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnum.php')->getEnums()[DummyEnum::class];

        static::assertArrayHasKey('color', $enum->methods);
        static::assertSame('string', $enum->methods['color']->returnType);

        // the implicit static enum api has to stay visible as well
        static::assertArrayHasKey('cases', $enum->methods);
        static::assertArrayHasKey('from', $enum->methods);
        static::assertArrayHasKey('tryFrom', $enum->methods);
    }

    /**
     * The very same shapes, but for enums that are not loadable at all, so only
     * the AST path can supply the data.
     */
    public function testUnloadedEnumsExposeTheSameModel(): void
    {
        $source = <<<'PHP'
<?php

namespace NotLoadedEnums;

interface HasLabel {}

enum Status: string implements HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    const DEFAULT_STATE = 'open';

    public function label(): string
    {
        return \ucfirst($this->value);
    }
}

enum Level: int
{
    case Low = 1;
}

enum Plain
{
    case A;
    case B;
}
PHP;

        $enums = PhpCodeParser::getFromString($source)->getEnums();

        $status = $enums['NotLoadedEnums\\Status'];
        static::assertSame('string', $status->scalarType);
        static::assertSame(['Open' => 'open', 'Closed' => 'closed'], $status->cases);
        static::assertSame(['Open', 'Closed'], \array_keys($status->caseDetails));
        static::assertSame(['DEFAULT_STATE'], \array_keys($status->constants));
        static::assertSame(['label'], \array_keys($status->methods));
        static::assertSame(['NotLoadedEnums\\HasLabel'], \array_values($status->interfaces));
        static::assertTrue($status->is_final);
        static::assertFalse($status->is_abstract);
        static::assertFalse($status->is_anonymous);

        $level = $enums['NotLoadedEnums\\Level'];
        static::assertSame('int', $level->scalarType);
        static::assertSame(['Low' => 1], $level->cases);
        static::assertSame([], $level->constants);

        $plain = $enums['NotLoadedEnums\\Plain'];
        static::assertNull($plain->scalarType);
        static::assertSame(['A' => null, 'B' => null], $plain->cases);
        static::assertSame(['A', 'B'], \array_keys($plain->caseDetails));
    }

    public function testEnumCaseDetailsCarrySourcePositions(): void
    {
        $enum = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnum.php')->getEnums()[DummyEnum::class];

        foreach ($enum->caseDetails as $caseName => $case) {
            static::assertNotNull($case->line, $caseName);
            static::assertGreaterThan(0, $case->line, $caseName);
            static::assertNotNull($case->startFilePos, $caseName);
            static::assertNotNull($case->endFilePos, $caseName);
            static::assertGreaterThan($case->startFilePos, $case->endFilePos, $caseName);
        }
    }

    public function testEnumCasesKeepAttributes(): void
    {
        $source = <<<'PHP'
<?php

namespace NotLoadedEnums\Attributed;

#[\Attribute]
class Label
{
    public function __construct(public string $text = '')
    {
    }
}

enum Status: string
{
    #[Label('open')]
    case Open = 'open';

    case Closed = 'closed';
}
PHP;

        $enum = PhpCodeParser::getFromString($source)->getEnums()['NotLoadedEnums\\Attributed\\Status'];

        static::assertCount(1, $enum->caseDetails['Open']->attributes);
        static::assertSame('NotLoadedEnums\\Attributed\\Label', $enum->caseDetails['Open']->attributes[0]->name);
        static::assertSame([], $enum->caseDetails['Closed']->attributes);
    }

    /**
     * Enum cases must never show up as constants, no matter which of the three
     * entry points produced the model.
     *
     * @param class-string<\UnitEnum>        $enumClass
     * @param array<string, string|int|null> $expectedCases
     * @param string[]                       $expectedConstants
     */
    private function assertEnumIsIdenticalAcrossAllReadPaths(
        string $enumClass,
        string $fixtureFile,
        ?string $expectedScalarType,
        array $expectedCases,
        array $expectedConstants
    ): void {
        require_once $fixtureFile;

        $enums = [
            'file'            => PhpCodeParser::getPhpFiles($fixtureFile)->getEnums()[$enumClass],
            'ReflectionClass' => (new PHPEnum(new ParserContainer()))->readObjectFromReflection(new \ReflectionClass($enumClass)),
            'ReflectionEnum'  => (new PHPEnum(new ParserContainer()))->readObjectFromReflection(new \ReflectionEnum($enumClass)),
        ];

        foreach ($enums as $label => $enum) {
            static::assertSame($enumClass, $enum->name, $label);
            static::assertSame($expectedScalarType, $enum->scalarType, $label);
            static::assertSame($expectedCases, $enum->cases, $label);
            static::assertSame(\array_keys($expectedCases), \array_keys($enum->caseDetails), $label);
            static::assertSame($expectedConstants, \array_keys($enum->constants), $label);

            // enums are implicitly final, never abstract and never anonymous
            static::assertTrue($enum->is_final, $label);
            static::assertFalse($enum->is_abstract, $label);
            static::assertFalse($enum->is_anonymous, $label);

            foreach ($expectedCases as $caseName => $caseValue) {
                static::assertArrayHasKey($caseName, $enum->caseDetails, $label);
                static::assertSame($caseName, $enum->caseDetails[$caseName]->name, $label);
                static::assertSame($caseValue, $enum->caseDetails[$caseName]->value, $label);
            }
        }
    }
}
