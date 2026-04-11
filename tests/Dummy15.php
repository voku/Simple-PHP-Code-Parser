<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
class Dummy15
{
    public (\Countable&\Traversable)|null $dnfProp = null;

    public function getDnf((\Countable&\Traversable)|null $input): (\Countable&\Traversable)|null
    {
        return $input;
    }
}
