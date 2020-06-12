<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Interface_;
use Roave\BetterReflection\Reflection\ReflectionClass;

class PHPInterface extends BasePHPClass
{
    /**
     * @var string
     *
     * @psalm-var class-string
     */
    public $name;

    /**
     * @var string[]
     *
     * @psalm-var class-string[]
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

        /** @noinspection NotOptimalIfConditionsInspection */
        if (\interface_exists($this->name, true)) {
            $reflectionInterface = ReflectionClass::createFromName($this->name);
            $this->readObjectFromBetterReflection($reflectionInterface);
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
    public function readObjectFromBetterReflection($interface): self
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
            $this->methods[$method->getName()] = (new PHPMethod($this->parserContainer))->readObjectFromBetterReflection($method);
        }

        /** @var class-string[] $interfaceNames */
        $interfaceNames = $interface->getInterfaceNames();
        $this->parentInterfaces = $interfaceNames;

        foreach ($interface->getReflectionConstants() as $constant) {
            $this->constants[$constant->getName()] = (new PHPConst($this->parserContainer))->readObjectFromBetterReflection($constant);
        }

        return $this;
    }
}
