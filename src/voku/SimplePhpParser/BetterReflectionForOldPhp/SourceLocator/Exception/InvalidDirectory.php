<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception;

use RuntimeException;

class InvalidDirectory extends RuntimeException
{
    public static function fromNonDirectory(string $nonDirectory): self
    {
        if (!\file_exists($nonDirectory)) {
            return new self(\sprintf('"%s" does not exist', $nonDirectory));
        }

        return new self(\sprintf('"%s" must be a directory, not a file', $nonDirectory));
    }

    /**
     * @param array|bool|float|int|object|resource|null $nonStringValue
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     *
     * @return static
     */
    public static function fromNonStringValue($nonStringValue): self
    {
        /** @noinspection GetClassUsageInspection */
        return new self(\sprintf(
            'Expected string, %s given',
            \is_object($nonStringValue) ? \get_class($nonStringValue) : \gettype($nonStringValue)
        ));
    }
}
