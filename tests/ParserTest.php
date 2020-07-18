<?php

declare(strict_types=1);

namespace voku\tests;

use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * @internal
 */
final class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleOneClass(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame('withoutPhpDocParam', $phpClasses[Dummy::class]->methods['withoutPhpDocParam']->name);

        static::assertSame('bool', $phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']->type);
    }

    public function testSimpleOneClassV2(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy3.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy3::class, $phpClasses[Dummy3::class]->name);

        $withComplexReturnArray = $phpClasses[Dummy3::class]->methods['withComplexReturnArray'];

        static::assertSame('withComplexReturnArray', $withComplexReturnArray->name);
        static::assertSame('This is a test-text [...] öäü !"§?.', $withComplexReturnArray->summary . $withComplexReturnArray->description);

        $parsedParamTag = $withComplexReturnArray->parameters['parsedParamTag'];

        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $parsedParamTag->type);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag <p>this is a test-text [...] öäü !"§?</p>', $parsedParamTag->typeFromPhpDocMaybeWithComment);
    }

    public function testSimpleOneClassV3(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy7.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy7::class, $phpClasses[Dummy7::class]->name);

        $withComplexReturnArray = $phpClasses[Dummy7::class]->methods['getFieldArray'];

        static::assertSame('getFieldArray', $withComplexReturnArray->name);

        $parsedParamTag = $withComplexReturnArray->parameters['RowOffset'];

        static::assertSame('int', $parsedParamTag->typeFromPhpDoc);
        static::assertSame('int $RowOffset', $parsedParamTag->typeFromPhpDocMaybeWithComment);
    }

    public function testSimpleOneClassWithTrait(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy8.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy8::class, $phpClasses[Dummy8::class]->name);

        $method = $phpClasses[Dummy8::class]->methods['getLallTrait'];

        static::assertSame('getLallTrait', $method->name);
    }

    public function testSimpleOneTrait(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyTrait.php');
        $phpTraits = $phpCode->getTraits();

        static::assertSame(DummyTrait::class, $phpTraits[DummyTrait::class]->name);

        $method = $phpTraits[DummyTrait::class]->methods['getLallTrait'];

        static::assertSame('getLallTrait', $method->name);

        static::assertSame(
            [
                'getLallTrait' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [],
                    'returnTypes'     => [
                        'type'                           => 'float',
                        'typeFromPhpDocMaybeWithComment' => 'float',
                        'typeFromPhpDoc'                 => 'float',
                        'typeFromPhpDocSimple'           => 'float',
                        'typeFromPhpDocPslam'            => 'float',
                    ],
                    'line'          => 20,
                    'file'          => 'Simple-PHP-Code-Parser/tests/DummyTrait.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
            ],
            self::removeLocalPathForTheTest($phpTraits[DummyTrait::class]->getMethodsInfo())
        );

        static::assertSame(
            [
                'lall_trait' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|float|int',
                    'typeFromPhpDoc'                 => 'null|float|int',
                    'typeFromPhpDocSimple'           => 'null|float|int',
                    'typeFromPhpDocPslam'            => 'null|float|int',
                    'typeFromDefaultValue'           => null,
                ],
            ],
            self::removeLocalPathForTheTest($phpTraits[DummyTrait::class]->getPropertiesInfo())
        );
    }

    public function testSimpleDirectory(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/',
            [],
            ['/Dummy5/']
        );

        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame(Dummy2::class, $phpClasses[Dummy2::class]->name);
        static::assertSame(DummyInterface::class, $phpClasses[Dummy2::class]->interfaces[0]);

        $phpInterfaces = $phpCode->getInterfaces();

        static::assertSame('array{parsedParamTagStr: string, variableName: (null[]|string)}', $phpInterfaces[DummyInterface::class]->methods['withComplexReturnArray']->returnTypeFromPhpDocPslam);
    }

    public function testSimpleStringInputClasses(): void
    {
        $code = '<?php
        namespace voku\tests;
        class SimpleClass {}
        $obja = new class() {};
        $objb = new class {};
        class AnotherClass {}';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertCount(4, $phpClasses);
    }

    public function testSimpleStringInputConstants(): void
    {
        $code = '<?php
        define("FOO", 123);
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpConstants = $phpCode->getConstants();

        static::assertCount(1, $phpConstants);
        static::assertSame(123, $phpConstants['FOO']->value);

        // ---

        $code = '<?php
        namespace foo\bar;
        define("FOO_BAR", "Lall");
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpConstants = $phpCode->getConstants();

        static::assertCount(1, $phpConstants);
        static::assertSame('Lall', $phpConstants['\foo\bar\FOO_BAR']->value);
    }

    public function testSimpleBrokenPhpDocStringInput(): void
    {
        $code = '
        <?php
        /** 
         * @property $foo 
         */
        abstract class Foo { 
            /**
             * @psalm-return
             */
            public function foo() { return []; }
        }
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertSame('Foo', $phpClasses['Foo']->name);
    }

    public function testGetMethodsInfoFromExtendedClass(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/Dummy4.php'
        );
        $phpClasses = $phpCode->getClasses();

        $result = $phpClasses[Dummy4::class]->getMethodsInfo();

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //\var_export($result);

        static::assertSame(
            [
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'                 => 'false|int',
                        'typeFromPhpDocSimple'           => 'false|int',
                        'typeFromPhpDocPslam'            => 'false|int',
                    ],
                    'line'          => 59,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'array',
                        'typeFromPhpDocMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'                 => 'array<int,int>',
                        'typeFromPhpDocSimple'           => 'int[]',
                        'typeFromPhpDocPslam'            => 'array<int, int>',
                    ],
                    'line'          => 51,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'getLallTrait' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'float',
                        'typeFromPhpDocMaybeWithComment' => 'float',
                        'typeFromPhpDoc'                 => 'float',
                        'typeFromPhpDocSimple'           => 'float',
                        'typeFromPhpDocPslam'            => 'float',
                    ],
                    'line'          => 20,
                    'file'          => 'Simple-PHP-Code-Parser/tests/DummyTrait.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocPslam'            => null,
                            'typeFromDefaultValue'           => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'array',
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocPslam'            => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                    ],
                    'line'          => 119,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => true,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple'           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocPslam'            => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue'           => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'array',
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocPslam'            => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                    ],
                    'line'          => 104,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => true,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocPslam'            => '?list<int>',
                            'typeFromDefaultValue'           => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => null,
                        'typeFromPhpDocSimple'           => null,
                        'typeFromPhpDocPslam'            => null,
                    ],
                    'line'          => 90,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'                 => 'int[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|null',
                            'typeFromPhpDocPslam'            => 'int[]|null',
                            'typeFromDefaultValue'           => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => null,
                        'typeFromPhpDocSimple'           => null,
                        'typeFromPhpDocPslam'            => null,
                    ],
                    'line'          => 80,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => 'bool',
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocPslam'            => null,
                            'typeFromDefaultValue'           => 'bool',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                        'typeFromPhpDoc'                 => 'int[]|string[]|null',
                        'typeFromPhpDocSimple'           => 'int[]|string[]|null',
                        'typeFromPhpDocPslam'            => '?list<(int|string)>',
                    ],
                    'line'          => 69,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocPslam'            => 'mixed',
                            'typeFromDefaultValue'           => 'array',
                        ],
                        'p2' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocPslam'            => 'mixed',
                            'typeFromDefaultValue'           => 'string',
                        ],
                        'p3' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocPslam'            => 'mixed',
                            'typeFromDefaultValue'           => 'int',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'array',
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocPslam'            => 'array',
                    ],
                    'line'          => 134,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
            ],
            $result
        );
    }

    public function testGetMethodsInfo(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

        // DEBUG
        //\print_r($phpClasses);

        $props = $phpClasses[Dummy::class]->getPropertiesInfo();

        $props = self::removeLocalPathForTheTest($props);

        // DEBUG
        //\var_export($props);

        static::assertSame(
            [
                'lall1' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|int[]',
                    'typeFromPhpDoc'                 => 'null|int[]',
                    'typeFromPhpDocSimple'           => 'null|int[]',
                    'typeFromPhpDocPslam'            => 'null|array<int, int>',
                    'typeFromDefaultValue'           => 'array',
                ],
                'lall2' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'float',
                    'typeFromPhpDoc'                 => 'float',
                    'typeFromPhpDocSimple'           => 'float',
                    'typeFromPhpDocPslam'            => 'float',
                    'typeFromDefaultValue'           => 'float',
                ],
                'lall3' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'                 => 'null|float',
                    'typeFromPhpDocSimple'           => 'null|float',
                    'typeFromPhpDocPslam'            => 'null|float',
                    'typeFromDefaultValue'           => null,
                ],
                'foo' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'                 => 'int',
                    'typeFromPhpDocSimple'           => 'int',
                    'typeFromPhpDocPslam'            => 'int',
                    'typeFromDefaultValue'           => null,
                ],
                'bar' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'                 => 'string',
                    'typeFromPhpDocSimple'           => 'string',
                    'typeFromPhpDocPslam'            => 'string',
                    'typeFromDefaultValue'           => null,
                ],
            ],
            $props
        );

        $result = $phpClasses[Dummy::class]->getMethodsInfo();

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //\var_export($result);

        static::assertSame(
            [
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'                 => 'false|int',
                        'typeFromPhpDocSimple'           => 'false|int',
                        'typeFromPhpDocPslam'            => 'false|int',
                    ],
                    'line'          => 59,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'array',
                        'typeFromPhpDocMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'                 => 'array<int,int>',
                        'typeFromPhpDocSimple'           => 'int[]',
                        'typeFromPhpDocPslam'            => 'array<int, int>',
                    ],
                    'line'          => 51,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocPslam'            => null,
                            'typeFromDefaultValue'           => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'array',
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocPslam'            => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                    ],
                    'line'          => 119,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => true,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple'           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocPslam'            => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue'           => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'array',
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocPslam'            => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                    ],
                    'line'          => 104,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => true,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocPslam'            => '?list<int>',
                            'typeFromDefaultValue'           => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => null,
                        'typeFromPhpDocSimple'           => null,
                        'typeFromPhpDocPslam'            => null,
                    ],
                    'line'          => 90,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'                 => 'int[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|null',
                            'typeFromPhpDocPslam'            => 'int[]|null',
                            'typeFromDefaultValue'           => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => null,
                        'typeFromPhpDocSimple'           => null,
                        'typeFromPhpDocPslam'            => null,
                    ],
                    'line'          => 80,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => 'bool',
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocPslam'            => null,
                            'typeFromDefaultValue'           => 'bool',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                        'typeFromPhpDoc'                 => 'int[]|string[]|null',
                        'typeFromPhpDocSimple'           => 'int[]|string[]|null',
                        'typeFromPhpDocPslam'            => '?list<(int|string)>',
                    ],
                    'line'          => 69,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocPslam'            => 'mixed',
                            'typeFromDefaultValue'           => 'array',
                        ],
                        'p2' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocPslam'            => 'mixed',
                            'typeFromDefaultValue'           => 'string',
                        ],
                        'p3' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocPslam'            => 'mixed',
                            'typeFromDefaultValue'           => 'int',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'array',
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocPslam'            => 'array',
                    ],
                    'line'          => 134,
                    'file'          => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_static'     => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
            ],
            $result
        );
    }

    /**
     * @param array $result
     *
     * @return array
     */
    public static function removeLocalPathForTheTest(array $result): array
    {
        // hack for CI
        $pathReplace = \realpath(\getcwd() . '/../') . '/';
        if (!\is_array($result)) {
            return $result;
        }

        $helper = [];
        foreach ($result as $key => $value) {
            if (\is_string($key)) {
                $key = (string) \str_replace($pathReplace, '', $key);
            }

            if (\is_array($value)) {
                $helper[$key] = self::removeLocalPathForTheTest($value);
            } else {
                if (\is_string($value)) {
                    $helper[$key] = \str_replace($pathReplace, '', $value);
                } else {
                    $helper[$key] = $value;
                }
            }
        }

        return $helper;
    }
}
