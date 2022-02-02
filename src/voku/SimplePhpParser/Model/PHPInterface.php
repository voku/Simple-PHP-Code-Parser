<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Interface_;
use ReflectionClass;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPInterface extends BasePHPClass
{
    /**
     * @var string|null
     *
     * @phpstan-var null|class-string
     */
    public $name;

    /**
     * @var string[]
     *
     * @phpstan-var class-string[]
     */
    public $parentInterfaces = [];

    /**
     * @param Interface_ $node
     * @param null       $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        $interfaceExists = false;
        try {
            /** @noinspection NotOptimalIfConditionsInspection */
            /** @noinspection ArgumentEqualsDefaultValueInspection */
            if (\interface_exists($this->name, true)) {
                $interfaceExists = true;
            }
        } catch (\Exception $e) {
            // nothing
        }
        if ($interfaceExists) {
            $reflectionInterface = Utils::createClassReflectionInstance($this->name);
            $this->readObjectFromReflection($reflectionInterface);
        }

        $this->collectTags($node);

        foreach ($node->getMethods() as $method) {
            $methodNameTmp = $method->name->name;

            if (isset($this->methods[$methodNameTmp])) {
                $this->methods[$methodNameTmp] = $this->methods[$methodNameTmp]->readObjectFromPhpNode($method);
            } else {
                $this->methods[$methodNameTmp] = (new PHPMethod($this->parserContainer))->readObjectFromPhpNode($method);
            }

            if (!$this->methods[$methodNameTmp]->file) {
                $this->methods[$methodNameTmp]->file = $this->file;
            }
        }

        if (!empty($node->extends)) {
            /** @var class-string $interfaceExtended */
            $interfaceExtended = \implode('\\', $node->extends[0]->parts);
            $this->parentInterfaces[] = $interfaceExtended;
        }

        return $this;
    }

    /**
     * @param ReflectionClass $interface
     *
     * @return $this
     */
    public function readObjectFromReflection($interface): self
    {
        $this->name = $interface->getName();

        if (!$this->line) {
            $this->line = $interface->getStartLine();
        }

        $file = $interface->getFileName();
        if ($file) {
            $this->file = $file;
        }

        foreach ($interface->getMethods() as $method) {
            $this->methods[$method->getName()] = (new PHPMethod($this->parserContainer))->readObjectFromReflection($method);
        }

        /** @var class-string[] $interfaceNames */
        $interfaceNames = $interface->getInterfaceNames();
        $this->parentInterfaces = $interfaceNames;

        foreach ($this->parentInterfaces as $parentInterface) {
            $interfaceExists = false;
            try {
                /** @noinspection ArgumentEqualsDefaultValueInspection */
                if (
                    !$this->parserContainer->getInterface($parentInterface)
                    &&
                    \interface_exists($parentInterface, true)
                ) {
                    $interfaceExists = true;
                }
            } catch (\Exception $e) {
                // nothing
            }
            if ($interfaceExists) {
                $reflectionInterface = Utils::createClassReflectionInstance($parentInterface);
                $parentInterfaceNew = (new self($this->parserContainer))->readObjectFromReflection($reflectionInterface);
                $this->parserContainer->addInterface($parentInterfaceNew);
            }
        }

        foreach ($interface->getReflectionConstants() as $constant) {
            $this->constants[$constant->getName()] = (new PHPConst($this->parserContainer))->readObjectFromReflection($constant);
        }

        return $this;
    }
}
