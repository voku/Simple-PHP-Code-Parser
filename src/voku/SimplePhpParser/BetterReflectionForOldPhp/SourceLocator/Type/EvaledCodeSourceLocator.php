<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use InvalidArgumentException;
use ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\EvaledLocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\SourceStubber\SourceStubber;

final class EvaledCodeSourceLocator extends AbstractSourceLocator
{
    /**
     * @var SourceStubber
     */
    private $stubber;

    public function __construct(Locator $astLocator, SourceStubber $stubber)
    {
        parent::__construct($astLocator);

        $this->stubber = $stubber;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws InvalidFileLocation
     */
    protected function createLocatedSource(Identifier $identifier): ?LocatedSource
    {
        $classReflection = $this->getInternalReflectionClass($identifier);

        if ($classReflection === null) {
            return null;
        }

        $stubData = $this->stubber->generateClassStub($classReflection->getName());

        if ($stubData === null) {
            return null;
        }

        return new EvaledLocatedSource($stubData->getStub());
    }

    private function getInternalReflectionClass(Identifier $identifier): ?ReflectionClass
    {
        if (!$identifier->isClass()) {
            return null;
        }

        $name = $identifier->getName();

        if (!(\class_exists($name, false) || \interface_exists($name, false) || \trait_exists($name, false))) {
            return null; // not an available internal class
        }

        $reflection = new ReflectionClass($name);
        $sourceFile = $reflection->getFileName();

        return $sourceFile && \file_exists($sourceFile)
            ? null : $reflection;
    }
}
