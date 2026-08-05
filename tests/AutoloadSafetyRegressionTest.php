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
