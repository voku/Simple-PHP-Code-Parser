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

        $autoloadedClasses = [];
        $autoloader = static function (string $className) use (&$autoloadedClasses): void {
            $autoloadedClasses[] = $className;
        };

        \spl_autoload_register($autoloader);
        try {
            $container = PhpCodeParser::getFromString($source);
        } finally {
            \spl_autoload_unregister($autoloader);
        }

        static::assertArrayHasKey($traitName, $container->getTraits());
        static::assertSame([], $autoloadedClasses);
    }

    public function testReflectionTypeFormattingDoesNotTriggerAutoload(): void
    {
        $fixtureClass = __NAMESPACE__ . '\\ReflectionTypeAutoloadFixture';
        $missingType = __NAMESPACE__ . '\\MissingReflectionType';
        $fixture = __DIR__ . '/fixtures/ReflectionTypeAutoloadFixture.php';

        require_once $fixture;

        $autoloadedClasses = [];
        $autoloader = static function (string $className) use (&$autoloadedClasses): void {
            $autoloadedClasses[] = $className;
        };

        \spl_autoload_register($autoloader);
        try {
            $class = PhpCodeParser::getPhpFiles($fixture)->getClasses()[$fixtureClass];
        } finally {
            \spl_autoload_unregister($autoloader);
        }

        static::assertNotContains($missingType, $autoloadedClasses);
        static::assertSame('\\' . $missingType, $class->properties['property']->type);
        static::assertSame('\\' . $missingType, $class->methods['handle']->parameters['parameter']->type);
    }
}
