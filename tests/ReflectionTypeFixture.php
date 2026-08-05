<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * Fixture for the reflection-based type formatting.
 *
 * Everything in here must stay compilable on PHP 8.1, because the fixture is
 * autoloaded on purpose so that the reflection code path is exercised.
 *
 * @internal
 */
final class ReflectionTypeFixture implements ReflectionTypeFixtureInterface
{
    public int $builtin = 0;

    public ?string $nullableBuiltin = null;

    public array $arrayType = [];

    public iterable $iterableType;

    public $untyped;

    /**
     * An interface type: `class_exists()` returns false for it, so this is the
     * case that used to lose its leading backslash in the reflection path.
     */
    public ReflectionTypeFixtureInterface $interfaceType;

    public ?ReflectionTypeFixtureInterface $nullableInterfaceType = null;

    public ReflectionTypeFixture $classType;

    public DummyEnum $enumType;

    public self $selfType;

    public \Traversable $globalInterfaceType;

    public int|string $unionType = 0;

    public \Countable&\ArrayAccess $intersectionType;

    public function method(
        int $builtin,
        ?string $nullableBuiltin,
        array $arrayType,
        ReflectionTypeFixtureInterface $interfaceType,
        ?ReflectionTypeFixtureInterface $nullableInterfaceType,
        ReflectionTypeFixture $classType,
        DummyEnum $enumType,
        self $selfType,
        \Traversable $globalInterfaceType,
        int|string $unionType,
        \Countable&\ArrayAccess $intersectionType,
        callable $callableType,
        $untyped = null
    ): self {
        return $this;
    }
}
