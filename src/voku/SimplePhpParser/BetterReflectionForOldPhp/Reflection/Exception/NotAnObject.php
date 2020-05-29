<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use InvalidArgumentException;

class NotAnObject extends InvalidArgumentException
{
    /**
     * @param array|bool|float|int|resource|string|null $nonObject
     *
     * @return static
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     */
    public static function fromNonObject($nonObject): self
    {
        return new self(\sprintf('Provided "%s" is not an object', \gettype($nonObject)));
    }
}
