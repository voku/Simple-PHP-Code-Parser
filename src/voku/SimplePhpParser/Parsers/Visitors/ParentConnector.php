<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * The visitor is required to provide "parent" attribute to nodes
 */
final class ParentConnector extends NodeVisitorAbstract
{
    /**
     * @var Node[]
     */
    private $stack;

    public function __construct()
    {
        $this->stack = [];
    }

    /**
     * @param \PhpParser\Node[] $nodes
     *
     * @return void
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->stack = [];
    }

    /**
     * @param \PhpParser\Node $node
     *
     * @return int|\PhpParser\Node|null
     */
    public function enterNode(Node $node)
    {
        $stackCount = \count($this->stack);
        if ($stackCount > 0) {
            $node->setAttribute('parent', $this->stack[$stackCount - 1]);
        }

        $this->stack[] = $node;

        return $node;
    }

    /**
     * @param \PhpParser\Node $node
     *
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        \array_pop($this->stack);
    }
}
