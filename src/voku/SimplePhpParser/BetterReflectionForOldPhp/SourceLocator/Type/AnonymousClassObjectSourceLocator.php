<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use ReflectionClass as CoreReflectionClass;
use ReflectionException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Exception\ParseToAstFailure;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Strategy\NodeToReflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\EvaledAnonymousClassCannotBeLocated;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\TwoAnonymousClassesOnSameLine;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\FileChecker;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

/**
 * @internal
 */
final class AnonymousClassObjectSourceLocator implements SourceLocator
{
    /**
     * @var CoreReflectionClass
     */
    private $coreClassReflection;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param mixed $anonymousClassObject
     *
     * @throws ReflectionException
     */

    /**
     * AnonymousClassObjectSourceLocator constructor.
     *
     * @param \object $anonymousClassObject
     * @param Parser  $parser
     *
     * @throws ReflectionException
     */
    public function __construct($anonymousClassObject, Parser $parser)
    {
        $this->coreClassReflection = new CoreReflectionClass($anonymousClassObject);
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParseToAstFailure
     */
    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        return $this->getReflectionClass($reflector, $identifier->getType());
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParseToAstFailure
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return \array_filter([$this->getReflectionClass($reflector, $identifierType)]);
    }

    private function getReflectionClass(Reflector $reflector, IdentifierType $identifierType): ?ReflectionClass
    {
        if (!$identifierType->isClass()) {
            return null;
        }

        $fileName = $this->coreClassReflection->getFileName();

        if (\strpos($fileName, 'eval()\'d code') !== false) {
            throw EvaledAnonymousClassCannotBeLocated::create();
        }

        FileChecker::assertReadableFile($fileName);

        $fileName = FileHelper::normalizeWindowsPath($fileName);

        $nodeVisitor = new class($fileName, $this->coreClassReflection->getStartLine()) extends NodeVisitorAbstract {
            /**
             * @var string
             */
            private $fileName;

            /**
             * @var int
             */
            private $startLine;

            /**
             * @var Class_[]
             */
            private $anonymousClassNodes = [];

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
                if (!($node instanceof Node\Stmt\Class_) || $node->name !== null) {
                    return null;
                }

                $this->anonymousClassNodes[] = $node;

                return null;
            }

            public function getAnonymousClassNode(): ?Class_
            {
                /** @var Class_[] $anonymousClassNodesOnSameLine */
                $anonymousClassNodesOnSameLine = \array_values(\array_filter($this->anonymousClassNodes, function (Class_ $node): bool {
                    return $node->getLine() === $this->startLine;
                }));

                if (!$anonymousClassNodesOnSameLine) {
                    return null;
                }

                if (isset($anonymousClassNodesOnSameLine[1])) {
                    throw TwoAnonymousClassesOnSameLine::create($this->fileName, $this->startLine);
                }

                return $anonymousClassNodesOnSameLine[0];
            }
        };

        $fileContents = \file_get_contents($fileName);
        $ast = $this->parser->parse($fileContents);

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nodeVisitor);
        $nodeTraverser->traverse($ast);

        $reflectionClass = (new NodeToReflection())->__invoke(
            $reflector,
            $nodeVisitor->getAnonymousClassNode(),
            new LocatedSource($fileContents, $fileName),
            null
        );
        \assert($reflectionClass instanceof ReflectionClass || $reflectionClass === null);

        return $reflectionClass;
    }
}
