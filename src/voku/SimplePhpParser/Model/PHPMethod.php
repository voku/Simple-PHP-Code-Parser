<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\ClassMethod;

class PHPMethod extends PHPFunction
{
    /**
     * @var string
     */
    public $access;

    /**
     * @var bool
     */
    public $is_static;

    /**
     * @var bool
     */
    public $is_final;

    /**
     * @var string
     */
    public $parentName;

    /**
     * @param ClassMethod $node
     * @param null        $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): PHPFunction
    {
        $doc = $node->getDocComment();
        if ($doc) {
            $phpDoc = PhpFileHelper::createDocBlockInstance()->create($doc->getText());
            $this->summary = $phpDoc->getSummary();
            $this->description = (string) $phpDoc->getDescription();
        }

        $this->parentName = $this->getFQN($node->getAttribute('parent'));

        $this->name = $node->name->name;

        if ($node->returnType) {
            if (\method_exists($node->returnType, 'toString')) {
                $this->returnType = $node->returnType->toString();
            } elseif (\property_exists($node->returnType, 'name')) {
                $this->returnType = $node->returnType->name;
            } elseif ($node->returnType instanceof \PhpParser\Node\NullableType) {
                $node->returnType->type->toString();
            }
        }

        $this->collectTags($node);
        $this->checkDeprecationTag($node);
        $this->checkReturnTag($node);

        if (\strncmp($this->name, 'PS_UNRESERVE_PREFIX_', 20) === 0) {
            $this->name = \substr($this->name, \strlen('PS_UNRESERVE_PREFIX_'));
        }

        foreach ($node->getParams() as $parameter) {
            $param = (new PHPParameter())->readObjectFromPhpNode($parameter, $node);
            $this->parameters[$param->name] = $param;
        }

        $this->is_final = $node->isFinal();
        $this->is_static = $node->isStatic();

        if ($node->isPrivate()) {
            $this->access = 'private';
        } elseif ($node->isProtected()) {
            $this->access = 'protected';
        } else {
            $this->access = 'public';
        }

        return $this;
    }

    /**
     * @param \ReflectionMethod $method
     *
     * @return $this
     */
    public function readObjectFromReflection($method): PHPFunction
    {
        $this->name = $method->name;

        $this->is_static = $method->isStatic();
        $this->is_final = $method->isFinal();

        foreach ($method->getParameters() as $parameter) {
            $param = (new PHPParameter())->readObjectFromReflection($parameter);
            $this->parameters[$param->name] = $param;
        }

        if ($method->isProtected()) {
            $access = 'protected';
        } elseif ($method->isPrivate()) {
            $access = 'private';
        } else {
            $access = 'public';
        }
        $this->access = $access;

        return $this;
    }
}
