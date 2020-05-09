<?php
declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

final class Utils
{
    /**
     * @param array $arr
     * @param bool  $group
     *
     * @return array
     */
    public static function flattenArray(array $arr, bool $group)
    {
        return \iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($arr)), $group);
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
     *
     * @return array{parsedParamTagStr: string, variableName: null|string}
     */
    public static function splitTypeAndVariable(
        \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
    ): array {
        $parsedParamTagStr = $parsedParamTag . '';
        $variableName = null;

        if (\strpos($parsedParamTagStr, '$') !== false) {
            $variableName = \mb_substr($parsedParamTagStr, (int) \mb_strpos($parsedParamTagStr, '$'));
            $parsedParamTagStr = \str_replace(
                $variableName,
                '',
                $parsedParamTagStr
            );
        }

        // clean-up
        if ($variableName) {
            $variableName = \str_replace('$', '', $variableName);
        }

        $parsedParamTagStr = \trim($parsedParamTagStr);

        return [
            'parsedParamTagStr' => $parsedParamTagStr,
            'variableName'      => $variableName,
        ];
    }
}
