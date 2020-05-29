<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use LogicException;

class Uncloneable extends LogicException
{
    /**
     * @param string $className
     *
     * @return static
     */
    public static function fromClass(string $className): self
    {
        return new self('Trying to clone an uncloneable object of class ' . $className);
    }
}
