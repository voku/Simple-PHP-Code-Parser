<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SourceLocator;

class ClassReflector implements Reflector
{
    /**
     * @var SourceLocator
     */
    private $sourceLocator;

    public function __construct(SourceLocator $sourceLocator)
    {
        $this->sourceLocator = $sourceLocator;
    }

    /**
     * Create a ReflectionClass for the specified $className.
     *
     * @param string $className
     *
     * @throws IdentifierNotFound
     *
     * @return Reflection
     */
    public function reflect(string $className): Reflection
    {
        $identifier = new Identifier($className, new IdentifierType(IdentifierType::IDENTIFIER_CLASS));

        $classInfo = $this->sourceLocator->locateIdentifier($this, $identifier);
        \assert($classInfo instanceof ReflectionClass || $classInfo === null);

        if ($classInfo === null) {
            throw Exception\IdentifierNotFound::fromIdentifier($identifier);
        }

        return $classInfo;
    }

    /**
     * Get all the classes available in the scope specified by the SourceLocator.
     *
     * @return ReflectionClass[]
     */
    public function getAllClasses(): array
    {
        /** @var ReflectionClass[] $allClasses */
        return $this->sourceLocator->locateIdentifiersByType(
            $this,
            new IdentifierType(IdentifierType::IDENTIFIER_CLASS)
        );
    }
}
