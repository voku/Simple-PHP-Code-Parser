<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;

class ParserContainer
{
    /**
     * @var PHPConst[]
     *
     * @psalm-var array<string, PHPConst>
     */
    private $constants = [];

    /**
     * @var PHPFunction[]
     *
     * @psalm-var array<string, PHPFunction>
     */
    private $functions = [];

    /**
     * @var PHPClass[]
     *
     * @psalm-var array<string, PHPClass>
     */
    private $classes = [];

    /**
     * @var PHPInterface[]
     *
     * @psalm-var array<string, PHPInterface>
     */
    private $interfaces = [];

    /**
     * @return PHPConst[]
     */
    public function getConstants(): array
    {
        return $this->constants;
    }

    /**
     * @param PHPConst $constant
     *
     * @return void
     */
    public function addConstant(PHPConst $constant): void
    {
        $this->constants[$constant->name] = $constant;
    }

    /**
     * @return PHPFunction[]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * @param PHPFunction $function
     *
     * @return void
     */
    public function addFunction(PHPFunction $function): void
    {
        $this->functions[$function->name] = $function;
    }

    /**
     * @param string $name
     *
     * @return PHPClass|null
     */
    public function getClass(string $name): ?PHPClass
    {
        if (\array_key_exists($name, $this->classes) && $this->classes[$name] !== null) {
            return $this->classes[$name];
        }

        return null;
    }

    /**
     * @return PHPClass[]
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @param array<string, PHPInterface> $interfaces
     *
     * @return void
     */
    public function setInterfaces($interfaces): void
    {
        foreach ($interfaces as $name => $interface) {
            $this->interfaces[$name] = $interface;
        }
    }

    /**
     * @param array<string, PHPConst> $constants
     *
     * @return void
     */
    public function setConstants($constants): void
    {
        foreach ($constants as $name => $constant) {
            $this->constants[$name] = $constant;
        }
    }

    /**
     * @param array<string, PHPFunction> $functions
     *
     * @return void
     */
    public function setFunctions($functions): void
    {
        foreach ($functions as $name => $function) {
            $this->functions[$name] = $function;
        }
    }

    /**
     * @param array<string, PHPClass> $classes
     *
     * @return void
     */
    public function setClasses($classes): void
    {
        foreach ($classes as $className => $class) {
            $this->classes[$className] = $class;
        }
    }

    /**
     * @param PHPClass $class
     *
     * @return void
     */
    public function addClass(PHPClass $class): void
    {
        $this->classes[$class->name ?: \md5(\serialize($class))] = $class;
    }

    /**
     * @param string $name
     *
     * @return PHPInterface|null
     */
    public function getInterface(string $name): ?PHPInterface
    {
        if (
            \array_key_exists($name, $this->interfaces)
            &&
            $this->interfaces[$name] !== null
        ) {
            return $this->interfaces[$name];
        }

        return null;
    }

    /**
     * @return PHPInterface[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * @param PHPInterface $interface
     *
     * @return void
     */
    public function addInterface(PHPInterface $interface): void
    {
        $this->interfaces[$interface->name] = $interface;
    }
}
