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
    public function readObjectFromPhpNode($node, $dummy = null)
    {
        $this->name = $this->getFQN($node);

        if (\interface_exists($this->name)) {
            try {
                $reflectionInterface = new ReflectionClass($this->name);
                $this->readObjectFromReflection($reflectionInterface);
            } catch (\ReflectionException $e) {
                // ignore
            }
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
            if ($method->getDeclaringClass()->getName() !== $this->name) {
                continue;
            }
            $this->methods[$method->name] = (new PHPMethod())->readObjectFromReflection($method);
        }

        $this->parentInterfaces = $interface->getInterfaceNames();
        foreach ($interface->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $this->name) {
                continue;
            }
            $this->constants[$constant->name] = (new PHPConst())->readObjectFromReflection($constant);
        }

        return $this;
    }
}
