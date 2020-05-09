<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
final class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testSimple()
    {
        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);
    }

    public function testSimpleStringInput()
    {
        $code = '
        <?php
        namespace voku\tests;
        class SimpleClass {}
        $obja = new class() {};
        $objb = new class {};
        class AnotherClass {}';

        $phpCode = \voku\SimplePhpParser\Parsers\PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertSame(
            'a:3:{s:22:"voku\tests\SimpleClass";O:35:"voku\SimplePhpParser\Model\PHPClass":13:{s:11:"parentClass";N;s:10:"interfaces";a:0:{}s:7:"methods";a:0:{}s:9:"constants";a:0:{}s:4:"name";s:22:"voku\tests\SimpleClass";s:10:"parseError";N;s:5:"links";a:0:{}s:3:"see";a:0:{}s:9:"sinceTags";a:0:{}s:14:"deprecatedTags";a:0:{}s:11:"removedTags";a:0:{}s:8:"tagNames";a:0:{}s:18:"hasInternalMetaTag";b:0;}s:32:"613fb2e8d460c8384f4d268dc00e3ed9";O:35:"voku\SimplePhpParser\Model\PHPClass":13:{s:11:"parentClass";N;s:10:"interfaces";a:0:{}s:7:"methods";a:0:{}s:9:"constants";a:0:{}s:4:"name";s:0:"";s:10:"parseError";N;s:5:"links";a:0:{}s:3:"see";a:0:{}s:9:"sinceTags";a:0:{}s:14:"deprecatedTags";a:0:{}s:11:"removedTags";a:0:{}s:8:"tagNames";a:0:{}s:18:"hasInternalMetaTag";b:0;}s:23:"voku\tests\AnotherClass";O:35:"voku\SimplePhpParser\Model\PHPClass":13:{s:11:"parentClass";N;s:10:"interfaces";a:0:{}s:7:"methods";a:0:{}s:9:"constants";a:0:{}s:4:"name";s:23:"voku\tests\AnotherClass";s:10:"parseError";N;s:5:"links";a:0:{}s:3:"see";a:0:{}s:9:"sinceTags";a:0:{}s:14:"deprecatedTags";a:0:{}s:11:"removedTags";a:0:{}s:8:"tagNames";a:0:{}s:18:"hasInternalMetaTag";b:0;}}',
            \serialize($phpClasses)
        );
    }

    public function testGetMethodsInfo()
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
                            'type'                 => 'phpDocumentor',
                            'typeMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'       => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocPslam'  => 'phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
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
