<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;

interface SourceLocator
{
    /**
     * Locate some source code.
     *
     * This method should return a LocatedSource value object or `null` if the
     * SourceLocator is unable to locate the source.
     *
     * NOTE: A SourceLocator should *NOT* throw an exception if it is unable to
     * locate the identifier, it should simply return null. If an exception is
     * thrown, it will break the Generic Reflector.
     */
    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection;

    /**
     * Find all identifiers of a type
     *
     * @return Reflection[]
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array;
}
