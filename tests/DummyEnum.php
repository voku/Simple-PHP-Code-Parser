<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
enum DummyEnum: string
{
    case Hearts = 'H';
    case Diamonds = 'D';
    case Clubs = 'C';
    case Spades = 'S';

    public function color(): string
    {
        return match ($this) {
            self::Hearts, self::Diamonds => 'red',
            self::Clubs, self::Spades => 'black',
        };
    }
}
