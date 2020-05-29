<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\InvalidConstantNode;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\FunctionReflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Strategy\AstConversionStrategy;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\ConstantNodeChecker;

/**
 * @internal
 */
final class FindReflectionsInTree
{
    /**
     * @var AstConversionStrategy
     */
    private $astConversionStrategy;

    /**
     * @var FunctionReflector
     */
    private $functionReflector;

    /**
     * @var Closure
     *
     * @psalm-var Closure(): FunctionReflector
     */
    private $functionReflectorGetter;

    /**
     * @param Closure(): FunctionReflector $functionReflectorGetter
     */
    public function __construct(AstConversionStrategy $astConversionStrategy, Closure $functionReflectorGetter)
    {
        $this->astConversionStrategy = $astConversionStrategy;
        $this->functionReflectorGetter = $functionReflectorGetter;
    }

    /**
     * Find all reflections of a given type in an Abstract Syntax Tree
     *
     * @param Node[] $ast
     *
     * @return Reflection[]
     */
    public function __invoke(
        Reflector $reflector,
        array $ast,
        IdentifierType $identifierType,
        LocatedSource $locatedSource
    ): array {
        $nodeVisitor = new class($reflector, $identifierType, $locatedSource, $this->astConversionStrategy, $this->functionReflectorGetter->__invoke())
            extends NodeVisitorAbstract {
            /**
             * @var Reflection[]
             */
            private $reflections = [];

            /**
             * @var Reflector
             */
            private $reflector;

            /**
             * @var IdentifierType
             */
            private $identifierType;

            /**
             * @var LocatedSource
             */
            private $locatedSource;

            /**
             * @var AstConversionStrategy
             */
            private $astConversionStrategy;

            /**
             * @var Namespace_|null
             */
            private $currentNamespace;

            /**
             * @var FunctionReflector
             */
            private $functionReflector;

            public function __construct(
                Reflector $reflector,
                IdentifierType $identifierType,
                LocatedSource $locatedSource,
                AstConversionStrategy $astConversionStrategy,
                FunctionReflector $functionReflector
            ) {
                $this->reflector = $reflector;
                $this->identifierType = $identifierType;
                $this->locatedSource = $locatedSource;
                $this->astConversionStrategy = $astConversionStrategy;
                $this->functionReflector = $functionReflector;
            }

            /**
             * {@inheritdoc}
             */
            public function enterNode(Node $node)
            {
                if ($node instanceof Namespace_) {
                    $this->currentNamespace = $node;

                    return null;
                }

                if ($node instanceof Node\Stmt\ClassLike) {
                    $classNamespace = $node->name === null ? null : $this->currentNamespace;
                    $reflection = $this->astConversionStrategy->__invoke($this->reflector, $node, $this->locatedSource, $classNamespace);

                    if ($this->identifierType->isMatchingReflector($reflection)) {
                        $this->reflections[] = $reflection;
                    }

                    return null;
                }

                if ($node instanceof Node\Stmt\ClassConst) {
                    return null;
                }

                if ($node instanceof Node\Stmt\Const_) {
                    for ($i = 0; $i < \count($node->consts); ++$i) {
                        $reflection = $this->astConversionStrategy->__invoke($this->reflector, $node, $this->locatedSource, $this->currentNamespace, $i);

                        if (!$this->identifierType->isMatchingReflector($reflection)) {
                            continue;
                        }

                        $this->reflections[] = $reflection;
                    }

                    return null;
                }

                if ($node instanceof Node\Expr\FuncCall) {
                    try {
                        ConstantNodeChecker::assertValidDefineFunctionCall($node);
                    } catch (InvalidConstantNode $e) {
                        return null;
                    }

                    if ($node->name->hasAttribute('namespacedName')) {
                        $namespacedName = $node->name->getAttribute('namespacedName');
                        \assert($namespacedName instanceof Name);
                        if (\count($namespacedName->parts) > 1) {
                            try {
                                $this->functionReflector->reflect($namespacedName->toString());

                                return null;
                            } catch (IdentifierNotFound $e) {
                                // Global define()
                            }
                        }
                    }

                    $reflection = $this->astConversionStrategy->__invoke($this->reflector, $node, $this->locatedSource, $this->currentNamespace);

                    if ($this->identifierType->isMatchingReflector($reflection)) {
                        $this->reflections[] = $reflection;
                    }

                    return null;
                }

                if (!($node instanceof Node\Stmt\Function_)) {
                    return null;
                }

                $reflection = $this->astConversionStrategy->__invoke($this->reflector, $node, $this->locatedSource, $this->currentNamespace);

                if (!$this->identifierType->isMatchingReflector($reflection)) {
                    return null;
                }

                $this->reflections[] = $reflection;

                return null;
            }

            /**
             * {@inheritdoc}
             */
            public function leaveNode(Node $node)
            {
                if (!($node instanceof Namespace_)) {
                    return null;
                }

                $this->currentNamespace = null;

                return null;
            }

            /**
             * @return Reflection[]
             */
            public function getReflections(): array
            {
                return $this->reflections;
            }
        };

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new NameResolver());
        $nodeTraverser->addVisitor($nodeVisitor);
        $nodeTraverser->traverse($ast);

        return $nodeVisitor->getReflections();
    }
}
