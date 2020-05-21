<?php

declare(strict_types=1);

namespace voku\tests;

use voku\SimplePhpParser\Parsers\PhpCodeChecker;

/**
 * @internal
 */
final class CheckerTest extends \PHPUnit\Framework\TestCase
{
    public function testCheckPhpClasses(): void
    {
        $phpCodeErrors = PhpCodeChecker::checkPhpFiles(__DIR__ . '/Dummy3.php');

        static::assertSame(
            [
                'missing return type for voku\tests\foo3()',
                'missing parameter type for voku\tests\Dummy3::lall() | parameter:foo',
                'missing return type for voku\tests\Dummy3::lall()',
                'missing property type for voku\tests\Dummy3->$foo',
            ],
            $phpCodeErrors
        );
    }

    public function testSimpleStringInput(): void
    {
        $code = '<?php
        namespace voku\tests;
        class SimpleClass {
            public $foo;
            public int $foo1;
            private $foo2;
        }';

        $phpCodeErrors = PhpCodeChecker::checkFromString($code, ['public']);

        static::assertSame(
            [
                'missing property type for voku\tests\SimpleClass->$foo',
            ],
            $phpCodeErrors
        );
    }
}
