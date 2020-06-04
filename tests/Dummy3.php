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
