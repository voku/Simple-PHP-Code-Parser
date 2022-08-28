<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
class Dummy11
{
    public function __construct(
        public readonly string $title,
        public readonly \DateTimeImmutable $date,
    ) {
    }
}
