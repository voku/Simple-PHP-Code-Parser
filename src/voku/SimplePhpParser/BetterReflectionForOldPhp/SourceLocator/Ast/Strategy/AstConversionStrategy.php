<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Strategy;

use PhpParser\Node;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

/**
 * @internal
 */
interface AstConversionStrategy
{
    /**
     * Take an AST node in some located source (potentially in a namespace) and
     * convert it to something (concrete implementation decides)
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
    ): ?Reflection;
}
