<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Visitors;

use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;
use phpDocumentor\Reflection\Types\Context;

/**
 * Attaches the namespace and class-import context required by phpDocumentor
 * to every AST node after ParentConnector has established scope ownership.
 */
final class PhpDocContextConnector extends NodeVisitorAbstract
{
    private Context $globalContext;

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->globalContext = $this->context('', $nodes);

        return null;
    }

    public function enterNode(Node $node): Node
    {
        $parent = $node->getAttribute('parent');
        $context = $parent instanceof Node
            ? $parent->getAttribute('phpDocContext', $this->globalContext)
            : $this->globalContext;

        if ($node instanceof Namespace_) {
            $context = $this->context($node->name?->toString() ?? '', $node->stmts);
        }

        \assert($context instanceof Context);
        $node->setAttribute('phpDocContext', $context);

        return $node;
    }

    /**
     * @param array<int, Node> $statements
     */
    private function context(string $namespace, array $statements): Context
    {
        $aliases = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Use_) {
                $this->addAliases($aliases, $statement->uses, $statement->type);

                continue;
            }

            if ($statement instanceof GroupUse) {
                $this->addAliases($aliases, $statement->uses, $statement->type, $statement->prefix->toString());
            }
        }

        return new Context($namespace, $aliases);
    }

    /**
     * @param array<string, string>                 $aliases
     * @param array<int, \PhpParser\Node\UseItem> $uses
     */
    private function addAliases(array &$aliases, array $uses, int $parentType, string $prefix = ''): void
    {
        foreach ($uses as $use) {
            $type = $use->type === Use_::TYPE_UNKNOWN ? $parentType : $use->type;
            if ($type !== Use_::TYPE_NORMAL) {
                continue;
            }

            $name = $use->name->toString();
            if ($prefix !== '') {
                $name = $prefix . '\\' . $name;
            }

            $aliases[$use->getAlias()->toString()] = $name;
        }
    }
}
