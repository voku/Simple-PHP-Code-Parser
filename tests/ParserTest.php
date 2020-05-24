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

    public function testSimpleDirectory(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/');

        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame(Dummy2::class, $phpClasses[Dummy2::class]->name);
        static::assertSame(DummyInterface::class, $phpClasses[Dummy2::class]->interfaces[0]);

        $phpInterfaces = $phpCode->getInterfaces();

        static::assertSame('array{parsedParamTagStr: string, variableName: array<array-key, null>|string}', $phpInterfaces[DummyInterface::class]->methods['withComplexReturnArray']->returnTypeFromPhpDocPslam);
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

    public function testGetMethodsInfoViaPhpReflectionOnly(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/Dummy.php',
            true
        );
        $phpClasses = $phpCode->getClasses();

        // DEBUG
        //\print_r($phpClasses);

        $props = $phpClasses[Dummy::class]->getPropertiesInfo();

        // DEBUG
        //\var_export($props);

        static::assertSame(
            [
                'foo' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'       => 'int',
                    'typeFromPhpDocSimple' => 'int',
                    'typeFromPhpDocPslam'  => 'int',
                    'typeFromDefaultValue' => null,
                ],
                'bar' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'       => 'string',
                    'typeFromPhpDocSimple' => 'string',
                    'typeFromPhpDocPslam'  => 'string',
                    'typeFromDefaultValue' => null,
                ],
                'lall1' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'null|int[]',
                    'typeFromPhpDoc'       => 'null|int[]',
                    'typeFromPhpDocSimple' => 'null|int[]',
                    'typeFromPhpDocPslam'  => 'array<int, int>|null',
                    'typeFromDefaultValue' => null,
                ],
                'lall2' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'float',
                    'typeFromPhpDoc'       => 'float',
                    'typeFromPhpDocSimple' => 'float',
                    'typeFromPhpDocPslam'  => 'float',
                    'typeFromDefaultValue' => null,
                ],
                'lall3' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'       => 'null|float',
                    'typeFromPhpDocSimple' => 'null|float',
                    'typeFromPhpDocPslam'  => 'float|null',
                    'typeFromDefaultValue' => null,
                ],
            ],
            $props
        );

        $result = $phpClasses[Dummy::class]->getMethodsInfo();

        // DEBUG
        //\var_export($result);

        static::assertSame(
            [
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                 => 'array',
                        'typeMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'       => 'array<int,int>',
                        'typeFromPhpDocSimple' => 'int[]',
                        'typeFromPhpDocPslam'  => 'array<int, int>',
                    ],
                    'line'          => 51,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'       => 'false|int',
                        'typeFromPhpDocSimple' => 'false|int',
                        'typeFromPhpDocPslam'  => 'false|int',
                    ],
                    'line'          => 59,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'bool',
                            'typeMaybeWithComment' => null,
                            'typeFromPhpDoc'       => null,
                            'typeFromPhpDocSimple' => null,
                            'typeFromPhpDocPslam'  => null,
                            'typeFromDefaultValue' => 'bool',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                        'typeFromPhpDoc'       => 'int[]|string[]|null',
                        'typeFromPhpDocSimple' => 'int[]|string[]|null',
                        'typeFromPhpDocPslam'  => 'list<int|string>|null',
                    ],
                    'line'          => 69,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'       => 'int[]|null',
                            'typeFromPhpDocSimple' => 'int[]|null',
                            'typeFromPhpDocPslam'  => 'list<int>|null',
                            'typeFromDefaultValue' => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => null,
                        'typeFromPhpDoc'       => null,
                        'typeFromPhpDocSimple' => null,
                        'typeFromPhpDocPslam'  => null,
                    ],
                    'line'          => 80,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => null,
                            'typeFromPhpDoc'       => null,
                            'typeFromPhpDocSimple' => null,
                            'typeFromPhpDocPslam'  => 'list<int>|null',
                            'typeFromDefaultValue' => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => null,
                        'typeFromPhpDoc'       => null,
                        'typeFromPhpDocSimple' => null,
                        'typeFromPhpDocPslam'  => null,
                    ],
                    'line'          => 90,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'       => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocPslam'  => 'phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue' => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                    'line'          => 104,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => null,
                            'typeFromPhpDoc'       => null,
                            'typeFromPhpDocSimple' => null,
                            'typeFromPhpDocPslam'  => null,
                            'typeFromDefaultValue' => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                    'line'          => 119,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'       => 'mixed',
                            'typeFromPhpDocSimple' => 'mixed',
                            'typeFromPhpDocPslam'  => 'mixed',
                            'typeFromDefaultValue' => 'array',
                        ],
                        'p2' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'       => 'mixed',
                            'typeFromPhpDocSimple' => 'mixed',
                            'typeFromPhpDocPslam'  => 'mixed',
                            'typeFromDefaultValue' => 'string',
                        ],
                        'p3' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'       => 'mixed',
                            'typeFromPhpDocSimple' => 'mixed',
                            'typeFromPhpDocPslam'  => 'mixed',
                            'typeFromDefaultValue' => 'int',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array<array-key, mixed>',
                    ],
                    'line'          => 134,
                    'error'         => '',
                    'is_deprecated' => false,
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

        // DEBUG
        //\var_export($props);

        static::assertSame(
            [
                'foo' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'       => 'int',
                    'typeFromPhpDocSimple' => 'int',
                    'typeFromPhpDocPslam'  => 'int',
                    'typeFromDefaultValue' => null,
                ],
                'bar' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'       => 'string',
                    'typeFromPhpDocSimple' => 'string',
                    'typeFromPhpDocPslam'  => 'string',
                    'typeFromDefaultValue' => null,
                ],
                'lall1' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'null|int[]',
                    'typeFromPhpDoc'       => 'null|int[]',
                    'typeFromPhpDocSimple' => 'null|int[]',
                    'typeFromPhpDocPslam'  => 'array<int, int>|null',
                    'typeFromDefaultValue' => 'array',
                ],
                'lall2' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'float',
                    'typeFromPhpDoc'       => 'float',
                    'typeFromPhpDocSimple' => 'float',
                    'typeFromPhpDocPslam'  => 'float',
                    'typeFromDefaultValue' => 'float',
                ],
                'lall3' => [
                    'type'                 => null,
                    'typeMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'       => 'null|float',
                    'typeFromPhpDocSimple' => 'null|float',
                    'typeFromPhpDocPslam'  => 'float|null',
                    'typeFromDefaultValue' => null,
                ],
            ],
            $props
        );

        $result = $phpClasses[Dummy::class]->getMethodsInfo();

        // DEBUG
        //\var_export($result);

        static::assertSame(
            [
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                 => 'array',
                        'typeMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'       => 'array<int,int>',
                        'typeFromPhpDocSimple' => 'int[]',
                        'typeFromPhpDocPslam'  => 'array<int, int>',
                    ],
                    'line'          => 51,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'       => 'false|int',
                        'typeFromPhpDocSimple' => 'false|int',
                        'typeFromPhpDocPslam'  => 'false|int',
                    ],
                    'line'          => 59,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'bool',
                            'typeMaybeWithComment' => null,
                            'typeFromPhpDoc'       => null,
                            'typeFromPhpDocSimple' => null,
                            'typeFromPhpDocPslam'  => null,
                            'typeFromDefaultValue' => 'string',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                        'typeFromPhpDoc'       => 'int[]|string[]|null',
                        'typeFromPhpDocSimple' => 'int[]|string[]|null',
                        'typeFromPhpDocPslam'  => 'list<int|string>|null',
                    ],
                    'line'          => 69,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'       => 'int[]|null',
                            'typeFromPhpDocSimple' => 'int[]|null',
                            'typeFromPhpDocPslam'  => 'array<array-key, int>|null',
                            'typeFromDefaultValue' => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => null,
                        'typeFromPhpDoc'       => null,
                        'typeFromPhpDocSimple' => null,
                        'typeFromPhpDocPslam'  => null,
                    ],
                    'line'          => 80,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => null,
                            'typeFromPhpDoc'       => null,
                            'typeFromPhpDocSimple' => null,
                            'typeFromPhpDocPslam'  => 'list<int>|null',
                            'typeFromDefaultValue' => 'array',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => null,
                        'typeFromPhpDoc'       => null,
                        'typeFromPhpDocSimple' => null,
                        'typeFromPhpDocPslam'  => null,
                    ],
                    'line'          => 90,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'       => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocPslam'  => 'phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue' => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                    'line'          => 104,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => null,
                            'typeFromPhpDoc'       => null,
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => null,
                            'typeFromDefaultValue' => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                    'line'          => 119,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'       => 'mixed',
                            'typeFromPhpDocSimple' => 'mixed',
                            'typeFromPhpDocPslam'  => 'mixed',
                            'typeFromDefaultValue' => null,
                        ],
                        'p2' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'       => 'mixed',
                            'typeFromPhpDocSimple' => 'mixed',
                            'typeFromPhpDocPslam'  => 'mixed',
                            'typeFromDefaultValue' => null,
                        ],
                        'p3' => [
                            'type'                 => null,
                            'typeMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'       => 'mixed',
                            'typeFromPhpDocSimple' => 'mixed',
                            'typeFromPhpDocPslam'  => 'mixed',
                            'typeFromDefaultValue' => null,
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => null,
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array<array-key, mixed>',
                    ],
                    'line'          => 134,
                    'error'         => '',
                    'is_deprecated' => false,
                    'is_meta'       => false,
                    'is_internal'   => false,
                    'is_removed'    => false,
                ],
            ],
            $result
        );
    }
}
