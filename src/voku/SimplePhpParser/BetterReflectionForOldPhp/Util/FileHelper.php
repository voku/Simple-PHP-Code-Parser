<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util;

use const DIRECTORY_SEPARATOR;

class FileHelper
{
    /**
     * @param string $path
     *
     * @return string
     */
    public static function normalizeWindowsPath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public static function normalizeSystemPath(string $path): string
    {
        $path = self::normalizeWindowsPath($path);

        return DIRECTORY_SEPARATOR === '\\'
            ? \str_replace('/', '\\', $path)
            : $path;
    }
}
