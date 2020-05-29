<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Exception\ParseToAstFailure;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator as AstLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

abstract class AbstractSourceLocator implements SourceLocator
{
    /**
     * @var AstLocator
     */
    private $astLocator;

    public function __construct(AstLocator $astLocator)
    {
        $this->astLocator = $astLocator;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParseToAstFailure
     */
    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        $locatedSource = $this->createLocatedSource($identifier);

        if (!$locatedSource) {
            return null;
        }

        try {
            return $this->astLocator->findReflection($reflector, $locatedSource, $identifier);
        } catch (IdentifierNotFound $exception) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParseToAstFailure
     */
    final public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        $locatedSource = $this->createLocatedSource(new Identifier(Identifier::WILDCARD, $identifierType));

        if (!$locatedSource) {
            return [];
        }

        return $this->astLocator->findReflectionsOfType(
            $reflector,
            $locatedSource,
            $identifierType
        );
    }

    /**
     * Children should implement this method and return a LocatedSource object
     * which contains the source and the file from which it was located.
     *
     * @example
     *   return new LocatedSource(['<?php class Foo {}', null]);
     *   return new LocatedSource([\file_get_contents('Foo.php'), 'Foo.php']);
     */
    abstract protected function createLocatedSource(Identifier $identifier): ?LocatedSource;
}
