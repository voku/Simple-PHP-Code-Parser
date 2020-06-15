<?php

declare(strict_types=1);

namespace voku\tests;

function foo3(int $foo = 0)
{
    return new Dummy();
}

/**
 * @internal
 */
final class Dummy3 implements DummyInterface
{
    public $foo;

    /**
     * @param $foo
     *
     * @return mixed
     */
    public function lall($foo)
    {
        return $foo + 1;
    }

    /**
     * @param int $foo
     *
     * @return int
     */
    public function lall2($foo): ?int
    {
        return $foo + 1;
    }

    /**
     * @param int $foo
     *
     * @return null|int
     */
    public function lall2_1($foo): int
    {
        return $foo + 1;
    }

    /**
     * @param int $foo
     *
     * @return int|string
     */
    public function lall3($foo): int
    {
        return $foo + 1;
    }

    /**
     * @param int|string $foo
     *
     * @return int
     */
    public function lall3_1(int $foo): int
    {
        return $foo + 1;
    }

    /**
     * @return \Generator|int[]
     */
    public function lall3_2(int $foo): \Generator
    {
        yield $foo;

        yield ++$foo;
    }

    /**
     * @return \Generator&int[]
     *
     * @psalm-return \Generator<int>
     */
    public function lall3_2_1(int $foo): \Generator
    {
        yield $foo;

        yield ++$foo;
    }

    /**
     * @return \voku\tests\Dummy3
     */
    public function lall4(): DummyInterface
    {
        return new self;
    }

    /**
     * This is a test-text [...] öäü !"§?.
     *
     * @param \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
     *                                                                        <p>this is a test-text [...] öäü !"§?</p>
     *
     * @return array
     *
     * @psalm-return array{parsedParamTagStr: string, variableName: null[]|string}
     */
    public function withComplexReturnArray(\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag)
    {
        return [
            'parsedParamTagStr' => 'foo',
            'variableName'      => [null],
        ];
    }
}
