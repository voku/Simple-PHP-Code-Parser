<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use RuntimeException;

class PropertyDoesNotExist extends RuntimeException
{
    /**
     * @param string $propertyName
     *
     * @return static
     */
    public static function fromName(string $propertyName): self
    {
        return new self(\sprintf('Property "%s" does not exist', $propertyName));
    }
}
