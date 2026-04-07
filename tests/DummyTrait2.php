<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
trait DummyTrait2
{
    public const TRAIT_CONST_A = 'alpha';
    protected const TRAIT_CONST_B = 42;

    public function traitMethod(): string
    {
        return self::TRAIT_CONST_A;
    }
}
