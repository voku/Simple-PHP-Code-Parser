<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;

final class MemoizingSourceLocator implements SourceLocator
{
    /**
     * @var SourceLocator
     */
    private $wrappedSourceLocator;

    /**
     * @var null[]|Reflection[] indexed by reflector key and identifier cache key
     */
    private $cacheByIdentifierKeyAndOid = [];

    /**
     * @var Reflection[][] indexed by reflector key and identifier type cache key
     */
    private $cacheByIdentifierTypeKeyAndOid = [];

    public function __construct(SourceLocator $wrappedSourceLocator)
    {
        $this->wrappedSourceLocator = $wrappedSourceLocator;
    }

    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        $cacheKey = $this->reflectorCacheKey($reflector) . '_' . $this->identifierToCacheKey($identifier);

        if (\array_key_exists($cacheKey, $this->cacheByIdentifierKeyAndOid)) {
            return $this->cacheByIdentifierKeyAndOid[$cacheKey];
        }

        return $this->cacheByIdentifierKeyAndOid[$cacheKey]
            = $this->wrappedSourceLocator->locateIdentifier($reflector, $identifier);
    }

    /**
     * @return Reflection[]
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        $cacheKey = $this->reflectorCacheKey($reflector) . '_' . $this->identifierTypeToCacheKey($identifierType);

        if (\array_key_exists($cacheKey, $this->cacheByIdentifierTypeKeyAndOid)) {
            return $this->cacheByIdentifierTypeKeyAndOid[$cacheKey];
        }

        return $this->cacheByIdentifierTypeKeyAndOid[$cacheKey]
            = $this->wrappedSourceLocator->locateIdentifiersByType($reflector, $identifierType);
    }

    private function reflectorCacheKey(Reflector $reflector): string
    {
        return 'type:' . \get_class($reflector)
            . '#oid:' . \spl_object_hash($reflector);
    }

    private function identifierToCacheKey(Identifier $identifier): string
    {
        return $this->identifierTypeToCacheKey($identifier->getType())
            . '#name:' . $identifier->getName();
    }

    private function identifierTypeToCacheKey(IdentifierType $identifierType): string
    {
        return 'type:' . $identifierType->getName();
    }
}
