<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util;

use PhpParser\Node;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Exception\InvalidNodePosition;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Exception\NoNodePosition;

/**
 * @internal
 */
final class CalculateReflectionColum
{
    /**
     * @param string $source
     * @param Node   $node
     *
     * @throws InvalidNodePosition
     * @throws NoNodePosition
     *
     * @return int
     */
    public static function getStartColumn(string $source, Node $node): int
    {
        if (!$node->hasAttribute('startFilePos')) {
            throw NoNodePosition::fromNode($node);
        }

        return self::calculateColumn($source, $node->getStartFilePos());
    }

    /**
     * @param string $source
     * @param Node   $node
     *
     * @throws InvalidNodePosition
     * @throws NoNodePosition
     *
     * @return int
     */
    public static function getEndColumn(string $source, Node $node): int
    {
        if (!$node->hasAttribute('endFilePos')) {
            throw NoNodePosition::fromNode($node);
        }

        return self::calculateColumn($source, $node->getEndFilePos());
    }

    /**
     * @param string $source
     * @param int    $position
     *
     * @throws InvalidNodePosition
     *
     * @return int
     */
    private static function calculateColumn(string $source, int $position): int
    {
        $sourceLength = \strlen($source);

        if ($position > $sourceLength) {
            throw InvalidNodePosition::fromPosition($position);
        }

        $lineStartPosition = \strrpos($source, "\n", $position - $sourceLength);
        if ($lineStartPosition === false) {
            return $position + 1;
        }

        return $position - $lineStartPosition;
    }
}
