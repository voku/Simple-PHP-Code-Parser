<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
enum DummyEnumInt: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }
}
