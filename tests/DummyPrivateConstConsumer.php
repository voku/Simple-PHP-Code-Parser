<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
class DummyPrivateConstConsumer
{
    public function withPrivateConstDefault(string $x = DummyPrivateConstOwner::SECRET): string
    {
        return $x;
    }
}
