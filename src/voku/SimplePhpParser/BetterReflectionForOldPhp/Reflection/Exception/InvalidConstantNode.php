<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

class InvalidConstantNode extends RuntimeException
{
    /**
     * @param Node $node
     *
     * @return static
     */
    public static function create(Node $node): self
    {
        return new self(\sprintf(
            'Invalid constant node (first 50 characters: %s)',
            \substr((new Standard())->prettyPrint([$node]), 0, 50)
        ));
    }
}
