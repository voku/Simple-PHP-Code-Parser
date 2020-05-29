<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Exception\InvalidIdentifierName;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunctionAbstract;

class Identifier
{
    public const WILDCARD = '*';

    private const VALID_NAME_REGEXP = '/([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*/';

    /**
     * @var string
     */
    private $name;

    /**
     * @var IdentifierType
     */
    private $type;

    /**
     * Identifier constructor.
     *
     * @param string         $name
     * @param IdentifierType $type
     *
     * @throws InvalidIdentifierName
     */
    public function __construct(string $name, IdentifierType $type)
    {
        $this->type = $type;

        if (
            $name === self::WILDCARD
            ||
            $name === ReflectionFunctionAbstract::CLOSURE_NAME
            ||
            \strpos($name, ReflectionClass::ANONYMOUS_CLASS_NAME_PREFIX) === 0
        ) {
            $this->name = $name;

            return;
        }

        $name = \ltrim($name, '\\');

        if (!\preg_match(self::VALID_NAME_REGEXP, $name)) {
            throw InvalidIdentifierName::fromInvalidName($name);
        }

        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): IdentifierType
    {
        return $this->type;
    }

    public function isClass(): bool
    {
        return $this->type->isClass();
    }

    public function isFunction(): bool
    {
        return $this->type->isFunction();
    }

    public function isConstant(): bool
    {
        return $this->type->isConstant();
    }
}
