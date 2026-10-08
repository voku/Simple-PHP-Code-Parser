<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use phpDocumentor\Reflection\DocBlockFactoryInterface;

final class DocFactoryProvider
{
    private static ?DocBlockFactoryInterface $docFactory = null;

    public static function getDocFactory(): DocBlockFactoryInterface
    {
        if (self::$docFactory === null) {
            self::$docFactory = MemoizingDocBlockFactory::createInstance();
        }

        return self::$docFactory;
    }
}
