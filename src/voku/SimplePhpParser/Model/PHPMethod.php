<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPMethod extends PHPFunction
{
    /**
     * @var string
     */
    public $access = '';

    /**
     * @var bool|null
     */
    public $is_static;

    /**
     * @var bool|null
     */
    public $is_final;

    /**
     * @var string|null
     *
     * @psalm-var null|class-string
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
        $this->prepareNode($node);

        $this->parentName = $this->getFQN($node->getAttribute('parent'));

        $this->name = $node->name->name;

        /** @noinspection NotOptimalIfConditionsInspection */
        if (
            $this->parentName
            &&
            ($this->usePhpReflection() === null || $this->usePhpReflection() === true)
            &&
            \method_exists($this->parentName, $this->name)
        ) {
            try {
                $reflectionMethod = new \ReflectionMethod($this->parentName, $this->name);
                $this->readObjectFromReflection($reflectionMethod);
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

        $docComment = $node->getDocComment();
        if ($docComment) {
            try {
                $phpDoc = Utils::createDocBlockInstance()->create($docComment->getText());
                $this->summary = $phpDoc->getSummary();
                $this->description = (string) $phpDoc->getDescription();
            } catch (\Exception $e) {
                $tmpErrorMessage = $this->parentName . '->' . $this->name . ':' . ($this->line ?? '') . ' | ' . \print_r($e->getMessage(), true);
                $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
            }
        }

        if ($node->returnType) {
            if (\method_exists($node->returnType, 'toString')) {
                $this->returnType = $node->returnType->toString();
            } elseif (\property_exists($node->returnType, 'name')) {
                $this->returnType = $node->returnType->name;
            } elseif ($node->returnType instanceof \PhpParser\Node\NullableType) {
                $this->returnType = $node->returnType->type->toString();
            }
        }

        $this->collectTags($node);

        $nodeDoc = $node->getDocComment();
        if ($nodeDoc) {
            $this->readPhpDoc($nodeDoc->getText());
        }

        if (\strncmp($this->name, 'PS_UNRESERVE_PREFIX_', 20) === 0) {
            $this->name = \substr($this->name, \strlen('PS_UNRESERVE_PREFIX_'));
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

        foreach ($node->getParams() as $parameter) {
            $param = (new PHPParameter($this->usePhpReflection()))->readObjectFromPhpNode($parameter, $node);
            $this->parameters[$param->name] = $param;
        }

        return $this;
    }

    /**
     * @param ReflectionMethod $method
     *
     * @return $this
     */
    public function readObjectFromReflection($method): PHPFunction
    {
        $this->name = $method->getName();

        $this->is_static = $method->isStatic();

        $this->is_final = $method->isFinal();

        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            if (\method_exists($returnType, 'getName')) {
                $this->returnType = $returnType->getName();
            } else {
                $this->returnType = $returnType . '';
            }
        }

        $docComment = $this->readObjectFromReflectionReturnHelper($method);
        if ($docComment !== null) {
            $docComment = '/** ' . $docComment . ' */';
            $this->readPhpDoc($docComment);
        }

        if ($method->isProtected()) {
            $access = 'protected';
        } elseif ($method->isPrivate()) {
            $access = 'private';
        } else {
            $access = 'public';
        }
        $this->access = $access;

        foreach ($method->getParameters() as $parameter) {
            $param = (new PHPParameter($this->usePhpReflection()))->readObjectFromReflection($parameter);
            $this->parameters[$param->name] = $param;
        }

        return $this;
    }
}
