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

        $phpCodeErrors = ParserTest::removeLocalPathForTheTest($phpCodeErrors);

        static::assertSame(
            [
                'Simple-PHP-Code-Parser/tests/Dummy3.php' => [
                    0 => '[7]: missing return type for voku\tests\foo3()',
                    1 => '[15]: missing property type for voku\tests\Dummy3->$foo',
                    2 => '[15]: missing property type for voku\tests\Dummy3->$foo_mixed',
                    3 => '[150]: missing parameter type "null" in phpdoc from voku\tests\Dummy3->lall8() | parameter:case',
                    4 => '[40]: missing parameter type for voku\tests\Dummy3->lall() | parameter:foo',
                    5 => '[40]: missing return type for voku\tests\Dummy3->lall()',
                    6 => '[70]: wrong return type "string" in phpdoc from voku\tests\Dummy3->lall3()',
                    7 => '[60]: wrong return type "null" in phpdoc from voku\tests\Dummy3->lall2_1()',
                    8 => '[50]: missing return type "null" in phpdoc from voku\tests\Dummy3->lall2()',
                    9 => '[80]: wrong parameter type "string" in phpdoc from voku\tests\Dummy3->lall3_1()  | parameter:foo',
                ],
            ],
            $phpCodeErrors
        );

        // --------------------------

        if (\PHP_VERSION_ID >= 70400) {
            $phpCodeErrors = PhpCodeChecker::checkPhpFiles(__DIR__ . '/Dummy5.php');

            $phpCodeErrors = ParserTest::removeLocalPathForTheTest($phpCodeErrors);

            static::assertSame(
                [
                    'Simple-PHP-Code-Parser/tests/Dummy5.php' => [
                        0 => '[12]: missing property type for voku\tests\Dummy5->$foo',
                        1 => '[12]: missing property type for voku\tests\Dummy5->$foo_mixed',
                        2 => '[12]: missing property type "int" in phpdoc from voku\tests\Dummy5 | property:foo_int_4',
                        3 => '[12]: wrong property type "string" in phpdoc from voku\tests\Dummy5  | property:foo_int_4',
                        4 => '[12]: missing property type "null" in phpdoc from voku\tests\Dummy5 | property:foo_int_6',
                        5 => '[12]: wrong property type "null" in phpdoc from voku\tests\Dummy5  | property:foo_int_7',
                    ],
                ],
                $phpCodeErrors
            );
        }

        // --------------------------

        $phpCodeErrors = PhpCodeChecker::checkPhpFiles(__DIR__ . '/Dummy7.php');

        $phpCodeErrors = ParserTest::removeLocalPathForTheTest($phpCodeErrors);

        static::assertSame([], $phpCodeErrors);
    }

    public function testSimpleStringInput(): void
    {
        $code = '<?php
        namespace voku\tests;
        class SimpleClass {
            public $foo;
            public int $foo1;
            private $foo2;
            
            /** @var mixed */
            public $foo3;
        }';

        $phpCodeErrors = PhpCodeChecker::checkFromString($code, ['public']);

        static::assertSame(
            [
                '' => [
                    '[3]: missing property type for voku\tests\SimpleClass->$foo',
                    '[3]: missing property type for voku\tests\SimpleClass->$foo3',
                ],
            ],
            $phpCodeErrors
        );
    }

    public function testSimpleStringInputInheritdocExtended(): void
    {
        $code = '<?php
        namespace voku\tests;
        class SimpleClassV1 {
            /**
             * @param string $foo
             */
            public function lall($foo): array
            {
               return [];
            }
        }
        class SimpleClassV2 extends SimpleClassV1 {
            /**
             * {@inheritdoc}
             */
            public function lall($foo): array
            {
               return [];
            }
        }';

        $phpCodeErrors = PhpCodeChecker::checkFromString($code, ['public'], false);

        static::assertSame(
            [],
            $phpCodeErrors
        );
    }

    public function testSimpleStringInputInheritdoc(): void
    {
        $code = '<?php
        namespace voku\tests;
        interface SimpleInterface {
            /**
             * @param string $foo
             */
            public function lall($foo)
        }
        class SimpleClass implements SimpleInterface {
            /**
             * {@inheritdoc}
             */
            public function lall($foo): array
            {
               return [];
            }
        }';

        $phpCodeErrors = PhpCodeChecker::checkFromString($code, ['public'], false);

        static::assertSame(
            [],
            $phpCodeErrors
        );
    }

    public function testSimpleStringInputWithMixed(): void
    {
        $code = '<?php
        namespace voku\tests;
        class SimpleClass {
            public $foo;
            public int $foo1;
            private $foo2;
            
            /** @var mixed */
            public $foo3;
        }';

        $phpCodeErrors = PhpCodeChecker::checkFromString($code, ['public'], true);

        static::assertSame(
            [
                '' => [
                    '[3]: missing property type for voku\tests\SimpleClass->$foo',
                ],
            ],
            $phpCodeErrors
        );
    }
}
