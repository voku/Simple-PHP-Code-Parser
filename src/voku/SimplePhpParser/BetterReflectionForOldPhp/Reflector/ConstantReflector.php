<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionConstant;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SourceLocator;

class ConstantReflector implements Reflector
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
     * Create a ReflectionConstant for the specified $constantName.
     *
     * @throws IdentifierNotFound
     *
     * @return ReflectionConstant
     */
    public function reflect(string $constantName): Reflection
    {
        $identifier = new Identifier($constantName, new IdentifierType(IdentifierType::IDENTIFIER_CONSTANT));

        $constantInfo = $this->sourceLocator->locateIdentifier($this->classReflector, $identifier);
        \assert($constantInfo instanceof ReflectionConstant || $constantInfo === null);

        if ($constantInfo === null) {
            throw Exception\IdentifierNotFound::fromIdentifier($identifier);
        }

        return $constantInfo;
    }

    /**
     * Get all the constants available in the scope specified by the SourceLocator.
     *
     * @return array<int, ReflectionConstant>
     */
    public function getAllConstants(): array
    {
        /** @var array<int,ReflectionConstant> $allConstants */
        return $this->sourceLocator->locateIdentifiersByType(
            $this,
            new IdentifierType(IdentifierType::IDENTIFIER_CONSTANT)
        );
    }
}
