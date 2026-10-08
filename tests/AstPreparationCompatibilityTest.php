<?php

declare(strict_types=1);

namespace voku\tests;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use voku\SimplePhpParser\Parsers\PhpCodeParser;
use voku\SimplePhpParser\Parsers\Visitors\ParentConnector;
use voku\SimplePhpParser\Parsers\Visitors\PhpDocContextConnector;

final class AstPreparationCompatibilityTest extends TestCase
{
    public function testPublicAstPreservesLegacyParentsAndImportContexts(): void
    {
        $source = <<<'PHP'
<?php
namespace First {
    use Vendor\Direct as Alias;
    use Vendor\Grouped\{Item, Other as Renamed, function helper, const VALUE};
    use function Vendor\functionOnly;
    use const Vendor\CONSTANT_ONLY;

    /** @return Alias */
    function outer(): Alias {
        function nested(Item $item): Renamed { return new Renamed(); }
        return new Alias();
    }
    class Example {
        public const ITEM = Alias::class;
        /** @var Item */
        public $item;
        public function value(): Item { return new Item(); }
    }
}
namespace Second {
    use Other\Direct as Alias;
    /** @return Alias */
    function value(): Alias { return new Alias(); }
}
namespace {
    use GlobalVendor\Direct as Alias;
    define('GLOBAL_VALUE', 1);
    $anonymous = new class { public function value(): Alias { return new Alias(); } };
}
PHP;

        $legacy = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        static::assertNotNull($legacy);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnector());
        $traverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true]));
        $traverser->addVisitor(new PhpDocContextConnector());
        $legacy = $traverser->traverse($legacy);

        static::assertSame($this->scopeSnapshot($legacy), $this->scopeSnapshot(PhpCodeParser::getAstFromString($source)));
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, mixed>>
     */
    private function scopeSnapshot(array $nodes): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function enterNode(Node $node): ?Node
            {
                $parent = $node->getAttribute('parent');
                $context = $node->getAttribute('phpDocContext');
                $this->rows[] = [
                    'type' => $node->getType(),
                    'position' => $node->getStartFilePos(),
                    'parent' => $parent instanceof Node ? [$parent->getType(), $parent->getStartFilePos()] : null,
                    'namespace' => $context->getNamespace(),
                    'aliases' => $context->getNamespaceAliases(),
                    'name' => $node instanceof Node\Name ? $node->toString() : null,
                ];

                return null;
            }
        };
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);

        return $visitor->rows;
    }
}
