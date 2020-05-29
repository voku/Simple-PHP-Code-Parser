<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located;

use InvalidArgumentException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception\InvalidFileLocation;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\FileChecker;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

/**
 * Value object containing source code that has been located.
 *
 * @internal
 */
class LocatedSource
{
    /**
     * @var string
     */
    private $source;

    /**
     * @var string|null
     */
    private $filename;

    /**
     * @param string      $source
     * @param string|null $filename
     *
     * @throws InvalidArgumentException
     * @throws InvalidFileLocation
     */
    public function __construct(string $source, ?string $filename)
    {
        if ($filename !== null) {
            FileChecker::assertReadableFile($filename);

            $filename = FileHelper::normalizeWindowsPath($filename);
        }

        $this->source = $source;
        $this->filename = $filename;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getFileName(): ?string
    {
        return $this->filename;
    }

    /**
     * Is the located source in PHP internals?
     */
    public function isInternal(): bool
    {
        return false;
    }

    public function getExtensionName(): ?string
    {
        return null;
    }

    /**
     * Is the located source produced by eval() or \function_create()?
     */
    public function isEvaled(): bool
    {
        return false;
    }
}
