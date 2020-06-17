<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPMethod extends PHPFunction
{
    /**
     * "private", "protected" or "public"
     *
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
     * @var bool|null
     */
    public $is_inheritdoc;

    /**
     * @var string|null
     *
     * @psalm-var null|class-string
     */
    public $parentName;

    /**
     * @param \PhpParser\Node\Stmt\ClassMethod $node
     * @param string|null                      $classStr
     *
     * @psalm-param null|class-string $classStr
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $classStr = null): PHPFunction
    {
        $this->prepareNode($node);

        $this->parentName = static::getFQN($node->getAttribute('parent'));

        $this->name = $node->name->name;

        $docComment = $node->getDocComment();
        if ($docComment) {
            try {
                $phpDoc = Utils::createDocBlockInstance()->create($docComment->getText());
                $this->summary = $phpDoc->getSummary();
                $this->description = (string) $phpDoc->getDescription();
            } catch (\Exception $e) {
                $tmpErrorMessage = $this->parentName . '->' . $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
                $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
            }
        }

        if (!$this->returnType && $node->returnType) {
            if (\method_exists($node->returnType, 'toString')) {
                $this->returnType = $node->returnType->toString();
            } elseif (\property_exists($node->returnType, 'name')) {
                $this->returnType = $node->returnType->name;
            }

            if ($node->returnType instanceof \PhpParser\Node\NullableType) {
                if ($this->returnType && $this->returnType !== 'null') {
                    $this->returnType = 'null|' . $this->returnType;
                } else {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $this->collectTags($node);

        $docComment = $node->getDocComment();
        if ($docComment) {
            $docComment = $docComment->getText();

            if (\stripos($docComment, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
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
            $parameterNameTmp = $parameter->var->name;
            \assert(\is_string($parameterNameTmp));

            if (isset($this->parameters[$parameterNameTmp])) {
                $this->parameters[$parameterNameTmp] = $this->parameters[$parameterNameTmp]->readObjectFromPhpNode($parameter, $node, $classStr);
            } else {
                $this->parameters[$parameterNameTmp] = (new PHPParameter($this->parserContainer))->readObjectFromPhpNode($parameter, $node, $classStr);
            }
        }

        return $this;
    }

    /**
     * @param \Roave\BetterReflection\Reflection\ReflectionMethod $method
     *
     * @return $this
     */
    public function readObjectFromBetterReflection($method): PHPFunction
    {
        $this->name = $method->getName();

        if (!$this->line) {
            $this->line = $method->getStartLine();
        }

        $file = $method->getFileName();
        if ($file) {
            $this->file = $file;
        }

        $this->is_static = $method->isStatic();

        $this->is_final = $method->isFinal();

        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            if (\method_exists($returnType, 'getName')) {
                $this->returnType = $returnType->getName();
            } else {
                $this->returnType = $returnType . '';
            }

            if ($returnType->allowsNull()) {
                if ($this->returnType && $this->returnType !== 'null') {
                    $this->returnType = 'null|' . $this->returnType;
                } else {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $docComment = $method->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
        }

        if (!$this->returnTypeFromPhpDoc) {
            try {
                $returnTypeTmp = $method->getDocBlockReturnTypes();
                if ($returnTypeTmp) {
                    $this->returnTypeFromPhpDoc = Utils::parseDocTypeObject($returnTypeTmp);
                }
            } catch (\Exception $e) {
                // ignore
            }
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
            $param = (new PHPParameter($this->parserContainer))->readObjectFromBetterReflection($parameter);
            $this->parameters[$param->name] = $param;
        }

        return $this;
    }
}
