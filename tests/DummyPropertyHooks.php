<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * Test fixture for PHP 8.4 property hooks and asymmetric visibility.
 *
 * This file is parsed purely from its string content (not autoloaded),
 * because PHP < 8.4 cannot compile property-hook syntax.
 */
class DummyPropertyHooks
{
    public string $fullName {
        get => $this->first . ' ' . $this->last;
        set (string $value) {
            [$this->first, $this->last] = explode(' ', $value, 2);
        }
    }

    public private(set) string $email = '';

    public protected(set) int $age = 0;

    private string $first = '';
    private string $last = '';
}
