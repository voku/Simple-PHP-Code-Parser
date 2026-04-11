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

        static::assertTrue(\count($phpClasses) > 0);

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame('withoutPhpDocParam', $phpClasses[Dummy::class]->methods['withoutPhpDocParam']->name);

        static::assertSame('bool', $phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']->type);
    }

    public function testSimpleOneClassV2(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy3.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy3::class, $phpClasses[Dummy3::class]->name);

        $lall4 = $phpClasses[Dummy3::class]->properties['lall4'];
        static::assertSame('lall4', $lall4->name);
        static::assertSame('int<-2147483648, 2147483647>', $lall4->typeFromPhpDoc);

        $lall11 = $phpClasses[Dummy3::class]->methods['lall11'];
        static::assertSame('lall11', $lall11->name);
        static::assertSame('voku\tests\DummyInterface', $lall11->returnType);
        static::assertSame('\voku\tests\Dummy3', $lall11->returnTypeFromPhpDocMaybeWithComment);

        $withComplexReturnArray = $phpClasses[Dummy3::class]->methods['withComplexReturnArray'];
        static::assertSame('withComplexReturnArray', $withComplexReturnArray->name);
        static::assertSame('This is a test-text [...] öäü !"§?.', $withComplexReturnArray->summary . $withComplexReturnArray->description);

        $parsedParamTag = $withComplexReturnArray->parameters['parsedParamTag'];
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $parsedParamTag->type);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag ' . "\n" . '<p>this is a test-text [...] öäü !"§?</p>', $parsedParamTag->typeFromPhpDocMaybeWithComment);
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

    public function testSimpleOneClassV4(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy9.php');
        $phpClasses = $phpCode->getClasses();

        self::assertFalse($phpClasses[Dummy9::class]->is_abstract);
        self::assertTrue($phpClasses[Dummy9::class]->is_final);
        self::assertTrue($phpClasses[Dummy9::class]->is_cloneable);
        self::assertTrue($phpClasses[Dummy9::class]->is_instantiable);
        self::assertFalse($phpClasses[Dummy9::class]->is_iterable);

        $getFieldArray = $phpClasses[Dummy9::class]->methods['getFieldArray'];
        static::assertSame('getFieldArray', $getFieldArray->name);

        static::assertSame('voku\tests\Dummy6', $phpClasses[Dummy9::class]->parentClass);
    }

    public function testUnionTypes(): void
    {
        if (PHP_VERSION_ID < 80000) {
            static::markTestSkipped('only for PHP >= 8.0');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy10.php');
        $phpClasses = $phpCode->getClasses();

        self::assertSame('int', $phpClasses[Dummy10::class]->constants['FOO3']->type);
        self::assertSame('private', $phpClasses[Dummy10::class]->constants['FOO3']->visibility);
        self::assertSame(3, $phpClasses[Dummy10::class]->constants['FOO3']->value);

        self::assertSame('int', $phpClasses[Dummy10::class]->constants['FOO4']->type);
        self::assertSame('public', $phpClasses[Dummy10::class]->constants['FOO4']->visibility);
        self::assertSame(-1, $phpClasses[Dummy10::class]->constants['FOO4']->value);

        self::assertFalse($phpClasses[Dummy10::class]->is_abstract);
        self::assertTrue($phpClasses[Dummy10::class]->is_final);
        self::assertTrue($phpClasses[Dummy10::class]->is_cloneable);
        self::assertTrue($phpClasses[Dummy10::class]->is_instantiable);
        self::assertFalse($phpClasses[Dummy10::class]->is_iterable);

        static::assertSame('null|int', $phpClasses[Dummy10::class]->properties['lall1']->getType());
        static::assertSame('null|int', $phpClasses[Dummy10::class]->properties['lall2']->getType());
        static::assertSame('null|int', $phpClasses[Dummy10::class]->properties['lall3']->getType());

        $getFieldArray = $phpClasses[Dummy10::class]->methods['getFieldArray'];
        static::assertSame('getFieldArray', $getFieldArray->name);
        static::assertSame('int|string', $getFieldArray->parameters['RowOffset']->type);
    }

    public function testConstructorPropertyPromotion(): void
    {
        if (PHP_VERSION_ID >= 80100) {
            $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy11.php');
            $phpClasses = $phpCode->getClasses();

            $getFieldArray = $phpClasses[Dummy11::class]->methods['__construct'];
            static::assertSame('__construct', $getFieldArray->name);
            static::assertSame('\DateTimeImmutable', $getFieldArray->parameters['date']->type);

            $isReadonly = $phpClasses[Dummy11::class]->properties['title']->is_readonly;
            static::assertSame(true, $isReadonly);
        } else {
            static::markTestSkipped('only for PHP >= 8.1');
        }
    }

    public function testReadonlyClass(): void
    {
        if (PHP_VERSION_ID >= 80200) {
            $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy13.php');
            $phpClasses = $phpCode->getClasses();

            $isReadonly = $phpClasses[Dummy13::class]->is_readonly;
            static::assertSame(true, $isReadonly);

            static::assertSame('callable(int): string', $phpClasses[Dummy13::class]->properties['lall']->typeFromPhpDocExtended);
            $isReadonly = $phpClasses[Dummy13::class]->properties['lall']->is_readonly;
            static::assertSame(true, $isReadonly);

            static::assertSame('callable(): int<0, 1>', $phpClasses[Dummy13::class]->methods['callableTest']->returnTypeFromPhpDocExtended);
        } else {
            static::markTestSkipped('only for PHP >= 8.2');
        }
    }

    public function testInheritDocFromInterface(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy12.php');
        $phpClasses = $phpCode->getClasses();

        $getFieldArray = $phpClasses[Dummy12::class]->methods['withComplexReturnArray'];
        static::assertSame('withComplexReturnArray', $getFieldArray->name);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $getFieldArray->parameters['parsedParamTag']->type);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $getFieldArray->parameters['parsedParamTag']->typeFromPhpDocSimple);
        static::assertSame('array', $getFieldArray->returnTypeFromPhpDocSimple);
        static::assertSame('array{parsedParamTagStr: string, variableName: (null[]|string)}', $getFieldArray->returnTypeFromPhpDocExtended);
    }

    public function testSimpleOneClassWithTrait(): void
    {
        if (PHP_VERSION_ID < 80000) {
            static::markTestSkipped('only for PHP >= 8.0');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy8.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame('\voku\tests\Foooooooo', $phpClasses[Dummy8::class]->properties['foooooooo']->defaultValue);

        static::assertSame(Dummy8::class, $phpClasses[Dummy8::class]->name);

        $method = $phpClasses[Dummy8::class]->methods['getLallTrait'];

        static::assertSame('getLallTrait', $method->name);

        static::assertSame(
            'array{stdClass: \stdClass, numbers: (int|float)}',
            $phpClasses[Dummy8::class]->methods['foo_mixed']->returnTypeFromPhpDocExtended
        );

        static::assertSame(
            'array{stdClass: \stdClass, numbers: (int|float)}',
            $phpClasses[Dummy8::class]->methods['foo_mixed']->parameters['lall']->typeFromPhpDocExtended
        );

        static::assertSame(
            'float|int',
            $phpClasses[Dummy8::class]->methods['test_multi_param_type']->parameters['param1']->type
        );

        static::assertSame(
            'int|float',
            $phpClasses[Dummy8::class]->methods['test_multi_param_type']->parameters['param1']->typeFromPhpDoc
        );

        static::assertSame(
            'callable(string): string',
            $phpClasses[Dummy8::class]->methods['withCallback']->parameters['callback']->typeFromPhpDocExtended
        );

        static::assertSame(
            'callable(): numeric',
            $phpClasses[Dummy8::class]->methods['withCallbackMulti']->parameters['callback2']->typeFromPhpDocExtended
        );

        static::assertSame(
            'string',
            $phpClasses[Dummy8::class]->methods['withCallbackMulti']->returnTypeFromPhpDoc
        );
    }

    public function testBrokenParamPhpDocRawIsPreserved(): void
    {
        if (PHP_VERSION_ID < 80000) {
            static::markTestSkipped('only for PHP >= 8.0');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy8.php');
        $phpClass = $phpCode->getClasses()[Dummy8::class];
        $fooBroken = $phpClass->methods['foo_broken'];
        $lall = $fooBroken->parameters['lall'];

        static::assertNull($lall->type);
        static::assertNull($lall->typeFromPhpDocMaybeWithComment);
        static::assertSame('array', $lall->typeFromPhpDoc);
        static::assertSame('array', $lall->typeFromPhpDocSimple);
        static::assertSame('array', $lall->typeFromPhpDocExtended);
        static::assertNull($lall->typeFromDefaultValue);
        static::assertSame(
            'array{stdClass: \stdClass, numbers: int|float $lall <foo/>',
            $lall->phpDocRaw
        );

        static::assertNull($fooBroken->returnType);
        static::assertNull($fooBroken->returnTypeFromPhpDocMaybeWithComment);
        static::assertSame('array', $fooBroken->returnTypeFromPhpDoc);
        static::assertSame('array', $fooBroken->returnTypeFromPhpDocSimple);
        static::assertSame('array', $fooBroken->returnTypeFromPhpDocExtended);
        static::assertSame(
            'array{stdClass: \stdClass, numbers: int|float <foo/>',
            $fooBroken->returnPhpDocRaw
        );

        static::assertSame(
            [
                'fullDescription' => '',
                'paramsTypes'     => [
                    'lall' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => 'array',
                        'typeFromPhpDocSimple'           => 'array',
                        'typeFromPhpDocExtended'         => 'array',
                        'typeFromDefaultValue'           => null,
                    ],
                ],
                'returnTypes' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => null,
                    'typeFromPhpDoc'                 => 'array',
                    'typeFromPhpDocSimple'           => 'array',
                    'typeFromPhpDocExtended'         => 'array',
                ],
                'paramsPhpDocRaw' => [
                    'lall' => 'array{stdClass: \stdClass, numbers: int|float $lall <foo/>',
                ],
                'returnPhpDocRaw' => 'array{stdClass: \stdClass, numbers: int|float <foo/>',
                'line'            => 62,
                'file'            => 'Simple-PHP-Code-Parser/tests/Dummy8.php',
                'error'           => 'foo_broken:62 | Unexpected token ":", expected \'}\' at offset 34 on line 1' . "\n"
                    . 'lall:62 | Unexpected token "$lall", expected \'}\' at offset 46 on line 1' . "\n"
                    . 'lall:62 | Unexpected token "", expected \'}\' at offset 45 on line 1',
                'is_deprecated'   => false,
                'is_static'       => false,
                'is_meta'         => false,
                'is_internal'     => false,
                'is_removed'      => false,
            ],
            self::removeLocalPathForTheTest($phpClass->getMethodsInfo()['foo_broken'])
        );

        // -- getPropertiesInfo(): class-string<T> generic + typeFromDefaultValue ---------
        // $foooooooo has @var class-string<Foooooooo> and a default of Foooooooo::class,
        // so typeFromDefaultValue should be 'string' (gettype of a string constant) and
        // typeFromPhpDocExtended should preserve the generic while typeFromPhpDocSimple
        // collapses it to the base type.
        $propsInfo = $phpClass->getPropertiesInfo();
        static::assertArrayHasKey('foooooooo', $propsInfo);
        static::assertSame('class-string<Foooooooo>', $propsInfo['foooooooo']['typeFromPhpDocExtended']);
        static::assertSame('string', $propsInfo['foooooooo']['typeFromPhpDocSimple']);
        static::assertSame('string', $propsInfo['foooooooo']['typeFromDefaultValue']);
        static::assertNull($propsInfo['foooooooo']['type']); // no native type on the property

        // -- list<int> return: typeFromPhpDocSimple collapses to int[] ------------------
        $fooList = $phpClass->methods['foo_list'];
        static::assertSame('list<int>', $fooList->returnTypeFromPhpDoc);
        static::assertSame('int[]', $fooList->returnTypeFromPhpDocSimple);
        static::assertSame('list<int>', $fooList->returnTypeFromPhpDocExtended);
        static::assertSame('list<int>', $fooList->returnPhpDocRaw);

        // -- Well-formed array shape (foo_mixed): union-inside-shape normalization ------
        // typeFromPhpDocExtended should normalize int|float inside an array shape to
        // (int|float); the raw phpDoc and typeFromPhpDoc preserve the original.
        $fooMixed = $phpClass->methods['foo_mixed'];
        $lallMixed = $fooMixed->parameters['lall'];
        static::assertSame('array{stdClass: \stdClass, numbers: int|float} $lall', $lallMixed->phpDocRaw);
        static::assertSame('array{stdClass: \stdClass, numbers: int|float}', $lallMixed->typeFromPhpDoc);
        static::assertSame('array', $lallMixed->typeFromPhpDocSimple);
        static::assertSame('array{stdClass: \stdClass, numbers: (int|float)}', $lallMixed->typeFromPhpDocExtended);
        // return type mirrors the param shape
        static::assertSame('array{stdClass: \stdClass, numbers: (int|float)}', $fooMixed->returnTypeFromPhpDocExtended);
        static::assertSame('array', $fooMixed->returnTypeFromPhpDocSimple);
        static::assertSame('array{stdClass: \stdClass, numbers: int|float}', $fooMixed->returnPhpDocRaw);

        // -- Callable shape types (withCallback / withCallbackMulti) -------------------
        // Verifies that callable(args): return type annotations are preserved as-is
        // through typeFromPhpDocExtended and collapsed to 'callable' in typeFromPhpDocSimple.
        $withCallback = $phpClass->methods['withCallback'];
        $cbParam = $withCallback->parameters['callback'];
        static::assertSame('callable(string): string', $cbParam->typeFromPhpDoc);
        static::assertSame('callable', $cbParam->typeFromPhpDocSimple);
        static::assertSame('callable(string): string', $cbParam->typeFromPhpDocExtended);
        static::assertSame('callable(string): string $callback', $cbParam->phpDocRaw);

        $withCallbackMulti = $phpClass->methods['withCallbackMulti'];
        $cb1 = $withCallbackMulti->parameters['callback'];
        $cb2 = $withCallbackMulti->parameters['callback2'];
        static::assertSame('callable(string, int, string): string', $cb1->typeFromPhpDocExtended);
        static::assertSame('callable', $cb1->typeFromPhpDocSimple);
        static::assertSame('callable(): numeric', $cb2->typeFromPhpDocExtended);
        static::assertSame('callable', $cb2->typeFromPhpDocSimple);

        // -- Native union types vs. phpDoc union types (test_multi_param_type) ---------
        // The AST type for int|float comes out as 'float|int' (alphabetical from
        // php-parser), while @param says 'int|float'; similarly for bool|int return.
        $multiParam = $phpClass->methods['test_multi_param_type'];
        $param1 = $multiParam->parameters['param1'];
        static::assertNotNull($param1->type);            // has a native type hint
        static::assertSame('int|float', $param1->typeFromPhpDoc);
        static::assertSame('int|float', $param1->typeFromPhpDocExtended);
        static::assertNotNull($multiParam->returnType);  // has a native return type
        static::assertSame('bool|int', $multiParam->returnTypeFromPhpDoc);
        static::assertSame('bool|int', $multiParam->returnTypeFromPhpDocExtended);
    }

    public function testSimpleOneTrait(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyTrait.php');
        $phpTraits = $phpCode->getTraits();

        static::assertSame(DummyTrait::class, $phpTraits[DummyTrait::class]->name);

        self::assertFalse($phpTraits[DummyTrait::class]->is_abstract);
        self::assertFalse($phpTraits[DummyTrait::class]->is_final);
        self::assertFalse($phpTraits[DummyTrait::class]->is_cloneable);
        self::assertFalse($phpTraits[DummyTrait::class]->is_instantiable);
        self::assertFalse($phpTraits[DummyTrait::class]->is_iterable);

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
                        'typeFromPhpDocExtended'         => 'float',
                    ],
                    'paramsPhpDocRaw' => [],
                    'returnPhpDocRaw' => 'float',
                    'line'            => 20,
                    'file'            => 'Simple-PHP-Code-Parser/tests/DummyTrait.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                ],
            ],
            self::removeLocalPathForTheTest($phpTraits[DummyTrait::class]->getMethodsInfo())
        );

        static::assertSame(
            [
                'lall_trait' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|float|int<0, 10>',
                    'typeFromPhpDoc'                 => 'null|float|int<0, 10>',
                    'typeFromPhpDocSimple'           => 'null|float|int',
                    'typeFromPhpDocExtended'         => 'null|float|int<0, 10>',
                    'typeFromDefaultValue'           => null,
                ],
            ],
            self::removeLocalPathForTheTest($phpTraits[DummyTrait::class]->getPropertiesInfo())
        );
    }

    public function testSimpleDirectory(): void
    {
        $pathExcludeRegex = ['/Dummy5|Dummy1[0|1|3]|Dummy8/'];
        if (!\class_exists(\PhpParser\Node\PropertyHook::class)) {
            $pathExcludeRegex[] = '/DummyPropertyHooks|DummyPromotedPropertyHooks/';
        }

        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/',
            [],
            $pathExcludeRegex
        );

        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame(Dummy2::class, $phpClasses[Dummy2::class]->name);
        static::assertSame(DummyInterface::class, $phpClasses[Dummy2::class]->interfaces[0]);

        $phpInterfaces = $phpCode->getInterfaces();

        self::assertTrue($phpInterfaces[DummyInterface::class]->is_abstract);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_final);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_cloneable);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_instantiable);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_iterable);

        static::assertSame('array{parsedParamTagStr: string, variableName: (null[]|string)}', $phpInterfaces[DummyInterface::class]->methods['withComplexReturnArray']->returnTypeFromPhpDocExtended);
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

    public function testFromClassName(): void
    {
        $phpCode = PhpCodeParser::getFromClassName(Dummy::class);
        $phpClasses = $phpCode->getClasses();

        static::assertSame('array<int, int>', $phpClasses[Dummy::class]->methods['withReturnType']->returnTypeFromPhpDocExtended);
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
         * @property int[] $foo 
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
        static::assertSame('int[]', $phpClasses['Foo']->properties['foo']->typeFromPhpDoc);
    }

    public function testSpecialPhpDocComment(): void
    {
        $code = '
        <?php
        /**
        * Add a route like `$router->route(\'/blog/\', function(){...});` where function returns html.
        *
        * @export(Router.route)
        *
        * @return mixed[][][]|false
        */
        function route($pattern, $callback){}
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpFunctions = $phpCode->getFunctions();

        static::assertSame('route', $phpFunctions['route']->name);
        static::assertSame(['export' => '@export (Router.route)', 'return' => '@return mixed[][][]|false'], $phpFunctions['route']->tagNames);
    }

    public function testMalformedParamPhpDocReportingVariants(): void
    {
        $code = <<<'PHP'
<?php

namespace voku\tests;

class BrokenPhpDocVariantsParent
{
    /**
     * @param $value
     */
    public function brokenParam($value): void
    {
    }

    /**
     * @psalm-param $value
     */
    public function brokenPsalmParam($value): void
    {
    }

    /**
     * @param mixed $value
     */
    public function validMixed($value): void
    {
    }

    /**
     * @param array{foo:int,bar:string} $value
     */
    public function validShape($value): void
    {
    }

    /**
     * @param int $valueSuffix
     */
    public function validDifferentParameterName($value): void
    {
    }
}

class BrokenPhpDocVariantsChild extends BrokenPhpDocVariantsParent
{
}
PHP;

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        $parentMethodsInfo = $phpClasses['voku\tests\BrokenPhpDocVariantsParent']->getMethodsInfo();

        static::assertStringContainsString(
            'Unexpected token "$value", expected type at offset 0 on line 1',
            $parentMethodsInfo['brokenParam']['error']
        );
        static::assertStringContainsString(
            'Unexpected token "$value", expected type at offset 0 on line 1',
            $parentMethodsInfo['brokenPsalmParam']['error']
        );
        static::assertSame('mixed', $parentMethodsInfo['brokenParam']['paramsTypes']['value']['typeFromPhpDoc']);
        static::assertSame('mixed', $parentMethodsInfo['brokenParam']['paramsTypes']['value']['typeFromPhpDocSimple']);
        static::assertSame('mixed', $parentMethodsInfo['brokenParam']['paramsTypes']['value']['typeFromPhpDocExtended']);
        static::assertNull($parentMethodsInfo['brokenPsalmParam']['paramsTypes']['value']['typeFromPhpDoc']);
        static::assertNull($parentMethodsInfo['brokenPsalmParam']['paramsTypes']['value']['typeFromPhpDocSimple']);
        static::assertNull($parentMethodsInfo['brokenPsalmParam']['paramsTypes']['value']['typeFromPhpDocExtended']);
        static::assertSame('', $parentMethodsInfo['validMixed']['error']);
        static::assertSame('', $parentMethodsInfo['validShape']['error']);
        static::assertSame('', $parentMethodsInfo['validDifferentParameterName']['error']);
    }

    public function testGetFunctionsInfo(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/Dummy.php'
        );
        $phpFunctionsInfo = $phpCode->getFunctionsInfo();

        $result = $phpFunctionsInfo;

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //\var_export($result);

        static::assertSame([
            'voku\\tests\\foo' => [
                'fullDescription' => '',
                'paramsTypes'     => [
                    'foo' => [
                        'type'                           => 'int',
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => null,
                        'typeFromPhpDocSimple'           => null,
                        'typeFromPhpDocExtended'         => null,
                        'typeFromDefaultValue'           => 'int',
                    ],
                ],
                'returnTypes' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => '\\Dummy',
                    'typeFromPhpDoc'                 => 'Dummy',
                    'typeFromPhpDocSimple'           => '\\Dummy',
                    'typeFromPhpDocExtended'         => 'Dummy',
                ],
                'paramsPhpDocRaw' => [
                    'foo' => null,
                ],
                'returnPhpDocRaw' => '\Dummy',
                'line'            => 10,
                'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                'error'           => '',
                'is_deprecated'   => false,
                'is_meta'         => false,
                'is_internal'     => false,
                'is_removed'      => false,
            ],
        ], $result);
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
                        'typeFromPhpDocExtended'         => 'false|int',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'false|int',
                    'line'            => 59,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'array',
                        'typeFromPhpDocMaybeWithComment' => 'array<int, int>',
                        'typeFromPhpDoc'                 => 'array<int, int>',
                        'typeFromPhpDocSimple'           => 'int[]',
                        'typeFromPhpDocExtended'         => 'array<int, int>',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'array<int, int>',
                    'line'            => 51,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
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
                        'typeFromPhpDocExtended'         => 'float',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'float',
                    'line'            => 20,
                    'file'            => 'Simple-PHP-Code-Parser/tests/DummyTrait.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $parsedParamTag',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => 'mixed $parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 119,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => 'parsedParamTag:119 | Unexpected token "$parsedParamTag", expected type at offset 0 on line 1',
                        'is_deprecated'   => false,
                        'is_static'       => true,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple'           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocExtended'         => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 104,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => true,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => '?list<int>',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => '?list<int> $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 90,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'                 => 'int[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|null',
                            'typeFromPhpDocExtended'         => 'int[]|null',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => 'int[]|null $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 80,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => 'bool',
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                            'typeFromDefaultValue'           => 'bool',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                            'typeFromPhpDoc'                 => 'int[]|string[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|string[]|null',
                            'typeFromPhpDocExtended'         => '?list<(int|string)>',
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => null,
                        ],
                        'returnPhpDocRaw' => 'int[]|string[]|null <p>foo</p>',
                        'line'            => 69,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'array',
                        ],
                        'p2' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'string',
                        ],
                        'p3' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'int',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array',
                        ],
                        'paramsPhpDocRaw' => [
                            'p1' => 'mixed $p1',
                            'p2' => 'mixed $p2',
                            'p3' => 'mixed $p3',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 134,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
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
                    'typeFromPhpDocExtended'         => 'null|array<int, int>',
                    'typeFromDefaultValue'           => 'array',
                ],
                'lall2' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'float',
                    'typeFromPhpDoc'                 => 'float',
                    'typeFromPhpDocSimple'           => 'float',
                    'typeFromPhpDocExtended'         => 'float',
                    'typeFromDefaultValue'           => 'float',
                ],
                'lall3' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'                 => 'null|float',
                    'typeFromPhpDocSimple'           => 'null|float',
                    'typeFromPhpDocExtended'         => 'null|float',
                    'typeFromDefaultValue'           => null,
                ],
                'foo' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'                 => 'int',
                    'typeFromPhpDocSimple'           => 'int',
                    'typeFromPhpDocExtended'         => 'int',
                    'typeFromDefaultValue'           => null,
                ],
                'bar' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'                 => 'string',
                    'typeFromPhpDocSimple'           => 'string',
                    'typeFromPhpDocExtended'         => 'string',
                    'typeFromDefaultValue'           => null,
                ],
            ],
            $props
        );

        $result = $phpClasses[Dummy::class]->getMethodsInfo();

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //var_export($result);

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
                        'typeFromPhpDocExtended'         => 'false|int',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'false|int',
                    'line'            => 59,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'array',
                        'typeFromPhpDocMaybeWithComment' => 'array<int, int>',
                        'typeFromPhpDoc'                 => 'array<int, int>',
                        'typeFromPhpDocSimple'           => 'int[]',
                        'typeFromPhpDocExtended'         => 'array<int, int>',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'array<int, int>',
                    'line'            => 51,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $parsedParamTag',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => 'mixed $parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 119,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error' => 'parsedParamTag:119 | Unexpected token "$parsedParamTag", expected type at offset 0 on line 1',
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
                            'typeFromPhpDocExtended'         => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 104,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => true,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => '?list<int>',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => '?list<int> $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 90,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'                 => 'int[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|null',
                            'typeFromPhpDocExtended'         => 'int[]|null',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => 'int[]|null $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 80,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => 'bool',
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                            'typeFromDefaultValue'           => 'bool',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                            'typeFromPhpDoc'                 => 'int[]|string[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|string[]|null',
                            'typeFromPhpDocExtended'         => '?list<(int|string)>',
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => null,
                        ],
                        'returnPhpDocRaw' => 'int[]|string[]|null <p>foo</p>',
                        'line'            => 69,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'array',
                        ],
                        'p2' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'string',
                        ],
                        'p3' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'int',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array',
                        ],
                        'paramsPhpDocRaw' => [
                            'p1' => 'mixed $p1',
                            'p2' => 'mixed $p2',
                            'p3' => 'mixed $p3',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 134,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
            ],
            $result
        );
    }

    public function testFunctionWithAmpersendInParameters(): void
    {
        $code = '<?php
        /**
         * Open Internet or Unix domain socket connection
         * @link https://php.net/manual/en/function.fsockopen.php
         * @param string $hostname <p>
         * If you have compiled in OpenSSL support, you may prefix the
         * hostname with either ssl://
         * or tls:// to use an SSL or TLS client connection
         * over TCP/IP to connect to the remote host.
         * </p>
         * @param null|int $port [optional] <p>
         * The port number.
         * </p>
         * @param int &$errno [optional] <p>
         * If provided, holds the system level error number that occurred in the
         * system-level connect() call.
         * </p>
         * <p>
         * If the value returned in errno is
         * 0 and the function returned false, it is an
         * indication that the error occurred before the
         * connect() call. This is most likely due to a
         * problem initializing the socket.
         * </p>
         * @param string &$errstr [optional] <p>
         * The error message as a string.
         * </p>
         * @param null|float $timeout [optional] <p>
         * The connection timeout, in seconds.
         * </p>
         * <p>
         * If you need to set a timeout for reading/writing data over the
         * socket, use stream_set_timeout, as the
         * timeout parameter to
         * fsockopen only applies while connecting the
         * socket.
         * </p>
         * @return resource|false fsockopen returns a file pointer which may be used
         * together with the other file functions (such as
         * fgets, fgetss,
         * fwrite, fclose, and
         * feof). If the call fails, it will return false
         */
        function fsockopen ($hostname, $port = null, &$errno = null, &$errstr = null, $timeout = null) { /** ... */ };';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpFunctions = $phpCode->getFunctions();

        self::assertTrue(isset($phpFunctions['fsockopen']));
        self::assertTrue(isset($phpFunctions['fsockopen']->parameters['errno']));
        self::assertTrue($phpFunctions['fsockopen']->parameters['errno']->is_passed_by_ref);
        self::assertSame('int', $phpFunctions['fsockopen']->parameters['errno']->typeFromPhpDoc);

        // -----------------------------

        $phpFunctionsInfo = $phpCode->getFunctionsInfo();
        self::assertSame('int', $phpFunctionsInfo['fsockopen']['paramsTypes']['errno']['typeFromPhpDoc']);
    }

    public function testResolvedComplexTypeHintsFromAst(): void
    {
        if (PHP_VERSION_ID < 80000) {
            static::markTestSkipped('only for PHP >= 8.0');
        }

        $phpCode = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace voku\tests;

final class DummyFromString
{
    public function example(\DateTimeInterface|\DateTimeImmutable|null $date): void
    {
    }
}
PHP
        );
        $phpClasses = $phpCode->getClasses();

        static::assertSame(
            '\DateTimeInterface|\DateTimeImmutable|null',
            $phpClasses['voku\tests\DummyFromString']->methods['example']->parameters['date']->type
        );
    }

    public function testTypedClassConstantsFromAst(): void
    {
        if (PHP_VERSION_ID < 80300) {
            static::markTestSkipped('only for PHP >= 8.3');
        }

        $phpCode = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace voku\tests;

final class DummyTypedConstant
{
    public const string NAME = 'foo';
}
PHP
        );
        $phpClasses = $phpCode->getClasses();

        static::assertSame('string', $phpClasses['voku\tests\DummyTypedConstant']->constants['NAME']->type);
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

    public function testEnumString(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnum.php');
        $phpEnums = $phpCode->getEnums();

        static::assertArrayHasKey(DummyEnum::class, $phpEnums);

        $enum = $phpEnums[DummyEnum::class];

        static::assertSame(DummyEnum::class, $enum->name);
        static::assertSame('string', $enum->scalarType);

        // Check cases
        static::assertCount(4, $enum->cases);
        static::assertSame('H', $enum->cases['Hearts']);
        static::assertSame('D', $enum->cases['Diamonds']);
        static::assertSame('C', $enum->cases['Clubs']);
        static::assertSame('S', $enum->cases['Spades']);

        // Check method
        static::assertArrayHasKey('color', $enum->methods);
        static::assertSame('string', $enum->methods['color']->returnType);
    }

    public function testEnumUnit(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnumUnit.php');
        $phpEnums = $phpCode->getEnums();

        static::assertArrayHasKey(DummyEnumUnit::class, $phpEnums);

        $enum = $phpEnums[DummyEnumUnit::class];
        static::assertSame(DummyEnumUnit::class, $enum->name);
        static::assertNull($enum->scalarType);

        // Unit enums have no backing values
        static::assertCount(3, $enum->cases);
        static::assertNull($enum->cases['Pending']);
        static::assertNull($enum->cases['Active']);
        static::assertNull($enum->cases['Closed']);
    }

    public function testEnumInt(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyEnumInt.php');
        $phpEnums = $phpCode->getEnums();

        static::assertArrayHasKey(DummyEnumInt::class, $phpEnums);

        $enum = $phpEnums[DummyEnumInt::class];
        static::assertSame(DummyEnumInt::class, $enum->name);
        static::assertSame('int', $enum->scalarType);

        static::assertCount(3, $enum->cases);
        static::assertSame(1, $enum->cases['Low']);
        static::assertSame(2, $enum->cases['Medium']);
        static::assertSame(3, $enum->cases['High']);

        // Check method
        static::assertArrayHasKey('label', $enum->methods);
        static::assertSame('string', $enum->methods['label']->returnType);
    }

    public function testEnumFromString(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $code = '<?php
        enum Color: string {
            case Red = \'red\';
            case Blue = \'blue\';

            public function label(): string {
                return ucfirst($this->value);
            }
        }
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpEnums = $phpCode->getEnums();

        static::assertCount(1, $phpEnums);
        $enum = \array_values($phpEnums)[0];
        static::assertSame('string', $enum->scalarType);
        static::assertCount(2, $enum->cases);
        static::assertSame('red', $enum->cases['Red']);
        static::assertSame('blue', $enum->cases['Blue']);
        static::assertArrayHasKey('label', $enum->methods);
    }

    public function testIntersectionTypes(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy14.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey(Dummy14::class, $phpClasses);

        $class = $phpClasses[Dummy14::class];

        // On PHP < 8.2, Dummy14 cannot be autoloaded (it contains PHP 8.2+ syntax such as
        // standalone `null` return type), so intersection types are sourced from the AST and
        // carry a leading backslash on each class-name component (FQN format).
        // On PHP >= 8.2, the class is reflected and reflection's __toString() omits the backslash.
        $expectedIntersection = \PHP_VERSION_ID >= 80200
            ? 'Countable&voku\tests\DummyInterface4'
            : '\Countable&\voku\tests\DummyInterface4';

        // Intersection type on property
        static::assertSame($expectedIntersection, $class->properties['intersectionProp']->type);

        // Intersection type on parameter
        $method = $class->methods['getIntersection'];
        static::assertSame($expectedIntersection, $method->parameters['input']->type);

        // Intersection return type
        static::assertSame($expectedIntersection, $method->returnType);
    }

    public function testNeverReturnType(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy14.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame('never', $phpClasses[Dummy14::class]->methods['neverReturn']->returnType);
    }

    public function testStandaloneTypes(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            static::markTestSkipped('only for PHP >= 8.2');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy14.php');
        $phpClasses = $phpCode->getClasses();

        $class = $phpClasses[Dummy14::class];

        static::assertSame('true', $class->methods['returnTrue']->returnType);
        static::assertSame('false', $class->methods['returnFalse']->returnType);
        static::assertSame('null', $class->methods['returnNull']->returnType);
    }

    public function testDnfTypes(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            static::markTestSkipped('only for PHP >= 8.2');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy15.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey(Dummy15::class, $phpClasses);

        $class = $phpClasses[Dummy15::class];

        // DNF type on property: (\Countable&\Traversable)|null
        $propType = $class->properties['dnfProp']->type;
        static::assertNotNull($propType);
        static::assertStringContainsString('Countable', $propType);
        static::assertStringContainsString('Traversable', $propType);
        static::assertStringContainsString('|', $propType);
        static::assertStringContainsString('&', $propType);

        // DNF type on parameter
        $paramType = $class->methods['getDnf']->parameters['input']->type;
        static::assertNotNull($paramType);
        static::assertStringContainsString('Countable', $paramType);
        static::assertStringContainsString('Traversable', $paramType);

        // DNF return type
        $returnType = $class->methods['getDnf']->returnType;
        static::assertNotNull($returnType);
        static::assertStringContainsString('Countable', $returnType);
        static::assertStringContainsString('Traversable', $returnType);
    }

    public function testTypedClassConstants(): void
    {
        if (\PHP_VERSION_ID < 80300) {
            static::markTestSkipped('only for PHP >= 8.3');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy16.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey(Dummy16::class, $phpClasses);

        $class = $phpClasses[Dummy16::class];

        static::assertSame('string', $class->constants['NAME']->typeFromDeclaration);
        static::assertSame('dummy', $class->constants['NAME']->value);
        static::assertSame('public', $class->constants['NAME']->visibility);

        static::assertSame('int', $class->constants['VERSION']->typeFromDeclaration);
        static::assertSame(1, $class->constants['VERSION']->value);

        static::assertSame('float', $class->constants['RATIO']->typeFromDeclaration);
        static::assertSame('protected', $class->constants['RATIO']->visibility);

        static::assertSame('bool', $class->constants['ACTIVE']->typeFromDeclaration);
        static::assertSame('private', $class->constants['ACTIVE']->visibility);
    }

    public function testTraitConstants(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            static::markTestSkipped('only for PHP >= 8.2');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyTrait2.php');
        $phpTraits = $phpCode->getTraits();

        static::assertArrayHasKey(DummyTrait2::class, $phpTraits);

        $trait = $phpTraits[DummyTrait2::class];

        static::assertArrayHasKey('TRAIT_CONST_A', $trait->constants);
        static::assertSame('alpha', $trait->constants['TRAIT_CONST_A']->value);
        static::assertSame('public', $trait->constants['TRAIT_CONST_A']->visibility);

        static::assertArrayHasKey('TRAIT_CONST_B', $trait->constants);
        static::assertSame(42, $trait->constants['TRAIT_CONST_B']->value);
        static::assertSame('protected', $trait->constants['TRAIT_CONST_B']->visibility);

        // Check trait method
        static::assertArrayHasKey('traitMethod', $trait->methods);
        static::assertSame('string', $trait->methods['traitMethod']->returnType);
    }

    public function testEnumDirectoryParsing(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            static::markTestSkipped('only for PHP >= 8.1');
        }

        $pathExcludeRegex = [];
        if (!\class_exists(\PhpParser\Node\PropertyHook::class)) {
            $pathExcludeRegex[] = '/DummyPropertyHooks|DummyPromotedPropertyHooks/';
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__, [], $pathExcludeRegex);
        $phpEnums = $phpCode->getEnums();

        // Should find all the enums we created
        static::assertArrayHasKey(DummyEnum::class, $phpEnums);
        static::assertArrayHasKey(DummyEnumUnit::class, $phpEnums);
        static::assertArrayHasKey(DummyEnumInt::class, $phpEnums);
    }

    public function testAttributesOnClass(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyWithAttributes.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey(DummyWithAttributes::class, $phpClasses);

        $class = $phpClasses[DummyWithAttributes::class];

        // Class-level attributes
        static::assertNotEmpty($class->attributes);
        static::assertSame('voku\tests\DummyAttribute', $class->attributes[0]->name);
        static::assertSame('TestClass', $class->attributes[0]->arguments['name']);
        static::assertSame(1, $class->attributes[0]->arguments['priority']);
    }

    public function testAttributesOnProperty(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyWithAttributes.php');
        $phpClasses = $phpCode->getClasses();

        $class = $phpClasses[DummyWithAttributes::class];

        // Property-level attributes
        static::assertNotEmpty($class->properties['name']->attributes);
        static::assertSame('voku\tests\DummyPropertyAttribute', $class->properties['name']->attributes[0]->name);
        static::assertTrue($class->properties['name']->attributes[0]->arguments['required']);

        // Property without required arg — default value
        static::assertNotEmpty($class->properties['age']->attributes);
        static::assertSame('voku\tests\DummyPropertyAttribute', $class->properties['age']->attributes[0]->name);
    }

    public function testAttributesOnMethod(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyWithAttributes.php');
        $phpClasses = $phpCode->getClasses();

        $class = $phpClasses[DummyWithAttributes::class];

        // Method-level attributes
        static::assertNotEmpty($class->methods['apiMethod']->attributes);
        static::assertSame('voku\tests\DummyMethodAttribute', $class->methods['apiMethod']->attributes[0]->name);
        static::assertSame('/api/test', $class->methods['apiMethod']->attributes[0]->arguments['route']);

        // Method without attributes
        static::assertEmpty($class->methods['plainMethod']->attributes);
    }

    public function testAttributesOnParameter(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyWithAttributes.php');
        $phpClasses = $phpCode->getClasses();

        $class = $phpClasses[DummyWithAttributes::class];

        // Parameter-level attributes
        $param1 = $class->methods['apiMethod']->parameters['param1'];
        static::assertNotEmpty($param1->attributes);
        static::assertSame('voku\tests\DummyParameterAttribute', $param1->attributes[0]->name);
        static::assertSame('query', $param1->attributes[0]->arguments['type']);

        // Parameter without attributes
        $param2 = $class->methods['apiMethod']->parameters['param2'];
        static::assertEmpty($param2->attributes);
    }

    public function testOverrideAttribute(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyOverride.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey(DummyOverrideChild::class, $phpClasses);

        $child = $phpClasses[DummyOverrideChild::class];

        // greet has #[\Override]
        static::assertTrue($child->methods['greet']->is_override);

        // farewell does NOT have #[\Override]
        static::assertNull($child->methods['farewell']->is_override);

        // newMethod does NOT have #[\Override]
        static::assertNull($child->methods['newMethod']->is_override);

        // Also check the Override attribute is in the attributes array
        static::assertNotEmpty($child->methods['greet']->attributes);
        $foundOverride = false;
        foreach ($child->methods['greet']->attributes as $attr) {
            if ($attr->name === 'Override') {
                $foundOverride = true;
            }
        }
        static::assertTrue($foundOverride, '#[Override] should be in the attributes array');
    }

    public function testModernSyntaxParsing(): void
    {
        // Verify the parser doesn't choke on modern PHP 8.x syntax
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyModernSyntax.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey(DummyFirstClassCallable::class, $phpClasses);

        $class = $phpClasses[DummyFirstClassCallable::class];

        // first-class callable method has Closure return type
        static::assertSame('\Closure', $class->methods['getCallable']->returnType);

        // match expression method parses fine
        static::assertSame('string', $class->methods['matchExample']->returnType);
        static::assertSame('string', $class->methods['matchExample']->parameters['status']->type);

        // named arguments example
        static::assertSame('string', $class->methods['namedArgExample']->returnType);

        // nullsafe operator example
        static::assertSame('null|string', $class->methods['nullsafeExample']->returnType);
    }

    public function testAttributeFromStringInput(): void
    {
        $code = '
        <?php
        #[\Attribute]
        class MyCustomAttr {
            public function __construct(public string $value = "") {}
        }

        #[MyCustomAttr(value: "test")]
        class TestTarget {
            #[MyCustomAttr(value: "prop")]
            public string $field = "";

            #[MyCustomAttr(value: "method")]
            public function doSomething(#[MyCustomAttr(value: "param")] int $x): void {}
        }
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey('TestTarget', $phpClasses);

        $class = $phpClasses['TestTarget'];

        // Class-level attribute
        static::assertNotEmpty($class->attributes);
        static::assertSame('MyCustomAttr', $class->attributes[0]->name);
        static::assertSame('test', $class->attributes[0]->arguments['value']);

        // Property-level attribute
        static::assertNotEmpty($class->properties['field']->attributes);
        static::assertSame('MyCustomAttr', $class->properties['field']->attributes[0]->name);
        static::assertSame('prop', $class->properties['field']->attributes[0]->arguments['value']);

        // Method-level attribute
        static::assertNotEmpty($class->methods['doSomething']->attributes);
        static::assertSame('MyCustomAttr', $class->methods['doSomething']->attributes[0]->name);
        static::assertSame('method', $class->methods['doSomething']->attributes[0]->arguments['value']);

        // Parameter-level attribute
        static::assertNotEmpty($class->methods['doSomething']->parameters['x']->attributes);
        static::assertSame('MyCustomAttr', $class->methods['doSomething']->parameters['x']->attributes[0]->name);
        static::assertSame('param', $class->methods['doSomething']->parameters['x']->attributes[0]->arguments['value']);
    }

    public function testConstantAndFunctionAttributesFromStringInput(): void
    {
        $code = <<<'PHP'
<?php

namespace voku\tests;

use Attribute;
use voku\tests\ParserAttr as ParserAttributeAlias;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::TARGET_FUNCTION | Attribute::TARGET_PARAMETER)]
class ParserAttr
{
    public function __construct(public string $name = '')
    {
    }
}

