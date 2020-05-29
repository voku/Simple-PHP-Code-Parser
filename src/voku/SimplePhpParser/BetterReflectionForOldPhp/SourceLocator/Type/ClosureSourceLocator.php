<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use ReflectionFunction as CoreFunctionReflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Exception\ParseToAstFailure;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Strategy\NodeToReflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\EvaledClosureCannotBeLocated;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\TwoClosuresOnSameLine;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\FileChecker;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

/**
 * @internal
 */
final class ClosureSourceLocator implements SourceLocator
{
    /**
     * @var CoreFunctionReflection
     */
    private $coreFunctionReflection;

    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Closure $closure, Parser $parser)
    {
        $this->coreFunctionReflection = new CoreFunctionReflection($closure);
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParseToAstFailure
     */
    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        return $this->getReflectionFunction($reflector, $identifier->getType());
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParseToAstFailure
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return \array_filter([$this->getReflectionFunction($reflector, $identifierType)]);
    }

    private function getReflectionFunction(Reflector $reflector, IdentifierType $identifierType): ?ReflectionFunction
    {
        if (!$identifierType->isFunction()) {
            return null;
        }

        $fileName = $this->coreFunctionReflection->getFileName();

        if (\strpos($fileName, 'eval()\'d code') !== false) {
            throw EvaledClosureCannotBeLocated::create();
        }

        FileChecker::assertReadableFile($fileName);

        $fileName = FileHelper::normalizeWindowsPath($fileName);

        $nodeVisitor = new class($fileName, $this->coreFunctionReflection->getStartLine()) extends NodeVisitorAbstract {
            /**
             * @var string
             */
            private $fileName;

            /**
             * @var int
             */
            private $startLine;

            /**
             * @var array
             *
             * @psalm-var (Node|null)[][]
             */
            private $closureNodes = [];

            /**
             * @var Namespace_|null
             */
            private $currentNamespace;

            public function __construct(string $fileName, int $startLine)
            {
                $this->fileName = $fileName;
                $this->startLine = $startLine;
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

                if (!($node instanceof Node\Expr\Closure)) {
                    return null;
                }

                $this->closureNodes[] = [$node, $this->currentNamespace];

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
             * @throws TwoClosuresOnSameLine
             *
             * @return Node[]|null[]|null
             */
            public function getClosureNodes(): ?array
            {
                /** @var (Node|null)[][] $closureNodesDataOnSameLine */
                $closureNodesDataOnSameLine = \array_values(\array_filter($this->closureNodes, function (array $nodes): bool {
                    return $nodes[0]->getLine() === $this->startLine;
                }));

                if (!$closureNodesDataOnSameLine) {
                    return null;
                }

                if (isset($closureNodesDataOnSameLine[1])) {
                    throw TwoClosuresOnSameLine::create($this->fileName, $this->startLine);
                }

                return $closureNodesDataOnSameLine[0];
            }
        };

        $fileContents = \file_get_contents($fileName);
        $ast = $this->parser->parse($fileContents);

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nodeVisitor);
        $nodeTraverser->traverse($ast);

        $closureNodes = $nodeVisitor->getClosureNodes();
        \assert(\is_array($closureNodes));
        \assert($closureNodes[1] instanceof Namespace_ || $closureNodes[1] === null);

        $reflectionFunction = (new NodeToReflection())->__invoke(
            $reflector,
            $closureNodes[0],
            new LocatedSource($fileContents, $fileName),
            $closureNodes[1]
        );
        \assert($reflectionFunction instanceof ReflectionFunction || $reflectionFunction === null);

        return $reflectionFunction;
    }
}
