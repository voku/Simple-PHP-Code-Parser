<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * Test fixture for #[\Override] attribute (PHP 8.3+).
 */
class DummyOverrideParent
{
    public function greet(): string
    {
        return 'Hello';
    }

    public function farewell(): string
    {
        return 'Bye';
    }
}

class DummyOverrideChild extends DummyOverrideParent
{
    #[\Override]
    public function greet(): string
    {
        return 'Hi there';
    }

    public function farewell(): string
    {
        return 'See ya';
    }

    public function newMethod(): void
    {
    }
}
