<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPEnum;
use voku\SimplePhpParser\Model\PHPFileInfo;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Model\PHPTrait;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * Covers the public entry points of the library end to end, so that a change
 * to one of them cannot silently break a consumer.
 *
 * @internal
 */
final class ParserEntryPointsTest extends TestCase
{
    public function testGetFromStringAndGetPhpFilesAgreeOnTheSameSource(): void
    {
        $file = __DIR__ . '/Dummy.php';
        $code = \file_get_contents($file);
        static::assertIsString($code);

        $fromFile = PhpCodeParser::getPhpFiles($file)->getClasses();
        $fromString = PhpCodeParser::getFromString($code)->getClasses();

        static::assertSame(\array_keys($fromFile), \array_keys($fromString));

        foreach ($fromFile as $className => $class) {
            static::assertSame(
                \array_keys($class->methods),
                \array_keys($fromString[$className]->methods),
                $className
            );
            static::assertSame(
                \array_keys($class->properties),
                \array_keys($fromString[$className]->properties),
                $className
            );
        }
    }

    public function testGetFromClassNameReturnsTheClass(): void
    {
        $container = PhpCodeParser::getFromClassName(Dummy::class);

        static::assertArrayHasKey(Dummy::class, $container->getClasses());
        static::assertInstanceOf(PHPClass::class, $container->getClass(Dummy::class));
        static::assertNull($container->getClass('Not\\A\\Class'));
    }

    public function testGetAstFromFileAndGetAstFromStringAgree(): void
    {
        $file = __DIR__ . '/Dummy4.php';
        $code = \file_get_contents($file);
        static::assertIsString($code);

        $astFromFile = PhpCodeParser::getAstFromFile($file);
        $astFromString = PhpCodeParser::getAstFromString($code);

        static::assertNotEmpty($astFromFile);
        static::assertCount(\count($astFromString), $astFromFile);

        foreach ($astFromFile as $index => $node) {
            static::assertInstanceOf(\PhpParser\Node::class, $node);
            static::assertSame($astFromString[$index]->getType(), $node->getType());
        }
    }

    public function testGetAstResolvesNamesAndKeepsFilePositions(): void
    {
        $ast = PhpCodeParser::getAstFromString(
            <<<'PHP'
<?php

namespace AstCheck;

use Vendor\Payload as Message;

class Consumer
{
    public function handle(Message $message): void
    {
    }
}
PHP
        );

        $namespace = $ast[0];
        static::assertInstanceOf(\PhpParser\Node\Stmt\Namespace_::class, $namespace);

        $class = null;
        foreach ($namespace->stmts as $statement) {
            if ($statement instanceof \PhpParser\Node\Stmt\Class_) {
                $class = $statement;
            }
        }

        static::assertNotNull($class);
        static::assertNotNull($class->getAttribute('startFilePos'));

        $method = $class->getMethod('handle');
        static::assertNotNull($method);

        $parameterType = $method->params[0]->type;
        static::assertInstanceOf(\PhpParser\Node\Name::class, $parameterType);
        static::assertSame('Vendor\\Payload', $parameterType->toString());
    }

    public function testGetFileInfoFromFileAndFromStringAgree(): void
    {
        $file = __DIR__ . '/Dummy.php';
        $code = \file_get_contents($file);
        static::assertIsString($code);

        $fromFile = PhpCodeParser::getFileInfoFromFile($file);
        $fromString = PhpCodeParser::getFileInfoFromString($code);

        static::assertInstanceOf(PHPFileInfo::class, $fromFile);
        static::assertSame($file, $fromFile->file);
        static::assertNull($fromString->file);
        static::assertSame($fromString->namespaces, $fromFile->namespaces);
    }

    public function testFileInfoExposesNamespacesImportsAndDeclares(): void
    {
        $info = PhpCodeParser::getFileInfoFromString(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace FileInfoCheck;

use Vendor\Payload as Message;
use function Vendor\helper;
use const Vendor\SOME_CONST;

class Consumer
{
}
PHP
        );

        // the `declare()` before the namespace lives in its own global scope entry
        static::assertCount(2, $info->namespaces);
        static::assertSame('', $info->namespaces[0]['name']);
        static::assertSame(['strict_types' => 1], $info->namespaces[0]['declares']);

        $namespace = $info->namespaces[1];
        static::assertSame('FileInfoCheck', $namespace['name']);
        static::assertSame([], $namespace['declares']);
        static::assertSame(5, $namespace['line']);

        $imports = [];
        foreach ($namespace['imports'] as $import) {
            $imports[$import['name']] = [$import['alias'], $import['kind']];
        }

        static::assertSame(['Message', 'class'], $imports['Vendor\\Payload']);
        static::assertSame(['helper', 'function'], $imports['Vendor\\helper']);
        static::assertSame(['SOME_CONST', 'const'], $imports['Vendor\\SOME_CONST']);
    }

    public function testContainerReturnsEveryKindOfSymbol(): void
    {
        $container = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace ContainerCheck;

interface Contract {}

trait Helper {}

enum State: string { case On = 'on'; }

class Service implements Contract { use Helper; }

function helper(): void {}

const SOME_CONST = 1;
PHP
        );

