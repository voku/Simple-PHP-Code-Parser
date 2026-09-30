<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

/**
 * Preserves an attribute argument expression that cannot be evaluated without
 * non-local runtime context.
 *
 * The expression is rendered from the names-resolved AST and therefore keeps
 * semantic identity, not the original whitespace or formatting.
 */
final class PHPAttributeExpression
{
    public readonly string $expression;

    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }
}
