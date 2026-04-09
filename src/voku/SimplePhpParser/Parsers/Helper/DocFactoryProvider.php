<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use phpDocumentor\Reflection\DocBlockFactory;

class DocFactoryProvider
{
    private static ?DocBlockFactory $docFactory = null;

    public static function getDocFactory(): DocBlockFactory
    {
        if (self::$docFactory === null) {
            self::$docFactory = DocBlockFactory::createInstance();
        }

        return self::$docFactory;
    }
}
