<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
class DummyPrivateConstOwner
{
    private const SECRET = 'secret-value';

    protected const GUARDED = 'guarded-value';

    public const OPEN = 'open-value';

    public const NOTHING = null;
}
