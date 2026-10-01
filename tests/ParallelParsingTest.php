<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use SplObjectStorage;
use voku\SimplePhpParser\Model\BasePHPElement;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class ParallelParsingTest extends TestCase
{
    public function testParallelParsingIsExplicitAndAstOnly(): void
    {
        static::assertTrue(ParserOptions::default()->reflectionEnrichment);
        static::assertFalse(ParserOptions::default()->parallelParsing);

        static::assertFalse(ParserOptions::astOnly()->reflectionEnrichment);
        static::assertFalse(ParserOptions::astOnly()->parallelParsing);

        static::assertFalse(ParserOptions::astOnlyParallel()->reflectionEnrichment);
        static::assertTrue(ParserOptions::astOnlyParallel()->parallelParsing);
    }

    public function testParallelAstOnlyParsingPreservesCrossFileSemantics(): void
    {
        if (!\function_exists('pcntl_fork')) {
            static::markTestSkipped('pcntl is required to exercise the parallel parser path.');
        }

        $directory = $this->createFixtureDirectory(false);

        try {
            $sequential = PhpCodeParser::getPhpFiles(
                $directory,
                options: ParserOptions::astOnly()
            );
            $parallel = PhpCodeParser::getPhpFiles(
                $directory,
                options: ParserOptions::astOnlyParallel()
            );

            static::assertSame([], $sequential->getParseErrors());
            static::assertSame($sequential->getParseErrors(), $parallel->getParseErrors());
            static::assertSame(
                \array_keys($sequential->getClasses()),
                \array_keys($parallel->getClasses())
            );
            static::assertSame(
                \array_keys($sequential->getInterfaces()),
                \array_keys($parallel->getInterfaces())
            );

            $sequentialChild = $sequential->getClasses()['ParallelFixture\\ChildThing'];
            $parallelChild = $parallel->getClasses()['ParallelFixture\\ChildThing'];

            static::assertSame(
                $sequentialChild->interfaces,
                $parallelChild->interfaces
            );
            static::assertSame(
                $sequentialChild->methods['value']->summary,
                $parallelChild->methods['value']->summary
            );
            static::assertSame(
                'string',
                $parallelChild->methods['value']->returnTypeFromPhpDoc
            );
            static::assertSame(
                $sequentialChild->methods['value']->returnTypeFromPhpDoc,
                $parallelChild->methods['value']->returnTypeFromPhpDoc
            );

            $this->assertContainerOwnership($parallel);
        } finally {
            $this->removeFixtureDirectory($directory);
        }
    }

    public function testParallelAstOnlyParsingPreservesParseErrors(): void
    {
        if (!\function_exists('pcntl_fork')) {
            static::markTestSkipped('pcntl is required to exercise the parallel parser path.');
        }

        $directory = $this->createFixtureDirectory(true);

        try {
            $sequential = PhpCodeParser::getPhpFiles(
                $directory,
                options: ParserOptions::astOnly()
            );
            $parallel = PhpCodeParser::getPhpFiles(
                $directory,
                options: ParserOptions::astOnlyParallel()
            );

            static::assertNotSame([], $sequential->getParseErrors());
            static::assertSame(
                $sequential->getParseErrors(),
                $parallel->getParseErrors()
            );
        } finally {
            $this->removeFixtureDirectory($directory);
        }
    }

    private function createFixtureDirectory(bool $withBrokenFile): string
    {
        $directory = \sys_get_temp_dir()
            . \DIRECTORY_SEPARATOR
            . 'simple-php-parser-parallel-test-'
            . \bin2hex(\random_bytes(8));

        static::assertTrue(\mkdir($directory, 0700));

        for ($index = 0; $index < 16; ++$index) {
            $file = $directory
                . \DIRECTORY_SEPARATOR
                . \sprintf('%02d-fixture.php', $index);

            if ($index === 0) {
                $code = <<<'PHP'
<?php

namespace ParallelFixture;

interface Contract
{
    /**
     * Contract value.
     *
     * @return string
     */
    public function value(): string;
}
PHP;
            } elseif ($index === 6) {
                $code = <<<'PHP'
<?php

namespace ParallelFixture;

class ParentThing implements Contract
{
    /**
     * Parent value.
     *
     * @return string
     */
    public function value(): string
    {
        return 'parent';
    }
}
PHP;
            } elseif ($index === 11) {
                $code = <<<'PHP'
<?php

namespace ParallelFixture;

class ChildThing extends ParentThing
{
    /** {@inheritdoc} */
    public function value(): string
    {
        return parent::value();
    }
}
PHP;
            } elseif ($withBrokenFile && $index === 15) {
                $code = <<<'PHP'
<?php

namespace ParallelFixture;

class BrokenFixture
{
    public function broken(]
    {
    }
}
PHP;
            } else {
                $className = 'Filler' . $index;
                $code = "<?php\n\nnamespace ParallelFixture;\n\nfinal class {$className}\n{\n    public function value(): int\n    {\n        return {$index};\n    }\n}\n";
            }

            static::assertNotFalse(\file_put_contents($file, $code));
        }

        return $directory;
    }

    private function removeFixtureDirectory(string $directory): void
    {
        $files = \glob($directory . \DIRECTORY_SEPARATOR . '*.php');
        if (\is_array($files)) {
            foreach ($files as $file) {
                if (\is_file($file)) {
                    \unlink($file);
                }
            }
        }

        if (\is_dir($directory)) {
            \rmdir($directory);
        }
    }

    private function assertContainerOwnership(ParserContainer $container): void
    {
        $seen = new SplObjectStorage();

        foreach ([
            $container->getTraits(),
            $container->getClasses(),
            $container->getInterfaces(),
            $container->getEnums(),
            $container->getConstants(),
            $container->getFunctions(),
        ] as $models) {
            $this->assertContainerOwnershipForValue($models, $container, $seen);
        }
    }

    /**
     * @param mixed $value
     * @param SplObjectStorage<BasePHPElement, null> $seen
     */
    private function assertContainerOwnershipForValue(
        $value,
        ParserContainer $container,
        SplObjectStorage $seen
    ): void {
        if (\is_array($value)) {
            foreach ($value as $item) {
                $this->assertContainerOwnershipForValue($item, $container, $seen);
            }

            return;
        }

        if (!$value instanceof BasePHPElement || $seen->contains($value)) {
            return;
        }

        $seen->attach($value);
        static::assertSame($container, $value->parserContainer, $value::class);

        foreach (\get_object_vars($value) as $property => $propertyValue) {
            if ($property === 'parserContainer') {
                continue;
            }

            $this->assertContainerOwnershipForValue(
                $propertyValue,
                $container,
                $seen
            );
        }
    }
}
