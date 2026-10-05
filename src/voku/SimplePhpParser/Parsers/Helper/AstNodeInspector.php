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

    /**
     * The range of the declaration a node owns: the node itself plus the PHPDoc comment
     * (and attributes) in front of it, which php-parser keeps outside the node range.
     *
     * php-parser's own range already starts at the first attribute; only the PHPDoc comment,
     * including one in front of the attributes, lies outside it. Returns null when the node
     * carries no source positions.
     *
     * @return array{startLine: int, endLine: int, startFilePos: int, endFilePos: int}|null
     */
    public static function ownedRange(Node $declaration): ?array
    {
        $startLine = $declaration->getStartLine();
        $endLine = $declaration->getEndLine();
        $startFilePos = $declaration->getStartFilePos();
        $endFilePos = $declaration->getEndFilePos();
        if ($startLine < 0 || $endLine < 0 || $startFilePos < 0 || $endFilePos < $startFilePos) {
            return null;
        }

        $owned = [];
        $docComment = $declaration->getDocComment();
        if ($docComment !== null) {
            $owned[] = $docComment;
        }
        foreach ($declaration->attrGroups ?? [] as $attributeGroup) {
            $owned[] = $attributeGroup;
            $attributeDocComment = $attributeGroup->getDocComment();
            if ($attributeDocComment !== null) {
                $owned[] = $attributeDocComment;
            }
        }
        foreach ($owned as $ownedNode) {
            $ownedLine = $ownedNode->getStartLine();
            if ($ownedLine >= 0 && $ownedLine < $startLine) {
                $startLine = $ownedLine;
            }
            $ownedPos = $ownedNode->getStartFilePos();
            if ($ownedPos >= 0 && $ownedPos < $startFilePos) {
                $startFilePos = $ownedPos;
            }
        }

        return [
            'startLine' => $startLine,
            'endLine' => $endLine,
            'startFilePos' => $startFilePos,
            'endFilePos' => $endFilePos,
        ];
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
