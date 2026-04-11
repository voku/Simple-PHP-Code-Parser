<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Const_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeAbstract;
use ReflectionClassConstant;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPConst extends BasePHPElement
{
    use PHPDocElement;

    public ?string $parentName = null;

    /**
     * @var array|bool|float|int|string|null
     *
     * @phpstan-var scalar|array<scalar>|null
     */
    public $value;

    public ?string $visibility = null;

    public ?string $type = null;

    /**
     * Type from the constant's native type declaration (PHP 8.3+).
     */
    public ?string $typeFromDeclaration = null;

    /**
     * PHP 8.0+ attributes on this constant.
     *
     * @var PHPAttribute[]
     */
    public array $attributes = [];

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

        $parentNode = $node->getAttribute('parent');

        if ($parentNode instanceof ClassConst) {
            if (\property_exists($parentNode, 'type') && $parentNode->type !== null) {
                $this->type = Utils::typeNodeToString($parentNode->type);
            }

            if ($parentNode->isPrivate()) {
                $this->visibility = 'private';
            } elseif ($parentNode->isProtected()) {
                $this->visibility = 'protected';
            } else {
                $this->visibility = 'public';
            }

            $this->parentName = self::getFQN($parentNode->getAttribute('parent'));

            // Typed class constants (PHP 8.3+)
            if (\property_exists($parentNode, 'type') && $parentNode->type !== null) {
                $typeDeclStr = Utils::typeNodeToString($parentNode->type);
                if ($typeDeclStr !== null) {
                    $this->typeFromDeclaration = $typeDeclStr;
                }
            }

            // Extract PHP 8.0+ attributes (only if not already populated by reflection)
            if (empty($this->attributes) && !empty($parentNode->attrGroups)) {
                $this->attributes = Utils::extractAttributesFromAstNode($parentNode->attrGroups);
            }
        }

        if ($this->type === null) {
            $this->type = Utils::normalizePhpType(\gettype($this->value));
        }

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

        if ($constant->isPrivate()) {
            $this->visibility = 'private';
        } elseif ($constant->isProtected()) {
            $this->visibility = 'protected';
        } else {
            $this->visibility = 'public';
        }

        // Typed class constants (PHP 8.3+)
        if (\method_exists($constant, 'getType')) {
            $reflType = $constant->getType();
            if ($reflType !== null) {
                $this->typeFromDeclaration = (string) $reflType;
            }
        }

        // Extract PHP 8.0+ attributes
        $this->attributes = Utils::extractAttributesFromReflection($constant);

        return $this;
    }

    protected function getConstantFQN(NodeAbstract $node, string $nodeName): string
    {
        $parent = $node->getAttribute('parent');
        $parentParentNode = $parent ? $parent->getAttribute('parent') : null;

        if (
            $parentParentNode instanceof Namespace_
            &&
            $parentParentNode->name instanceof Name
        ) {
            $namespace = '\\' . $parentParentNode->name->toString() . '\\';
        } else {
            $namespace = '';
        }

        return $namespace . $nodeName;
    }
}
