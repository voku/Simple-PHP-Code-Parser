<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\ClassLoaderMethod\LoaderMethodInterface;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\Exception\ClassAlreadyLoaded;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\Exception\ClassAlreadyRegistered;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\Autoload\Exception\FailedToLoadClass;

final class ClassLoader
{
    /**
     * @var ReflectionClass[]
     */
    private $reflections = [];

    /**
     * @var LoaderMethodInterface
     */
    private $loaderMethod;

    public function __construct(LoaderMethodInterface $loaderMethod)
    {
        $this->loaderMethod = $loaderMethod;
        \spl_autoload_register($this, true, true);
    }

    /**
     * @param string $classToLoad
     *
     * @throws FailedToLoadClass
     *
     * @return bool
     */
    public function __invoke(string $classToLoad): bool
    {
        if (!\array_key_exists($classToLoad, $this->reflections)) {
            return false;
        }

        $this->loaderMethod->__invoke($this->reflections[$classToLoad]);

        if (!(\class_exists($classToLoad, false)
            || \interface_exists($classToLoad, false)
            || \trait_exists($classToLoad, false))) {
            throw Exception\FailedToLoadClass::fromClassName($classToLoad);
        }

        return true;
    }

    /**
     * @param ReflectionClass $reflectionClass
     *
     * @throws ClassAlreadyLoaded
     * @throws ClassAlreadyRegistered
     *
     * @return void
     */
    public function addClass(ReflectionClass $reflectionClass): void
    {
        if (\array_key_exists($reflectionClass->getName(), $this->reflections)) {
            throw Exception\ClassAlreadyRegistered::fromReflectionClass($reflectionClass);
        }

        if (\class_exists($reflectionClass->getName(), false)) {
            throw Exception\ClassAlreadyLoaded::fromReflectionClass($reflectionClass);
        }

        $this->reflections[$reflectionClass->getName()] = $reflectionClass;
    }
}
