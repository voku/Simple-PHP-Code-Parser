<?php

declare(strict_types=1);

namespace voku\tests;

use ErrorException;
use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class InheritdocWithoutParentRegressionTest extends TestCase
{
    public function testInheritdocMethodWithoutParentDoesNotUseNullAsArrayKey(): void
    {
        $source = <<<'PHP'
<?php

namespace NotLoadable\InheritdocWithoutParent;

final class Standalone
{
    /** @inheritdoc */
    public function execute(): void {}
}
PHP;

        \set_error_handler(
            static function (int $severity, string $message, string $file, int $line): bool {
                if (
                    $severity === \E_DEPRECATED
                    && \str_contains($message, 'Using null as an array offset is deprecated')
                ) {
                    throw new ErrorException($message, 0, $severity, $file, $line);
                }

                return false;
            }
        );

        try {
            $container = PhpCodeParser::getFromString($source);
        } finally {
            \restore_error_handler();
        }

        static::assertArrayHasKey(
            'NotLoadable\\InheritdocWithoutParent\\Standalone',
            $container->getClasses()
        );
    }
}
