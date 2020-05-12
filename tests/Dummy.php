<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @return Dummy
 */
function foo(int $foo = 0)
{
    return new Dummy();
}

/**
 * @internal
 */
final class Dummy extends \stdClass
{
    /**
     * @return array<int, int>
     */
    public function withReturnType(): array
    {
        return [1, 2, 3];
    }

    /**
     * @return false|int
     */
    public function withoutReturnType()
    {
        return \random_int(0, 10) > 5 ? 0 : false;
    }

    /**
     * @return int[]|string[]|null <p>foo</p>
     *
     * @psalm-return ?list<int|string>
     */
    public function withoutPhpDocParam(bool $useRandInt = true)
    {
        return \random_int(0, 10) > 5 ? [1, 2, 'lall'] : null;
    }

    /**
     * @param int[]|null $useRandInt
     *
     * @psalm-param ?list<int> $useRandInt
     */
    public function withPhpDocParam($useRandInt = [3, 5])
    {
        $max = $useRandInt === null ? 5 : \max($useRandInt);

        return \random_int(0, $max) > 2 ? [1, 2, 'lall'] : null;
    }

    /**
     * @psalm-param ?list<int> $useRandInt
     */
    public function withPsalmPhpDocOnlyParam($useRandInt = [3, 5])
    {
        $max = $useRandInt === null ? 5 : \max($useRandInt);

        return \random_int(0, $max) > 2 ? [1, 2, 'lall'] : null;
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
     *
     * @return array
     *
     * @psalm-return array{parsedParamTagStr: string, variableName: null[]|string}
     */
    public static function withComplexReturnArray(\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag)
    {
        return [
            'parsedParamTagStr' => 'foo',
            'variableName'      => [null],
        ];
    }

    /**
     * @param $parsedParamTag
     *
     * @return array
     *
     * @psalm-return array{parsedParamTagStr: string, variableName: null[]|string}
     */
    public static function withEmptyParamTypePhpDoc($parsedParamTag)
    {
        return [
            'parsedParamTagStr' => 'foo',
            'variableName'      => [null],
        ];
    }
}
