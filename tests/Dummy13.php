<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
readonly final class Dummy13 implements \voku\tests\DummyInterface
{
    public int $lall;

    public function __construct(int $lall)
    {
        $this->lall = $lall;
    }

    /**
     * {@inheritdoc}
     */
    public function withComplexReturnArray($parsedParamTag)
    {
        return [
            'parsedParamTagStr' => 'foo',
            'variableName'      => [null],
        ];
    }
}
