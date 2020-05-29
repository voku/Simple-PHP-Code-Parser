<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type;

use Composer\Autoload\ClassLoader;
use InvalidArgumentException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\IdentifierType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Locator;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

/**
 * This source locator uses Composer's built-in ClassLoader to locate files.
 *
 * Note that we use ClassLoader->findFile directory, rather than
 * ClassLoader->loadClass because this library has a strict requirement that we
 * do NOT actually load the classes
 */
class ComposerSourceLocator extends AbstractSourceLocator
{
    /**
     * @var ClassLoader
     */
    private $classLoader;

    public function __construct(ClassLoader $classLoader, Locator $astLocator)
    {
        parent::__construct($astLocator);
        $this->classLoader = $classLoader;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws InvalidFileLocation
     */
    protected function createLocatedSource(Identifier $identifier): ?LocatedSource
    {
        if ($identifier->getType()->getName() !== IdentifierType::IDENTIFIER_CLASS) {
            return null;
        }

        $filename = $this->classLoader->findFile($identifier->getName());

        if (!$filename) {
            return null;
        }

        return new LocatedSource(
            \file_get_contents($filename),
            $filename
        );
    }
}