        static::assertInstanceOf(PHPClass::class, $container->getClass('ContainerCheck\\Service'));
        static::assertInstanceOf(PHPInterface::class, $container->getInterface('ContainerCheck\\Contract'));
        static::assertInstanceOf(PHPTrait::class, $container->getTrait('ContainerCheck\\Helper'));
        static::assertInstanceOf(PHPEnum::class, $container->getEnum('ContainerCheck\\State'));
        static::assertInstanceOf(PHPFunction::class, $container->getFunctions()['ContainerCheck\\helper']);
        // namespaced constants are keyed with a leading backslash
        static::assertInstanceOf(PHPConst::class, $container->getConstants()['\\ContainerCheck\\SOME_CONST']);

        static::assertNull($container->getClass('ContainerCheck\\Missing'));
        static::assertNull($container->getInterface('ContainerCheck\\Missing'));
        static::assertNull($container->getTrait('ContainerCheck\\Missing'));
        static::assertNull($container->getEnum('ContainerCheck\\Missing'));

        static::assertSame([], $container->getParseErrors());
    }

    public function testContainerSettersAndReferenceAccessorRoundTrip(): void
    {
        $source = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace ContainerRoundTrip;

interface Contract {}

trait Helper {}

enum State: string { case On = 'on'; }

class Service {}

function helper(): void {}

const SOME_CONST = 1;
PHP
        );

        $target = new ParserContainer();
        $target->setClasses($source->getClasses());
        $target->setInterfaces($source->getInterfaces());
        $target->setTraits($source->getTraits());
        $target->setEnums($source->getEnums());
        $target->setFunctions($source->getFunctions());
        $target->setConstants($source->getConstants());

        static::assertSame(\array_keys($source->getClasses()), \array_keys($target->getClasses()));
        static::assertSame(\array_keys($source->getInterfaces()), \array_keys($target->getInterfaces()));
        static::assertSame(\array_keys($source->getTraits()), \array_keys($target->getTraits()));
        static::assertSame(\array_keys($source->getEnums()), \array_keys($target->getEnums()));
        static::assertSame(\array_keys($source->getFunctions()), \array_keys($target->getFunctions()));
        static::assertSame(\array_keys($source->getConstants()), \array_keys($target->getConstants()));

        $byReference = &$target->getClassesByReference();
        unset($byReference['ContainerRoundTrip\\Service']);

        static::assertSame([], $target->getClasses());
    }

    public function testFunctionsInfoIsStillShaped(): void
    {
        $container = PhpCodeParser::getFromString(
            <<<'PHP'
<?php

namespace FunctionsInfoCheck;

/**
 * Summary here.
 *
 * @param int $number a number
 *
 * @return string
 */
function withDoc(int $number): string
{
    return (string) $number;
}

/**
 * @deprecated
 */
function deprecatedOne(): void {}

function _underscored(): void {}
PHP
        );

        $all = $container->getFunctionsInfo();
        static::assertArrayHasKey('FunctionsInfoCheck\\withDoc', $all);
        static::assertArrayHasKey('FunctionsInfoCheck\\deprecatedOne', $all);

        $info = $all['FunctionsInfoCheck\\withDoc'];
        static::assertStringContainsString('Summary here.', $info['fullDescription']);
        static::assertSame('int', $info['paramsTypes']['number']['type']);
        static::assertSame('int', $info['paramsTypes']['number']['typeFromPhpDoc']);
        static::assertSame('string', $info['returnTypes']['type']);
        static::assertFalse($info['is_deprecated']);
        static::assertSame('', $info['error']);

        static::assertTrue($all['FunctionsInfoCheck\\deprecatedOne']['is_deprecated']);

        $filtered = $container->getFunctionsInfo(true, false);
        static::assertArrayNotHasKey('FunctionsInfoCheck\\deprecatedOne', $filtered);
    }

    public function testMethodsAndPropertiesInfoAreStillShaped(): void
    {
        $class = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php')->getClasses()[Dummy::class];

        $methodsInfo = $class->getMethodsInfo();
        static::assertNotEmpty($methodsInfo);
        foreach ($methodsInfo as $methodName => $info) {
            static::assertArrayHasKey('fullDescription', $info, $methodName);
            static::assertArrayHasKey('paramsTypes', $info, $methodName);
            static::assertArrayHasKey('returnTypes', $info, $methodName);
            static::assertArrayHasKey('line', $info, $methodName);
            static::assertArrayHasKey('file', $info, $methodName);
        }

        $propertiesInfo = $class->getPropertiesInfo();
        foreach ($propertiesInfo as $propertyName => $info) {
            static::assertArrayHasKey('type', $info, $propertyName);
            static::assertArrayHasKey('typeFromPhpDoc', $info, $propertyName);
        }
    }

    public function testBrokenCodeIsReportedInsteadOfThrown(): void
    {
        $container = PhpCodeParser::getFromString('<?php class Broken { function foo(] {} }');

        static::assertNotSame([], $container->getParseErrors());
    }

    public function testUnreadableFileIsReportedInsteadOfThrown(): void
    {
        $container = PhpCodeParser::getPhpFiles(__DIR__ . '/does-not-exist-' . \uniqid('', false) . '.php');

        static::assertSame([], $container->getClasses());
    }
}
