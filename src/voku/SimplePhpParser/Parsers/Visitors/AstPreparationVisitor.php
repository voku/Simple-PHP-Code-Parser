<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Visitors;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;

/**
 * Single-pass replacement for ParentConnector + PhpDocContextConnector that
 * additionally records, in pre-order, every node ASTVisitor reacts to.
 *
 * Model extraction has to run after name resolution finished, so it cannot
 * share the traversal; but it only ever looks at a handful of node types.
 * Replaying the recorded candidates spares the second full tree walk.
 *
 * @internal
 */
final class AstPreparationVisitor extends NodeVisitorAbstract
{
    /**
     * @var Node[]
     */
    private array $stack = [];

    /**
     * @var \phpDocumentor\Reflection\Types\Context[]
     */
    private array $contexts = [];

    /**
     * @var Node[]
     */
    private array $candidates = [];

    /**
     * @param array<int, Node> $nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->stack = [];
        $this->candidates = [];
        $this->contexts = [PhpDocContextConnector::createContext('', $nodes)];

        return null;
    }

    public function enterNode(Node $node): Node
    {
        $stackCount = \count($this->stack);
        if ($stackCount > 0) {
            $node->setAttribute('parent', $this->stack[$stackCount - 1]);
        }
        $this->stack[] = $node;

        if ($node instanceof Namespace_) {
            $context = PhpDocContextConnector::createContext($node->name?->toString() ?? '', $node->stmts);
        } else {
            $context = $this->contexts[\count($this->contexts) - 1];
        }
        $this->contexts[] = $context;
        $node->setAttribute('phpDocContext', $context);

        if (
            $node instanceof Class_
            || $node instanceof Function_
            || $node instanceof Interface_
            || $node instanceof Trait_
            || $node instanceof Enum_
            || $node instanceof Node\Const_
            || $node instanceof FuncCall
        ) {
            $this->candidates[] = $node;
        }

        return $node;
    }

    /**
     * @return null
     */
    public function leaveNode(Node $node)
    {
        \array_pop($this->stack);
        \array_pop($this->contexts);

        return null;
    }

    /**
     * @return Node[]
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }
}
