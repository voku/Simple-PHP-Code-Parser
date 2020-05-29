<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\ClassLoaderMethod;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;

interface LoaderMethodInterface
{
    public function __invoke(ReflectionClass $classInfo): void;
}
