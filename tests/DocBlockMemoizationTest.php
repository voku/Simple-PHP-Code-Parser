<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\MemoizingDocBlockFactory;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

final class DocBlockMemoizationTest extends TestCase
{
    public function testSameTextAndContextReturnsSharedInstance(): void
    {
        $factory = MemoizingDocBlockFactory::createInstance();
        $doc = "/**\n * Summary.\n * @return int\n */";

        static::assertSame($factory->create($doc), $factory->create($doc));
    }

    public function testDifferentNamespaceContextIsNotShared(): void
    {
        $factory = MemoizingDocBlockFactory::createInstance();
        $doc = "/**\n * @return Foo\n */";

        $a = $factory->create($doc, new \phpDocumentor\Reflection\Types\Context('A'));
        $b = $factory->create($doc, new \phpDocumentor\Reflection\Types\Context('B'));

        static::assertNotSame($a, $b);
        static::assertSame('\\A\\Foo', (string) $a->getTagsByName('return')[0]->getType());
        static::assertSame('\\B\\Foo', (string) $b->getTagsByName('return')[0]->getType());
    }

    public function testDifferentTextIsNotShared(): void
    {
        $factory = MemoizingDocBlockFactory::createInstance();

        static::assertNotSame($factory->create('/** One. */'), $factory->create('/** Two. */'));
    }

    public function testDirectoryParseSeesEditsMadeWithinTheSameSecond(): void
    {
        $dir = \sys_get_temp_dir() . '/spcp-fresh-' . \bin2hex(\random_bytes(4));
        \mkdir($dir);
        $file = $dir . '/Fresh.php';

        try {
            \file_put_contents($file, '<?php class FreshOne {}');
            \touch($file, 1700000000);
            $first = PhpCodeParser::getFromDirectory($dir, [], [], [], ParserOptions::astOnly());

            \file_put_contents($file, '<?php class FreshTwo {}');
            \touch($file, 1700000000);
            $second = PhpCodeParser::getFromDirectory($dir, [], [], [], ParserOptions::astOnly());

            static::assertSame(['FreshOne'], \array_keys($first->getClasses()));
            static::assertSame(['FreshTwo'], \array_keys($second->getClasses()));
        } finally {
            @\unlink($file);
            @\rmdir($dir);
        }
    }
}
