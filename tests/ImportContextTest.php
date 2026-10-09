<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\ImportContext;

/** @internal */
final class ImportContextTest extends TestCase
{
    public function testResolvesWrittenClassNamesLikePhp(): void
    {
        $context = ImportContext::fromSource(<<<'PHP'
<?php

namespace App\Service;

use DateTimeImmutable;
use Lib\Util as Helper;
use Lib\{Alpha, Beta as B, function strlen};
use const PHP_EOL;

final class Demo
{
}
PHP);

        static::assertSame('App\Service', $context->namespace);
        static::assertSame('DateTimeImmutable', $context->resolveClassName('DateTimeImmutable'));
        static::assertSame('Lib\Util\Inner', $context->resolveClassName('Helper\Inner'));
        static::assertSame('Lib\Alpha', $context->resolveClassName('Alpha'));
        static::assertSame('Lib\Beta', $context->resolveClassName('b'));
        static::assertSame('App\Service\Local', $context->resolveClassName('Local'));
        static::assertSame('Global\Name', $context->resolveClassName('\Global\Name'));
        static::assertArrayNotHasKey('strlen', $context->classImports);
        static::assertArrayNotHasKey('php_eol', $context->classImports);
    }

    public function testGlobalNamespaceResolvesUnimportedNamesToThemselves(): void
    {
        $context = ImportContext::fromSource("<?php\n\nuse Lib\\Thing;\n\nfinal class Demo {}\n");

        static::assertSame('', $context->namespace);
        static::assertSame('Lib\Thing', $context->resolveClassName('Thing'));
        static::assertSame('Other', $context->resolveClassName('Other'));
    }
}
