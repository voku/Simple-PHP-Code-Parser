<?php

declare(strict_types=1);

namespace voku\tests;

enum EnumReflectionFixture: string
{
    case Ready = 'ready';

    public const LABEL = 'label';
}
