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
     * @var array<int|string, mixed>
     */
    public array $arguments = [];

    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }
}
