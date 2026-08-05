<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class AutoloadSafetyRegressionTest extends TestCase
{
    public function testUnsupportedTraitSyntaxDoesNotTriggerAutoload(): void
    {
        if (\PHP_VERSION_ID >= 80300) {
            static::markTestSkipped('No unsupported trait fixture for this runtime.');
        }

        if (\PHP_VERSION_ID < 80200) {
            $traitName = 'AutoloadSafety\\DnfTrait';
            $source = <<<'PHP'
<?php

namespace AutoloadSafety;

trait DnfTrait
{
    public function handle((FirstContract&SecondContract)|FallbackContract $value): void
    {
    }
}
PHP;
        } else {
            $traitName = 'AutoloadSafety\\TypedConstantTrait';
            $source = <<<'PHP'
<?php

namespace AutoloadSafety;

trait TypedConstantTrait
{
    public const string NAME = 'value';
}
PHP;
        }

        $traitAutoloaded = false;
        $autoloader = static function (string $className) use ($traitName, &$traitAutoloaded): void {
            if ($className === $traitName) {
                $traitAutoloaded = true;
            }
        };

        \spl_autoload_register($autoloader, true, true);
        try {
            $container = PhpCodeParser::getFromString($source);
        } finally {
            \spl_autoload_unregister($autoloader);
        }

        static::assertArrayHasKey($traitName, $container->getTraits());
        static::assertFalse($traitAutoloaded);
    }

    /**
     * Traits with constants are PHP 8.2+ syntax, so older runtimes may not
     * autoload them -- but the constants have to be readable from the AST on
     * every supported runtime.
     */
    public function testTraitConstantsAreReadOnEveryRuntime(): void
    {
        $source = <<<'PHP'
<?php

namespace AutoloadSafety\Constants;

trait WithConstants
{
    public const NAME = 'value';

    public const OTHER = 2;

    public function handle(): void
    {
    }
}
PHP;

        $trait = PhpCodeParser::getFromString($source)->getTraits()['AutoloadSafety\\Constants\\WithConstants'];

        static::assertSame(['NAME', 'OTHER'], \array_keys($trait->constants));
        static::assertSame('value', $trait->constants['NAME']->value);
        static::assertSame(2, $trait->constants['OTHER']->value);
        static::assertArrayHasKey('handle', $trait->methods);
    }

    /**
     * Enums go through the very same autoload guard as classes, interfaces and
     * traits, so syntax the current runtime cannot compile must not be loaded.
     */
    public function testUnsupportedEnumSyntaxDoesNotTriggerAutoload(): void
    {
        if (\PHP_VERSION_ID >= 80300) {
            static::markTestSkipped('typed constants are compilable on this runtime.');
        }

        $enumName = 'AutoloadSafety\\TypedConstantEnum';
        $source = <<<'PHP'
<?php

namespace AutoloadSafety;

enum TypedConstantEnum: string
{
    case Ready = 'ready';

    public const string LABEL = 'label';
}
PHP;

        $enumAutoloaded = false;
        $autoloader = static function (string $className) use ($enumName, &$enumAutoloaded): void {
            if ($className === $enumName) {
                $enumAutoloaded = true;
            }
        };

        \spl_autoload_register($autoloader, true, true);
        try {
            $container = PhpCodeParser::getFromString($source);
        } finally {
            \spl_autoload_unregister($autoloader);
        }

        $enum = $container->getEnums()[$enumName];

        static::assertFalse($enumAutoloaded);
        static::assertSame(['Ready' => 'ready'], $enum->cases);
        static::assertSame(['LABEL'], \array_keys($enum->constants));
    }

    public function testReflectionTypeFormattingDoesNotTriggerAutoload(): void
    {
        $fixtureClass = __NAMESPACE__ . '\\ReflectionTypeAutoloadFixture';
        $missingType = __NAMESPACE__ . '\\MissingReflectionType';
        $fixture = __DIR__ . '/ReflectionTypeAutoloadFixture.php';

        require_once $fixture;

        $missingTypeAutoloaded = false;
        $autoloader = static function (string $className) use ($missingType, &$missingTypeAutoloaded): void {
            if ($className === $missingType) {
                $missingTypeAutoloaded = true;
            }
        };

        \spl_autoload_register($autoloader, true, true);
        try {
            $class = PhpCodeParser::getPhpFiles($fixture)->getClasses()[$fixtureClass];
        } finally {
            \spl_autoload_unregister($autoloader);
        }

        static::assertFalse($missingTypeAutoloaded);
        static::assertSame('\\' . $missingType, $class->properties['property']->type);
        static::assertSame('\\' . $missingType, $class->methods['handle']->parameters['parameter']->type);
    }
}
