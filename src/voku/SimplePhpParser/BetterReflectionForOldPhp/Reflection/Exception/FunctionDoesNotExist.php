<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use RuntimeException;

class FunctionDoesNotExist extends RuntimeException
{
    /**
     * @param string $functionName
     *
     * @return static
     */
    public static function fromName(string $functionName): self
    {
        return new self(\sprintf('Function "%s" cannot be used as the function is not loaded', $functionName));
    }
}
