<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class PHPEnumRegressionTest extends TestCase
{
    public function testEnumCasesDoNotLeakIntoConstantsDuringReflectionAugmentation(): void
    {
        $enumClass = __NAMESPACE__ . '\\EnumReflectionFixture';

        if (!\enum_exists($enumClass, false)) {
            eval(<<<'PHP'
namespace voku\tests;

enum EnumReflectionFixture: string
{
    case Ready = 'ready';

    public const LABEL = 'label';
}
PHP);
        }

        $source = <<<'PHP'
<?php

namespace voku\tests;

enum EnumReflectionFixture: string
{
    case Ready = 'ready';

    public const LABEL = 'label';
}
PHP;

        $enum = PhpCodeParser::getFromString($source)->getEnums()[$enumClass];

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
}
