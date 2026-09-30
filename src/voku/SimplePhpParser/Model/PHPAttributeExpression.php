<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

/**
 * Preserves an attribute argument expression that cannot be evaluated without external context.
 *
 * The expression is rendered from the names-resolved AST and deliberately contains no live AST
 * node references, so the compact model remains safe to inspect and serialize.
 */
final class PHPAttributeExpression
{
    public readonly string $expression;

    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }
}
