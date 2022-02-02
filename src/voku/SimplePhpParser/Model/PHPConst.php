<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeAbstract;
use ReflectionClassConstant;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPConst extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var string|null
     */
    public $parentName;

    /**
     * @var array|bool|float|int|string|null
     *
     * @phpstan-var scalar|array<scalar>|null
     */
    public $value;

    /**
     * @var string|null
     */
    public $type;

    /**
     * @param Const_ $node
     * @param null   $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = $this->getConstantFQN($node, $node->name->name);

        $this->value = Utils::getPhpParserValueFromNode($node);

        $this->type = Utils::normalizePhpType(\gettype($this->value));

        $this->collectTags($node);

        if ($node->getAttribute('parent') instanceof ClassConst) {
            $this->parentName = static::getFQN($node->getAttribute('parent')->getAttribute('parent'));
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
        $this->name = $constant->getName();

        $file = $constant->getDeclaringClass()->getFileName();
        if ($file) {
            $this->file = $file;
        }

        /** @psalm-suppress InvalidPropertyAssignmentValue - upstream phpdoc error ? */
        $this->value = $constant->getValue();

        $this->type = \gettype($this->value);

        return $this;
    }

    protected function getConstantFQN(NodeAbstract $node, string $nodeName): string
    {
        $parent = $node->getAttribute('parent');
        $parentParentNode = $parent ? $parent->getAttribute('parent') : null;

        if ($parentParentNode instanceof Namespace_ && !empty($parentParentNode->name)) {
            $namespace = '\\' . \implode('\\', $parentParentNode->name->parts) . '\\';
        } else {
            $namespace = '';
        }

        return $namespace . $nodeName;
    }
}
