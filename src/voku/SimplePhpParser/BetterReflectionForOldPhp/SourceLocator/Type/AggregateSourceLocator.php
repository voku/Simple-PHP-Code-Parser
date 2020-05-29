<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;

class AggregateSourceLocator implements SourceLocator
{
    /**
     * @var SourceLocator[]
     */
    private $sourceLocators;

    /**
     * @param SourceLocator[] $sourceLocators
     */
    public function __construct(array $sourceLocators = [])
    {
        // This slightly confusing code simply type-checks the $sourceLocators
        // array by unpacking them and splatting them in the closure.
        $validator = static function (SourceLocator ...$sourceLocator): array {
            return $sourceLocator;
        };
        $this->sourceLocators = $validator(...$sourceLocators);
    }

    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        foreach ($this->sourceLocators as $sourceLocator) {
            $located = $sourceLocator->locateIdentifier($reflector, $identifier);

            if ($located) {
                return $located;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return \array_merge(
            [],
            ...\array_map(static function (SourceLocator $sourceLocator) use ($reflector, $identifierType) {
                return $sourceLocator->locateIdentifiersByType($reflector, $identifierType);
            }, $this->sourceLocators)
        );
    }
}
