<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier;

use InvalidArgumentException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionConstant;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction;

class IdentifierType
{
    public const IDENTIFIER_CLASS = ReflectionClass::class;

    public const IDENTIFIER_FUNCTION = ReflectionFunction::class;

    public const IDENTIFIER_CONSTANT = ReflectionConstant::class;

    private const VALID_TYPES = [
        self::IDENTIFIER_CLASS    => null,
        self::IDENTIFIER_FUNCTION => null,
        self::IDENTIFIER_CONSTANT => null,
    ];

    /**
     * @var string
     */
    private $name;

    public function __construct(string $type = self::IDENTIFIER_CLASS)
    {
        if (!\array_key_exists($type, self::VALID_TYPES)) {
            throw new InvalidArgumentException(\sprintf(
                '%s is not a valid identifier type',
                $type
            ));
        }

        $this->name = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isClass(): bool
    {
        return $this->name === self::IDENTIFIER_CLASS;
    }

    public function isFunction(): bool
    {
        return $this->name === self::IDENTIFIER_FUNCTION;
    }

    public function isConstant(): bool
    {
        return $this->name === self::IDENTIFIER_CONSTANT;
    }

    /**
     * Check to see if a reflector is of a valid type specified by this identifier.
     *
     * @param Reflection $reflector
     *
     * @return bool
     */
    public function isMatchingReflector(Reflection $reflector): bool
    {
        if ($this->name === self::IDENTIFIER_CLASS) {
            return $reflector instanceof ReflectionClass;
        }

        if ($this->name === self::IDENTIFIER_FUNCTION) {
            return $reflector instanceof ReflectionFunction;
        }

        if ($this->name === self::IDENTIFIER_CONSTANT) {
            return $reflector instanceof ReflectionConstant;
        }

        return false;
    }
}
