<?php

declare(strict_types=1);

namespace voku\tests;

use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * Mistaken or alias-style sources can make a class or interface extend itself, directly or through
 * a partner. Parsing must finish with what the source declares instead of recursing without bound.
 *
 * @internal
 */
final class CyclicInheritanceTest extends TestCase
{
    public function testClassExtendingItselfIsParsed(): void
    {
        $container = $this->parse('<?php namespace Cycle; class Node extends \Cycle\Node {}');

        static::assertArrayHasKey('Cycle\Node', $container->getClasses());
    }

    public function testClassesExtendingEachOtherAreParsedWithTheirInterfaces(): void
    {
        $container = $this->parse(<<<'PHP'
<?php
namespace Cycle;
interface Marker {}
class First extends Second implements Marker {}
class Second extends First {}
PHP);

        $classes = $container->getClasses();
        static::assertSame(['Cycle\Marker'], $classes['Cycle\First']->interfaces);
        static::assertSame(['Cycle\Marker'], $classes['Cycle\Second']->interfaces);
    }

    public function testInterfaceExtendingItselfIsParsed(): void
    {
        $container = $this->parse('<?php namespace Cycle; interface Loop extends \Cycle\Loop {}');

        static::assertArrayHasKey('Cycle\Loop', $container->getInterfaces());
    }

    public function testInterfacesExtendingEachOtherTerminateAndNameBothInterfaces(): void
    {
        $container = $this->parse('<?php namespace Cycle; interface Left extends Right {} interface Right extends Left {}');

        // Parent lists are not de-duplicated (also not for acyclic diamonds); only termination and membership matter.
        $interfaces = $container->getInterfaces();
        static::assertEqualsCanonicalizing(['Cycle\Left', 'Cycle\Right'], \array_unique($interfaces['Cycle\Left']->parentInterfaces));
        static::assertEqualsCanonicalizing(['Cycle\Left', 'Cycle\Right'], \array_unique($interfaces['Cycle\Right']->parentInterfaces));
    }

    public function testAcyclicInheritanceIsUnchanged(): void
    {
        $container = $this->parse(<<<'PHP'
<?php
namespace Plain;
interface Base {}
interface Child extends Base {}
class Parent_ implements Child {}
class Leaf extends Parent_ {}
PHP);

        static::assertSame(['Plain\Base'], $container->getInterfaces()['Plain\Child']->parentInterfaces);
        static::assertEqualsCanonicalizing(['Plain\Child', 'Plain\Base'], $container->getClasses()['Plain\Leaf']->interfaces);
    }

    public function testSelfConstantResolutionWalksUpToTheDeclaringParentAndTerminates(): void
    {
        $container = $this->parse(<<<'PHP'
<?php
namespace Consts;
class Base { public const KNOWN = 1; }
class Child extends Base {}
class Loop extends Loop {}
PHP);

        // Only the declaring-class lookup is under test here, not constant value resolution.
        static::assertSame('\Consts\Base::class', $this->classOf($container, 'Consts\Child', 'KNOWN'));
        static::assertSame('\Consts\Child::class', $this->classOf($container, 'Consts\Child', 'MISSING'));
        static::assertSame('\Consts\Loop::class', $this->classOf($container, 'Consts\Loop', 'MISSING'));
    }

    private function classOf(\voku\SimplePhpParser\Parsers\Helper\ParserContainer $container, string $classStr, string $constant): string
    {
        $method = new \ReflectionMethod(Utils::class, 'findParentClassDeclaringConstant');
        $method->setAccessible(true);

        return '\\' . $method->invoke(null, $classStr, $constant, $container) . '::class';
    }

    private function parse(string $source): \voku\SimplePhpParser\Parsers\Helper\ParserContainer
    {
        return PhpCodeParser::getPhpFiles($source, [], [], [], ParserOptions::astOnly());
    }
}
