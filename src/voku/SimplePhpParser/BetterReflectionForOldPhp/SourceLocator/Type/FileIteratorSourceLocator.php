<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use Iterator;
use const PATHINFO_EXTENSION;
use SplFileInfo;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileInfo;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;

/**
 * This source locator loads all php files from \FileSystemIterator
 */
class FileIteratorSourceLocator implements SourceLocator
{
    /**
     * @var AggregateSourceLocator|null
     */
    private $aggregateSourceLocator;

    /**
     * @var Iterator|SplFileInfo[]
     */
    private $fileSystemIterator;

    /**
     * @var Locator
     */
    private $astLocator;

    /**
     * @param Iterator|SplFileInfo[] $fileInfoIterator note: only SplFileInfo allowed in this iterator
     *
     * @throws InvalidFileInfo in case of iterator not contains only SplFileInfo
     */
    public function __construct(Iterator $fileInfoIterator, Locator $astLocator)
    {
        foreach ($fileInfoIterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo) {
                throw InvalidFileInfo::fromNonSplFileInfo($fileInfo);
            }
        }

        $this->fileSystemIterator = $fileInfoIterator;
        $this->astLocator = $astLocator;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFileLocation
     */
    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        return $this->getAggregatedSourceLocator()->locateIdentifier($reflector, $identifier);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFileLocation
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return $this->getAggregatedSourceLocator()->locateIdentifiersByType($reflector, $identifierType);
    }

    /**
     * @throws InvalidFileLocation
     */
    private function getAggregatedSourceLocator(): AggregateSourceLocator
    {
        return $this->aggregateSourceLocator ?: new AggregateSourceLocator(\array_values(\array_filter(\array_map(
            function (SplFileInfo $item): ?SingleFileSourceLocator {
                if (!($item->isFile() && \pathinfo($item->getRealPath(), PATHINFO_EXTENSION) === 'php')) {
                    return null;
                }

                return new SingleFileSourceLocator($item->getRealPath(), $this->astLocator);
            },
            \iterator_to_array($this->fileSystemIterator)
        ))));
    }
}
