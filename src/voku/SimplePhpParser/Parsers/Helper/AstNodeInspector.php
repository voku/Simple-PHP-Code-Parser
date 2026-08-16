<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Node;

/**
 * Source-aware helpers for consumers that inspect the raw php-parser AST.
 */
final class AstNodeInspector
{
    public static function sourceText(Node $node, string $sourceCode): ?string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        if ($start < 0 || $end < $start) {
            return null;
        }

        return \substr($sourceCode, $start, $end - $start + 1);
    }

    public static function startColumn(Node $node, string $sourceCode): int
    {
        $start = $node->getStartFilePos();
        if ($start < 0) {
            return 1;
        }

        $lineStart = \strrpos(\substr($sourceCode, 0, $start), "\n");

        return $lineStart === false ? $start + 1 : $start - $lineStart;
    }

    /**
     * Build a shallow structural fingerprint from node kinds only.
     *
     * Identifiers and literal values deliberately do not participate.
     */
    public static function shapeFingerprint(Node $node, int $maxDepth = 4): string
    {
        return self::fingerprint($node, \max(0, $maxDepth), 0);
    }

    private static function fingerprint(Node $node, int $maxDepth, int $depth): string
    {
        $label = $node->getType();
        if ($depth >= $maxDepth) {
            return $label;
        }

        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->{$name};
            if ($value instanceof Node) {
                $children[] = self::fingerprint($value, $maxDepth, $depth + 1);
                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            foreach ($value as $child) {
                if ($child instanceof Node) {
                    $children[] = self::fingerprint($child, $maxDepth, $depth + 1);
                }
            }
        }

        return $children === [] ? $label : $label . '(' . \implode(',', $children) . ')';
    }
}
