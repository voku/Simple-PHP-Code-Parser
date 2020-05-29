<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidDirectory;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileInfo;

/**
 * This source locator recursively loads all php files in an entire directory or multiple directories.
 */
class DirectoriesSourceLocator implements SourceLocator
{
    /**
     * @var AggregateSourceLocator
     */
    private $aggregateSourceLocator;

    /**
     * @param string[] $directories directories to scan
     *
     * @throws InvalidDirectory
     * @throws InvalidFileInfo
     */
    public function __construct(array $directories, Locator $astLocator)
    {
        $this->aggregateSourceLocator = new AggregateSourceLocator(\array_values(\array_map(
            static function ($directory) use ($astLocator): FileIteratorSourceLocator {
                if (!\is_string($directory)) {
                    throw InvalidDirectory::fromNonStringValue($directory);
                }

                if (!\is_dir($directory)) {
                    throw InvalidDirectory::fromNonDirectory($directory);
                }

                return new FileIteratorSourceLocator(
                    new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                        $directory,
                        RecursiveDirectoryIterator::SKIP_DOTS
                    )),
                    $astLocator
                );
            },
            $directories
        )));
    }

    public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
    {
        return $this->aggregateSourceLocator->locateIdentifier($reflector, $identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
    {
        return $this->aggregateSourceLocator->locateIdentifiersByType($reflector, $identifierType);
    }
}
