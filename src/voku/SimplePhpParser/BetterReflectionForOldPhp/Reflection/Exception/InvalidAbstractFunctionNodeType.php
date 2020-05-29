<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use InvalidArgumentException;
use PhpParser\Node;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunctionAbstract;

class InvalidAbstractFunctionNodeType extends InvalidArgumentException
{
    /**
     * @param Node $node
     *
     * @return static
     */
    public static function fromNode(Node $node): self
    {
        return new self(\sprintf(
            'Node for "%s" must be "%s" or "%s", was a "%s"',
            ReflectionFunctionAbstract::class,
            Node\Stmt\ClassMethod::class,
            Node\FunctionLike::class,
            \get_class($node)
        ));
    }
}
