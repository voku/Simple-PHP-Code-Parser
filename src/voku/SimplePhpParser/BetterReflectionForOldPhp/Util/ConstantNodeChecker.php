<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util;

use PhpParser\Node;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\InvalidConstantNode;

/**
 * @internal
 */
final class ConstantNodeChecker
{
    /**
     * @param Node\Expr\FuncCall $node
     *
     * @throws InvalidConstantNode
     *
     * @return void
     */
    public static function assertValidDefineFunctionCall(Node\Expr\FuncCall $node): void
    {
        if (!($node->name instanceof Node\Name)) {
            throw InvalidConstantNode::create($node);
        }

        if ($node->name->toLowerString() !== 'define') {
            throw InvalidConstantNode::create($node);
        }

        if (!\in_array(\count($node->args), [2, 3], true)) {
            throw InvalidConstantNode::create($node);
        }

        if (!($node->args[0]->value instanceof Node\Scalar\String_)) {
            throw InvalidConstantNode::create($node);
        }

        $valueNode = $node->args[1]->value;

        if ($valueNode instanceof Node\Expr\FuncCall) {
            throw InvalidConstantNode::create($node);
        }

        if ($valueNode instanceof Node\Expr\Variable) {
            throw InvalidConstantNode::create($node);
        }
    }
}
