<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util;

use PhpParser\Comment\Doc;
use PhpParser\NodeAbstract;

/**
 * @internal
 */
final class GetFirstDocComment
{
    public static function forNode(NodeAbstract $node): string
    {
        foreach ($node->getComments() as $comment) {
            if ($comment instanceof Doc) {
                $text = $comment->getReformattedText();

                \assert(\is_string($text));

                return $text;
            }
        }

        return '';
    }
}
