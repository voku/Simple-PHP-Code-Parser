<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Interface_;
use ReflectionClass;

class PHPInterface extends BasePHPClass
{
    /**
     * @var string[]
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

        $this->name = $this->getFQN($node);

        if (
            ($this->usePhpReflection() === null || $this->usePhpReflection() === true)
            &&
            \interface_exists($this->name)
        ) {
            try {
                $reflectionInterface = new ReflectionClass($this->name);
                $this->readObjectFromReflection($reflectionInterface);
            } catch (\ReflectionException $e) {
                if ($this->usePhpReflection() === true) {
                    throw $e;
                }

                // ignore
            }
        }

        if ($this->usePhpReflection() === true) {
            return $this;
        }

        $this->collectTags($node);

        if (!empty($node->extends)) {
            $this->parentInterfaces[] = \implode('\\', $node->extends[0]->parts);
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

        foreach ($interface->getMethods() as $method) {
            $this->methods[$method->getName()] = (new PHPMethod($this->usePhpReflection()))->readObjectFromReflection($method);
        }

        $this->parentInterfaces = $interface->getInterfaceNames();
        foreach ($interface->getReflectionConstants() as $constant) {
            $this->constants[$constant->name] = (new PHPConst($this->usePhpReflection()))->readObjectFromReflection($constant);
        }

        return $this;
    }
}
