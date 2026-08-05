<?php

declare(strict_types=1);

namespace voku\tests;

final class ReflectionTypeAutoloadFixture
{
    public MissingReflectionType $property;

    public function handle(MissingReflectionType $parameter): void
    {
    }
}
