<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception;

use InvalidArgumentException;

class ObjectNotInstanceOfClass extends InvalidArgumentException
{
    /**
     * @param string $className
     *
     * @return static
     */
    public static function fromClassName(string $className): self
    {
        return new self(\sprintf('Object is not instance of class "%s"', $className));
    }
}
