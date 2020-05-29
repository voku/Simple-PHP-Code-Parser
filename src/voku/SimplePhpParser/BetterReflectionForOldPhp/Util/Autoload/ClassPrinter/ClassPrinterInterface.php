<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\ClassPrinter;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

interface ClassPrinterInterface
{
    public function __invoke(ReflectionClass $classInfo): string;
}
