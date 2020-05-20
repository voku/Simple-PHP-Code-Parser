<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeAbstract;
use ReflectionClassConstant;

class PHPConst extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var string|null
     */
    public $parentName;

    /**
     * @var mixed
     */
    public $value;

    /**
     * @var string
     */
    public $type = '';

    /**
     * @param Const_ $node
     * @param null   $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        if ($this->usePhpReflection() === true) {
            return $this;
        }

        $this->name = $this->getConstantFQN($node, $node->name->name);

        $this->value = $this->getConstValue($node);

        $this->type = \gettype($this->value);

        $this->collectTags($node);

        if ($node->getAttribute('parent') instanceof ClassConst) {
            $this->parentName = $this->getFQN($node->getAttribute('parent')->getAttribute('parent'));
        }

        return $this;
    }

    /**
     * @param ReflectionClassConstant $constant
     *
     * @return $this
     */
    public function readObjectFromReflection($constant): self
    {
        $this->name = $constant->name;

        $this->value = $constant->getValue();

        $this->type = \gettype($this->value);

        return $this;
    }

    /**
     * @param Arg|Const_ $node
     *
     * @return mixed
     */
    protected function getConstValue($node)
    {
        if (\in_array('value', $node->value->getSubNodeNames(), true)) {
            return $node->value->value;
        }

        if (\in_array('expr', $node->value->getSubNodeNames(), true)) {
            if ($node->value instanceof UnaryMinus) {
                /** @psalm-suppress UndefinedPropertyFetch - false-positive ? */
                return -$node->value->expr->value;
            }

            /** @psalm-suppress UndefinedPropertyFetch - false-positive ? */
            return $node->value->expr->value;
        }

        if (\in_array('name', $node->value->getSubNodeNames(), true)) {
            /** @psalm-suppress InvalidPropertyFetch - false-positive ? */
            return $node->value->name->parts[0];
        }

        return null;
    }

    protected function getConstantFQN(NodeAbstract $node, string $nodeName): string
    {
        $namespace = '';
        $parent = $node->getAttribute('parent');
        $parentParentNode = $parent ? $parent->getAttribute('parent') : null;
        if ($parentParentNode instanceof Namespace_ && !empty($parentParentNode->name)) {
            $namespace = '\\' . \implode('\\', $parentParentNode->name->parts) . '\\';
        }

        return $namespace . $nodeName;
    }
}
