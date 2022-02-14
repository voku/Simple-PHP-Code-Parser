<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * @internal
 */
final class Dummy10
{
    public ?int $lall1 = null;

    public ?int $lall2;

    /**
     * @var null|int
     */
    public $lall3;

    private const FOO3 = 3;

    public const FOO4 = -1;

    public function getFieldArray(int|string $RowOffset, string $OrderByField, string $OrderByDir): array
    {
        return [];
    }
}
