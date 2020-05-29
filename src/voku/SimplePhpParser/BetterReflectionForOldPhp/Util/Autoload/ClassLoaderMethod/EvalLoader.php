<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\ClassLoaderMethod;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\ClassPrinter\ClassPrinterInterface;

final class EvalLoader implements LoaderMethodInterface
{
    /**
     * @var ClassPrinterInterface
     */
    private $classPrinter;

    public function __construct(ClassPrinterInterface $classPrinter)
    {
        $this->classPrinter = $classPrinter;
    }

    public function __invoke(ReflectionClass $classInfo): void
    {
        eval($this->classPrinter->__invoke($classInfo));
    }
}
