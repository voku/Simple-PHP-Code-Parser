<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
class Dummy14 implements \Countable, DummyInterface4
{
    public \Countable&DummyInterface4 $intersectionProp;

    public function getIntersection(\Countable&DummyInterface4 $input): \Countable&DummyInterface4
    {
        return $input;
    }

    public function neverReturn(): never
    {
        throw new \RuntimeException('never');
    }

    public function returnTrue(): true
    {
        return true;
    }

    public function returnFalse(): false
    {
        return false;
    }

    public function returnNull(): null
    {
        return null;
    }

    public function count(): int
    {
        return 0;
    }
}
