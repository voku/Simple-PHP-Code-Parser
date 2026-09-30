<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

/**
 * Represents a single PHP 8.0+ attribute instance.
 */
class PHPAttribute
{
    /**
     * Fully qualified attribute class name.
     */
    public string $name;

    /**
     * Attribute constructor arguments.
     *
     * Values that are context-free constant expressions are exposed as normal PHP values.
     * Expressions that require external runtime context are preserved as PHPAttributeExpression.
     *
     * @var array<int|string, mixed>
     */
    public array $arguments = [];

    /**
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }
}
