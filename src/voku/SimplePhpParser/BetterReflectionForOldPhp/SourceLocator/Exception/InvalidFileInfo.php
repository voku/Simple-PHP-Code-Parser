<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception;

use RuntimeException;

class InvalidFileInfo extends RuntimeException
{
    /**
     * @param mixed $nonSplFileInfo
     *
     * @return static
     */
    public static function fromNonSplFileInfo($nonSplFileInfo): self
    {
        return new self(\sprintf(
            'Expected an iterator of SplFileInfo instances, %s given instead',
            \is_object($nonSplFileInfo) ? \get_class($nonSplFileInfo) : \gettype($nonSplFileInfo)
        ));
    }
}
