<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use InvalidArgumentException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\FileChecker;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

/**
 * This source locator loads an entire file, specified in the constructor
 * argument.
 *
 * This is useful for loading a class that does not have a namespace. This is
 * also the class required if you want to use Reflector->getClassesFromFile
 * (which loads all classes from specified file)
 */
class SingleFileSourceLocator extends AbstractSourceLocator
{
    /**
     * @var string
     */
    private $fileName;

    /**
     * @throws InvalidFileLocation
     */
    public function __construct(string $fileName, Locator $astLocator)
    {
        FileChecker::assertReadableFile($fileName);

        parent::__construct($astLocator);

        $this->fileName = $fileName;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws InvalidFileLocation
     */
    protected function createLocatedSource(Identifier $identifier): ?LocatedSource
    {
        return new LocatedSource(
            \file_get_contents($this->fileName),
            $this->fileName
        );
    }
}
