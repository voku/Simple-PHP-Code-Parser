<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\ClassLoaderMethod\Exception;

use RuntimeException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

final class SignatureCheckFailed extends RuntimeException
{
    public static function fromReflectionClass(ReflectionClass $reflectionClass): self
    {
        return new self(\sprintf(
            'Failed to verify the signature of the cached file for %s',
            $reflectionClass->getName()
        ));
    }
}
