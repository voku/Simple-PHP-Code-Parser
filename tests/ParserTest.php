<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
final class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleOneClass(): void
    {
        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);
    }

    public function testSimpleDirectory(): void
    {
        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);
        static::assertSame(Dummy2::class, $phpClasses[Dummy2::class]->name);
    }

    public function testSimpleStringInput(): void
    {
        $code = '<?php
        namespace voku\tests;
        class SimpleClass {}
        $obja = new class() {};
        $objb = new class {};
        class AnotherClass {}';

        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getFromString($code);
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

        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertSame('Foo', $phpClasses['Foo']->name);
        static::assertContains('Empty type', $phpClasses['Foo']->parseError);
        static::assertContains('Empty type', $phpClasses['Foo']->methods['foo']->parseError);
    }

    public function testGetMethodsInfoViaPhpReflectionOnly(): void
    {
        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(
            __DIR__ . '/Dummy.php',
            true
        );
        $phpClasses = $phpCode->getClasses();

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
                            'type'                 => '',
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
                            'type'                 => '',
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
        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

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
                            'type'                 => '',
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
                            'type'                 => '',
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
