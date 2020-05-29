<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SourceLocator;

class FunctionReflector implements Reflector
{
    /**
     * @var SourceLocator
     */
    private $sourceLocator;

    /**
     * @var ClassReflector
     */
    private $classReflector;

    public function __construct(SourceLocator $sourceLocator, ClassReflector $classReflector)
    {
        $this->sourceLocator = $sourceLocator;
        $this->classReflector = $classReflector;
    }

    /**
     * Create a ReflectionFunction for the specified $functionName.
     *
     * @throws IdentifierNotFound
     *
     * @return ReflectionFunction
     */
    public function reflect(string $functionName): Reflection
    {
        $identifier = new Identifier($functionName, new IdentifierType(IdentifierType::IDENTIFIER_FUNCTION));

        $functionInfo = $this->sourceLocator->locateIdentifier($this->classReflector, $identifier);
        \assert($functionInfo instanceof ReflectionFunction || $functionInfo === null);

        if ($functionInfo === null) {
            throw Exception\IdentifierNotFound::fromIdentifier($identifier);
        }

        return $functionInfo;
    }

    /**
     * Get all the functions available in the scope specified by the SourceLocator.
     *
     * @return ReflectionFunction[]
     */
    public function getAllFunctions(): array
    {
        /** @var ReflectionFunction[] $allFunctions */
        return $this->sourceLocator->locateIdentifiersByType(
            $this,
            new IdentifierType(IdentifierType::IDENTIFIER_FUNCTION)
        );
    }
}
