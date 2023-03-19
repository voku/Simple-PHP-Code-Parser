<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
final class Dummy8 extends Dummy6
{
    /**
     * @var class-string<Foooooooo>
     */
    public $foooooooo = Foooooooo::class;

    use DummyTrait;

    /**
     * {@inheritdoc}
     */
    public function getFieldArray($RowOffset, $OrderByField, $OrderByDir): array
    {
        return [
            ['foo' => 1],
            ['foo' => 2]
        ];
    }

    /**
     * @return list<int>
     */
    public function foo_list()
    {
        return [4, 1, 2, 3, 4];
    }

    /**
     * @param int|float $param1
     *
     * @return bool|int
     */
    public function test_multi_param_type(int|float $param1): bool|int
    {
    }

    /**
     * @param array{stdClass: \stdClass, numbers: int|float} $lall
     *
     * @return array{stdClass: \stdClass, numbers: int|float}
     */
    public function foo_mixed($lall)
    {
        return $lall;
    }

    /**
     * @param array{stdClass: \stdClass, numbers: int|float $lall <foo/>
     *
     * @return array{stdClass: \stdClass, numbers: int|float <foo/>
     */
    public function foo_broken($lall)
    {
        return $lall;
    }

    /**
     * @param callable(string): string $callback
     *
     * @return string
     */
    public function withCallback($callback)
    {
        return $callback('foo');
    }

    /**
     * @param callable(string): string $callback
     * @param callable(): numeric $callback2
     *
     * @return string
     */
    public function withCallbackMulti($callback, $callback2)
    {
        return $callback('foo');
    }
}