class AttributeTargets
{
    #[ParserAttributeAlias(name: 'const')]
    public const FOO = 1;
}

#[ParserAttributeAlias(name: 'function')]
function attribute_target(
    #[ParserAttributeAlias(name: 'parameter')] string &$label,
    int $count = 1,
    string ...$ids
): void {
}
PHP;

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();
        $phpFunctions = $phpCode->getFunctions();

        static::assertArrayHasKey('voku\tests\AttributeTargets', $phpClasses);
        static::assertArrayHasKey('voku\tests\attribute_target', $phpFunctions);

        $class = $phpClasses['voku\tests\AttributeTargets'];
        $function = $phpFunctions['voku\tests\attribute_target'];

        static::assertNotEmpty($class->constants['FOO']->attributes);
        static::assertSame('voku\tests\ParserAttr', $class->constants['FOO']->attributes[0]->name);
        static::assertSame('const', $class->constants['FOO']->attributes[0]->arguments['name']);

        static::assertNotEmpty($function->attributes);
        static::assertSame('voku\tests\ParserAttr', $function->attributes[0]->name);
        static::assertSame('function', $function->attributes[0]->arguments['name']);

        static::assertNotEmpty($function->parameters['label']->attributes);
        static::assertSame('voku\tests\ParserAttr', $function->parameters['label']->attributes[0]->name);
        static::assertSame('parameter', $function->parameters['label']->attributes[0]->arguments['name']);
    }

    public function testStandaloneFunctionParameterMetadataFromStringInput(): void
    {
        $code = <<<'PHP'
<?php

namespace voku\tests;

function parameter_metadata(string &$label, int $count = 1, ?string $optional = null, string ...$ids): void
{
}
PHP;

        $phpCode = PhpCodeParser::getFromString($code);
        $phpFunctions = $phpCode->getFunctions();

        static::assertArrayHasKey('voku\tests\parameter_metadata', $phpFunctions);

        $function = $phpFunctions['voku\tests\parameter_metadata'];

        static::assertTrue($function->parameters['label']->is_passed_by_ref);
        static::assertNull($function->parameters['label']->typeFromDefaultValue);

        static::assertSame(1, $function->parameters['count']->defaultValue);
        static::assertSame('int', $function->parameters['count']->typeFromDefaultValue);

        static::assertSame('null', $function->parameters['optional']->typeFromDefaultValue);

        static::assertTrue($function->parameters['ids']->is_vararg);
        static::assertSame('string', $function->parameters['ids']->type);
    }

    public function testPropertyHooksFromStringInput(): void
    {
        if (!\class_exists(\PhpParser\Node\PropertyHook::class)) {
            static::markTestSkipped('Property hooks require nikic/php-parser v5');
        }

        $code = (string) \file_get_contents(__DIR__ . '/DummyPropertyHooks.php');

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey('voku\tests\DummyPropertyHooks', $phpClasses);

        $class = $phpClasses['voku\tests\DummyPropertyHooks'];

        // -- Property hooks on $fullName --
        static::assertArrayHasKey('fullName', $class->properties);
        $fullName = $class->properties['fullName'];
        static::assertSame('string', $fullName->type);
        static::assertSame('public', $fullName->access);

        // hooks should be extracted
        static::assertArrayHasKey('get', $fullName->hooks);
        static::assertArrayHasKey('set', $fullName->hooks);
        static::assertSame('get', $fullName->hooks['get']['name']);
        static::assertSame('set', $fullName->hooks['set']['name']);
        // set hook has a parameter
        static::assertNotEmpty($fullName->hooks['set']['params']);
        static::assertStringContainsString('$value', $fullName->hooks['set']['params'][0]);

        // -- Asymmetric visibility: public private(set) $email --
        static::assertArrayHasKey('email', $class->properties);
        $email = $class->properties['email'];
        static::assertSame('public', $email->access);
        static::assertSame('private', $email->access_set);
        static::assertSame('string', $email->type);

        // -- Asymmetric visibility: public protected(set) $age --
        static::assertArrayHasKey('age', $class->properties);
        $age = $class->properties['age'];
        static::assertSame('public', $age->access);
        static::assertSame('protected', $age->access_set);
        static::assertSame('int', $age->type);

        // -- Regular properties without hooks should have empty hooks --
        static::assertArrayHasKey('first', $class->properties);
        static::assertEmpty($class->properties['first']->hooks);
        static::assertSame('', $class->properties['first']->access_set);
    }

    public function testPropertyHooksFromFileInput(): void
    {
        if (!\class_exists(\PhpParser\Node\PropertyHook::class)) {
            static::markTestSkipped('Property hooks require nikic/php-parser v5');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyPropertyHooks.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey('voku\tests\DummyPropertyHooks', $phpClasses);

        $class = $phpClasses['voku\tests\DummyPropertyHooks'];

        static::assertArrayHasKey('fullName', $class->properties);
        static::assertSame('public', $class->properties['fullName']->access);
        static::assertSame('', $class->properties['fullName']->access_set);
        static::assertArrayHasKey('get', $class->properties['fullName']->hooks);
        static::assertArrayHasKey('set', $class->properties['fullName']->hooks);

        static::assertSame('private', $class->properties['email']->access_set);
        static::assertSame('protected', $class->properties['age']->access_set);
    }

    public function testPromotedPropertyFallbackFromFileInput(): void
    {
        if (!\class_exists(\PhpParser\Node\PropertyHook::class)) {
            static::markTestSkipped('Promoted asymmetric visibility requires nikic/php-parser v5');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyPromotedPropertyHooks.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey('voku\tests\DummyPromotedPropertyHooks', $phpClasses);

        $class = $phpClasses['voku\tests\DummyPromotedPropertyHooks'];

        static::assertArrayHasKey('__construct', $class->methods);
        static::assertArrayHasKey('name', $class->properties);
        static::assertArrayHasKey('age', $class->properties);
        static::assertArrayHasKey('id', $class->properties);

        static::assertSame('public', $class->properties['name']->access);
        static::assertSame('private', $class->properties['name']->access_set);
        static::assertSame('string', $class->properties['name']->type);
        static::assertTrue($class->properties['name']->is_final);
        static::assertArrayHasKey('get', $class->properties['name']->hooks);
        static::assertArrayHasKey('set', $class->properties['name']->hooks);
        static::assertSame('set', $class->properties['name']->hooks['set']['name']);
        static::assertSame('string $value', $class->properties['name']->hooks['set']['params'][0]);
        static::assertSame(
            'voku\tests\DummyPromotedPropertyAttribute',
            $class->properties['name']->attributes[0]->name
        );
        static::assertSame('name', $class->properties['name']->attributes[0]->arguments['name']);

        static::assertSame('public', $class->properties['age']->access);
        static::assertSame('protected', $class->properties['age']->access_set);
        static::assertSame(0, $class->properties['age']->defaultValue);
        static::assertSame('int', $class->properties['age']->typeFromDefaultValue);

        static::assertTrue($class->properties['id']->is_readonly);
        static::assertSame('null|string', $class->properties['id']->type);
        static::assertNull($class->properties['id']->defaultValue);
        static::assertSame('null', $class->properties['id']->typeFromDefaultValue);
        static::assertSame(
            'voku\tests\DummyPromotedPropertyAttribute',
            $class->properties['id']->attributes[0]->name
        );

        static::assertArrayHasKey('name', $class->methods['__construct']->parameters);
        static::assertArrayHasKey('age', $class->methods['__construct']->parameters);
        static::assertArrayHasKey('id', $class->methods['__construct']->parameters);
    }

    public function testPromotedPropertyDefaultsFromAutoloadedFileInput(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyPromotedPropertyDefaults.php');
        $phpClasses = $phpCode->getClasses();

        static::assertArrayHasKey('voku\tests\DummyPromotedPropertyDefaults', $phpClasses);

        $class = $phpClasses['voku\tests\DummyPromotedPropertyDefaults'];

        static::assertArrayHasKey('age', $class->properties);
        static::assertSame(0, $class->properties['age']->defaultValue);
        static::assertSame('int', $class->properties['age']->typeFromDefaultValue);
        static::assertSame(
            'voku\tests\DummyPromotedDefaultAttribute',
            $class->properties['age']->attributes[0]->name
        );
        static::assertSame('age', $class->properties['age']->attributes[0]->arguments['name']);

        static::assertArrayHasKey('id', $class->properties);
        static::assertTrue($class->properties['id']->is_readonly);
        static::assertNull($class->properties['id']->defaultValue);
        static::assertSame('null', $class->properties['id']->typeFromDefaultValue);
        static::assertSame(
            'voku\tests\DummyPromotedDefaultAttribute',
            $class->properties['id']->attributes[0]->name
        );
        static::assertSame('id', $class->properties['id']->attributes[0]->arguments['name']);
    }
}
