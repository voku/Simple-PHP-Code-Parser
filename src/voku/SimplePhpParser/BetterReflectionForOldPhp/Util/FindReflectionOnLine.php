<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util;

use InvalidArgumentException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionConstant;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionMethod;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\ClassReflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Exception\ParseToAstFailure;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\AggregateSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SingleFileSourceLocator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\SourceLocator;

final class FindReflectionOnLine
{
    /**
     * @var SourceLocator
     */
    private $sourceLocator;

    /**
     * @var Locator
     */
    private $astLocator;

    /**
     * @param SourceLocator $sourceLocator
     * @param Locator       $astLocator
     */
    public function __construct(SourceLocator $sourceLocator, Locator $astLocator)
    {
        $this->sourceLocator = $sourceLocator;
        $this->astLocator = $astLocator;
    }

    /**
     * Find a reflection on the specified line number.
     *
     * Returns null if no reflections found on the line.
     *
     * @param string $filename
     * @param int    $lineNumber
     *
     * @throws InvalidFileLocation
     * @throws ParseToAstFailure
     * @throws InvalidArgumentException
     *
     * @return Reflection|ReflectionClass|ReflectionConstant|ReflectionFunction|ReflectionMethod|null
     */
    public function __invoke(string $filename, int $lineNumber)
    {
        $reflections = $this->computeReflections($filename);

        foreach ($reflections as $reflection) {
            if ($reflection instanceof ReflectionClass && $this->containsLine($reflection, $lineNumber)) {
                foreach ($reflection->getMethods() as $method) {
                    if ($this->containsLine($method, $lineNumber)) {
                        return $method;
                    }
                }

                return $reflection;
            }

            if ($reflection instanceof ReflectionFunction && $this->containsLine($reflection, $lineNumber)) {
                return $reflection;
            }

            if ($reflection instanceof ReflectionConstant && $this->containsLine($reflection, $lineNumber)) {
                return $reflection;
            }
        }

        return null;
    }

    /**
     * Find all class and function reflections in the specified file
     *
     * @param string $filename
     *
     * @throws ParseToAstFailure
     * @throws InvalidFileLocation
     *
     * @return Reflection[]
     */
    private function computeReflections(string $filename): array
    {
        $singleFileSourceLocator = new SingleFileSourceLocator($filename, $this->astLocator);
        $reflector = new ClassReflector(new AggregateSourceLocator([$singleFileSourceLocator, $this->sourceLocator]));

        return \array_merge(
            $singleFileSourceLocator->locateIdentifiersByType($reflector, new IdentifierType(IdentifierType::IDENTIFIER_CLASS)),
            $singleFileSourceLocator->locateIdentifiersByType($reflector, new IdentifierType(IdentifierType::IDENTIFIER_FUNCTION)),
            $singleFileSourceLocator->locateIdentifiersByType($reflector, new IdentifierType(IdentifierType::IDENTIFIER_CONSTANT))
        );
    }

    /**
     * Check to see if the line is within the boundaries of the reflection specified.
     *
     * @param Reflection|ReflectionClass|ReflectionFunction|ReflectionMethod $reflection
     * @param int                                                            $lineNumber
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    private function containsLine($reflection, int $lineNumber): bool
    {
        if (!\method_exists($reflection, 'getStartLine')) {
            throw new InvalidArgumentException('Reflection does not have getStartLine method');
        }

        if (!\method_exists($reflection, 'getEndLine')) {
            throw new InvalidArgumentException('Reflection does not have getEndLine method');
        }

        return $lineNumber >= $reflection->getStartLine() && $lineNumber <= $reflection->getEndLine();
    }
}
