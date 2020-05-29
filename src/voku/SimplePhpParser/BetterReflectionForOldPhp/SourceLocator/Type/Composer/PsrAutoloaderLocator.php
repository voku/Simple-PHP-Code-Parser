<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\Composer;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\Composer\Psr\PsrAutoloaderMapping;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\DirectoriesSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SourceLocator;

final class PsrAutoloaderLocator implements SourceLocator
{
    /**
     * @var PsrAutoloaderMapping
     */
    private $mapping;

    /**
     * @var Locator
     */
    private $astLocator;

    public function __construct(PsrAutoloaderMapping $mapping, Locator $astLocator)
    {
        $this->mapping = $mapping;
        $this->astLocator = $astLocator;
    }

    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        foreach ($this->mapping->resolvePossibleFilePaths($identifier) as $file) {
            if (!\file_exists($file)) {
                continue;
            }

            try {
                return $this->astLocator->findReflection(
                    $reflector,
                    new LocatedSource(
                        \file_get_contents($file),
                        $file
                    ),
                    $identifier
                );
            } catch (IdentifierNotFound $exception) {
                // on purpose - autoloading is allowed to fail, and silently-failing autoloaders are normal/endorsed
            }
        }

        return null;
    }

    /**
     * Find all identifiers of a type
     *
     * @return Reflection[]
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return (new DirectoriesSourceLocator(
            $this->mapping->directories(),
            $this->astLocator
        ))->locateIdentifiersByType($reflector, $identifierType);
    }
}
