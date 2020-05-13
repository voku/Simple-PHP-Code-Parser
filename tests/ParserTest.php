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

    public function testSimpleStringInput(): void
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
        static::assertContains('Empty type', $phpClasses['Foo']->parseError);
        static::assertContains('Empty type', $phpClasses['Foo']->methods['foo']->parseError);
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
                    'type'                 => '',
                    'typeMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'       => 'int',
                    'typeFromPhpDocSimple' => 'int',
                    'typeFromPhpDocPslam'  => 'int',
                ],
                'bar' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'       => 'string',
                    'typeFromPhpDocSimple' => 'string',
                    'typeFromPhpDocPslam'  => 'string',
                ],
                'lall1' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'null|int[]',
                    'typeFromPhpDoc'       => 'null|int[]',
                    'typeFromPhpDocSimple' => 'null|int[]',
                    'typeFromPhpDocPslam'  => 'array<int, int>|null',
                ],
                'lall2' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'float',
                    'typeFromPhpDoc'       => 'float',
                    'typeFromPhpDocSimple' => 'float',
                    'typeFromPhpDocPslam'  => 'float',
                ],
                'lall3' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'       => 'null|float',
                    'typeFromPhpDocSimple' => 'null|float',
                    'typeFromPhpDocPslam'  => 'float|null',
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
                    'paramsTypes'     => [],
                    'returnTypes'     => [
                        'type'                 => 'array',
                        'typeMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'       => 'array<int,int>',
                        'typeFromPhpDocSimple' => 'int[]',
                        'typeFromPhpDocPslam'  => 'array<int, int>',
                    ],
                ],
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [],
                    'returnTypes'     => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'       => 'false|int',
                        'typeFromPhpDocSimple' => 'false|int',
                        'typeFromPhpDocPslam'  => 'false|int',
                    ],
                ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'bool',
                            'typeMaybeWithComment' => '',
                            'typeFromPhpDoc'       => '',
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => '',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                        'typeFromPhpDoc'       => 'int[]|string[]|null',
                        'typeFromPhpDocSimple' => 'int[]|string[]|null',
                        'typeFromPhpDocPslam'  => 'list<int|string>|null',
                    ],
                ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'array',
                            'typeMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'       => 'int[]|null',
                            'typeFromPhpDocSimple' => 'int[]|null',
                            'typeFromPhpDocPslam'  => 'list<int>|null',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => '',
                        'typeFromPhpDoc'       => '',
                        'typeFromPhpDocSimple' => '',
                        'typeFromPhpDocPslam'  => '',
                    ],
                ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'array',
                            'typeMaybeWithComment' => '',
                            'typeFromPhpDoc'       => '',
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => 'list<int>|null',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => '',
                        'typeFromPhpDoc'       => '',
                        'typeFromPhpDocSimple' => '',
                        'typeFromPhpDocPslam'  => '',
                    ],
                ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                            'typeMaybeWithComment' => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'       => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                            'typeFromPhpDocSimple' => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                            'typeFromPhpDocPslam'  => 'phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => '',
                            'typeMaybeWithComment' => '',
                            'typeFromPhpDoc'       => '',
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => '',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
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
                    'type'                 => '',
                    'typeMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'       => 'int',
                    'typeFromPhpDocSimple' => 'int',
                    'typeFromPhpDocPslam'  => 'int',
                ],
                'bar' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'       => 'string',
                    'typeFromPhpDocSimple' => 'string',
                    'typeFromPhpDocPslam'  => 'string',
                ],
                'lall1' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'null|int[]',
                    'typeFromPhpDoc'       => 'null|int[]',
                    'typeFromPhpDocSimple' => 'null|int[]',
                    'typeFromPhpDocPslam'  => 'array<int, int>|null',
                ],
                'lall2' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'float',
                    'typeFromPhpDoc'       => 'float',
                    'typeFromPhpDocSimple' => 'float',
                    'typeFromPhpDocPslam'  => 'float',
                ],
                'lall3' => [
                    'type'                 => '',
                    'typeMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'       => 'null|float',
                    'typeFromPhpDocSimple' => 'null|float',
                    'typeFromPhpDocPslam'  => 'float|null',
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
                    'paramsTypes'     => [],
                    'returnTypes'     => [
                        'type'                 => 'array',
                        'typeMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'       => 'array<int,int>',
                        'typeFromPhpDocSimple' => 'int[]',
                        'typeFromPhpDocPslam'  => 'array<int, int>',
                    ],
                ],
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [],
                    'returnTypes'     => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'       => 'false|int',
                        'typeFromPhpDocSimple' => 'false|int',
                        'typeFromPhpDocPslam'  => 'false|int',
                    ],
                ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'bool',
                            'typeMaybeWithComment' => '',
                            'typeFromPhpDoc'       => '',
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => '',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                        'typeFromPhpDoc'       => 'int[]|string[]|null',
                        'typeFromPhpDocSimple' => 'int[]|string[]|null',
                        'typeFromPhpDocPslam'  => 'list<int|string>|null',
                    ],
                ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'array',
                            'typeMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'       => 'int[]|null',
                            'typeFromPhpDocSimple' => 'int[]|null',
                            'typeFromPhpDocPslam'  => 'array<array-key, int>|null',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => '',
                        'typeFromPhpDoc'       => '',
                        'typeFromPhpDocSimple' => '',
                        'typeFromPhpDocPslam'  => '',
                    ],
                ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                 => 'array',
                            'typeMaybeWithComment' => '',
                            'typeFromPhpDoc'       => '',
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => 'list<int>|null',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => '',
                        'typeFromPhpDoc'       => '',
                        'typeFromPhpDocSimple' => '',
                        'typeFromPhpDocPslam'  => '',
                    ],
                ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                            'typeMaybeWithComment' => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'       => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                            'typeFromPhpDocSimple' => '\phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                            'typeFromPhpDocPslam'  => 'phpDocumentor\Reflection\DocBlock\Tags\BaseTag',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                 => '',
                            'typeMaybeWithComment' => '',
                            'typeFromPhpDoc'       => '',
                            'typeFromPhpDocSimple' => '',
                            'typeFromPhpDocPslam'  => '',
                        ],
                    ],
                    'returnTypes' => [
                        'type'                 => '',
                        'typeMaybeWithComment' => 'array',
                        'typeFromPhpDoc'       => 'array',
                        'typeFromPhpDocSimple' => 'array',
                        'typeFromPhpDocPslam'  => 'array{parsedParamTagStr: string, variableName: array<array-key, null>|string}',
                    ],
                ],
            ],
            $result
        );
    }
}
