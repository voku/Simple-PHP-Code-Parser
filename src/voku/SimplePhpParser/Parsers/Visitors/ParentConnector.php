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

    /**
     * @param array $nodes
     *
     * @return void
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->stack = [];
    }

    /**
     * @param Node $node
     *
     * @return void
     */
    public function enterNode(Node $node): void
    {
        if (!empty($this->stack)) {
            $node->setAttribute('parent', $this->stack[\count($this->stack) - 1]);
        }
        $this->stack[] = $node;
    }

    /**
     * @param Node $node
     *
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        \array_pop($this->stack);
    }
}
