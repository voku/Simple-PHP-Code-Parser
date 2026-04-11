<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;

final class DocFactoryProvider
{
    private static ?DocBlockFactoryInterface $docFactory = null;

    public static function getDocFactory(): DocBlockFactoryInterface
    {
        if (self::$docFactory === null) {
            self::$docFactory = DocBlockFactory::createInstance();
        }

        return self::$docFactory;
    }
}
