<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use ReflectionType as CoreReflectionType;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionType as BetterReflectionType;

class ReflectionType extends CoreReflectionType
{
    /**
     * @var BetterReflectionType
     */
    private $betterReflectionType;

    public function __construct(BetterReflectionType $betterReflectionType)
    {
        $this->betterReflectionType = $betterReflectionType;
    }

    public function __toString(): string
    {
        return $this->betterReflectionType->__toString();
    }

    public static function fromReturnTypeOrNull(?BetterReflectionType $betterReflectionType): ?self
    {
        if ($betterReflectionType === null) {
            return null;
        }

        return new self($betterReflectionType);
    }

    public function allowsNull(): bool
    {
        return $this->betterReflectionType->allowsNull();
    }

    public function isBuiltin(): bool
    {
        $type = (string) $this->betterReflectionType;

        if ($type === 'self' || $type === 'parent') {
            return false;
        }

        return $this->betterReflectionType->isBuiltin();
    }
}
