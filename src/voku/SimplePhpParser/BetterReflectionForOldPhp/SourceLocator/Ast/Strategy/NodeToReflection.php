<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Strategy;

use PhpParser\Node;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\InvalidConstantNode;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionConstant;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

/**
 * @internal
 */
final class NodeToReflection implements AstConversionStrategy
{
    /**
     * Take an AST node in some located source (potentially in a namespace) and
     * convert it to a Reflection
     *
     * @param Reflector                 $reflector
     * @param Node                      $node
     * @param LocatedSource             $locatedSource
     * @param Node\Stmt\Namespace_|null $namespace
     * @param int|null                  $positionInNode
     *
     * @return Reflection|null
     */
    public function __invoke(
        Reflector $reflector,
        Node $node,
        LocatedSource $locatedSource,
        ?Node\Stmt\Namespace_ $namespace,
        ?int $positionInNode = null
    ): ?Reflection {
        if ($node instanceof Node\Stmt\ClassLike) {
            return ReflectionClass::createFromNode(
                $reflector,
                $node,
                $locatedSource,
                $namespace
            );
        }

        if ($node instanceof Node\Stmt\ClassMethod
            || $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Expr\Closure
        ) {
            return ReflectionFunction::createFromNode(
                $reflector,
                $node,
                $locatedSource,
                $namespace
            );
        }

        if ($node instanceof Node\Stmt\Const_) {
            return ReflectionConstant::createFromNode($reflector, $node, $locatedSource, $namespace, $positionInNode);
        }

        if ($node instanceof Node\Expr\FuncCall) {
            try {
                return ReflectionConstant::createFromNode($reflector, $node, $locatedSource);
            } catch (InvalidConstantNode $e) {
                // Ignore
            }
        }

        return null;
    }
}
