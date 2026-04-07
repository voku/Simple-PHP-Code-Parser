<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * Test fixture for PHP 8.1 fibers and first-class callable syntax.
 */
class DummyFirstClassCallable
{
    public function getCallable(): \Closure
    {
        return strlen(...);
    }

    public function matchExample(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            default => 'Unknown',
        };
    }

    public function namedArgExample(): string
    {
        return \implode(separator: ', ', array: ['a', 'b']);
    }

    public function nullsafeExample(?object $obj): ?string
    {
        return $obj?->toString();
    }
}
