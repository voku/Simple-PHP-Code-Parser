<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast;

use Closure;
use PhpParser\Parser;
use Throwable;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Strategy\NodeToReflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

/**
 * @internal
 */
final class Locator
{
    /**
     * @var FindReflectionsInTree
     */
    private $findReflectionsInTree;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param Parser  $parser
     * @param Closure $functionReflectorGetter
     *
     * @psalm-param Closure(): FunctionReflector $functionReflectorGetter
     */
    public function __construct(Parser $parser, Closure $functionReflectorGetter)
    {
        $this->findReflectionsInTree = new FindReflectionsInTree(new NodeToReflection(), $functionReflectorGetter);

        $this->parser = $parser;
    }

    /**
     * @param Reflector     $reflector
     * @param LocatedSource $locatedSource
     * @param Identifier    $identifier
     *
     * @throws IdentifierNotFound
     * @throws Exception\ParseToAstFailure
     *
     * @return Reflection
     */
    public function findReflection(
        Reflector $reflector,
        LocatedSource $locatedSource,
        Identifier $identifier
    ): Reflection {
        return $this->findInArray(
            $this->findReflectionsOfType(
                $reflector,
                $locatedSource,
                $identifier->getType()
            ),
            $identifier
        );
    }

    /**
     * Get an array of reflections found in some code.
     *
     * @param Reflector      $reflector
     * @param LocatedSource  $locatedSource
     * @param IdentifierType $identifierType
     *
     * @throws Exception\ParseToAstFailure
     *
     * @return Reflection[]
     */
    public function findReflectionsOfType(
        Reflector $reflector,
        LocatedSource $locatedSource,
        IdentifierType $identifierType
    ): array {
        try {
            return $this->findReflectionsInTree->__invoke(
                $reflector,
                $this->parser->parse($locatedSource->getSource()),
                $identifierType,
                $locatedSource
            );
        } catch (Throwable $exception) {
            throw Exception\ParseToAstFailure::fromLocatedSource($locatedSource, $exception);
        }
    }

    /**
     * Given an array of Reflections, try to find the identifier.
     *
     * @param Reflection[] $reflections
     * @param Identifier   $identifier
     *
     * @throws IdentifierNotFound
     *
     * @return Reflection
     */
    private function findInArray(array $reflections, Identifier $identifier): Reflection
    {
        $identifierName = \strtolower($identifier->getName());

        foreach ($reflections as $reflection) {
            if (\strtolower($reflection->getName()) === $identifierName) {
                return $reflection;
            }
        }

        throw IdentifierNotFound::fromIdentifier($identifier);
    }
}
