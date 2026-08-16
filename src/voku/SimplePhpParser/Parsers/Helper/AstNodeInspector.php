<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Node;

/**
 * Source-aware helpers for consumers that inspect the raw php-parser AST.
 */
final class AstNodeInspector
{
    public function __construct(
        private readonly string $sourceCode
    ) {
    }

    /**
     * Return the exact source slice covered by a node.
     */
    public function sourceText(Node $node): ?string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        if ($start < 0 || $end < $start) {
            return null;
        }

        return \substr($this->sourceCode, $start, $end - $start + 1);
    }

    /**
     * Return the node's one-based start column.
     */
    public function startColumn(Node $node): int
    {
        $start = $node->getStartFilePos();
        if ($start < 0) {
            return 1;
        }

        $prefix = \substr($this->sourceCode, 0, $start);
        $lineStart = \strrpos($prefix, "\n");

        return $lineStart === false ? $start + 1 : $start - $lineStart;
    }

    /**
     * Build a shallow structural fingerprint from node kinds only.
     *
     * Identifiers and literal values deliberately do not participate, which
     * makes this useful for comparing repeated code shapes without pretending
     * to perform semantic equivalence analysis.
     */
    public function shapeFingerprint(Node $node, int $maxDepth = 4): string
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
