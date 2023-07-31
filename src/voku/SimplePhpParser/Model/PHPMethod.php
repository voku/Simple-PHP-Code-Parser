<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPMethod extends PHPFunction
{
    /**
     * @phpstan-var ''|'private'|'protected'|'public'
     */
    public string $access = '';

    public ?bool $is_static = null;

    public ?bool $is_final = null;

    public ?bool $is_inheritdoc = null;

    /**
     * @phpstan-var null|class-string
     */
    public ?string $parentName = null;

    /**
     * @param \PhpParser\Node\Stmt\ClassMethod $node
     * @param string|null                      $classStr
     *
     * @phpstan-param null|class-string $classStr
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
                $tmpErrorMessage = \sprintf(
                    '%s->%s:%s | %s',
                    $this->parentName,
                    $this->name,
                    $this->line ?? '?',
                    \print_r($e->getMessage(), true)
                );
                $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
            }
        }

        if ($node->returnType) {
            if (!$this->returnType) {
                if (\method_exists($node->returnType, 'toString')) {
                    $this->returnType = $node->returnType->toString();
                } elseif (\property_exists($node->returnType, 'name') && $node->returnType->name) {
                    $this->returnType = $node->returnType->name;
                }
            }

            if ($node->returnType instanceof \PhpParser\Node\NullableType) {
                if ($this->returnType && $this->returnType !== 'null' && \strpos($this->returnType, 'null|') !== 0) {
                    $this->returnType = 'null|' . $this->returnType;
                } elseif (!$this->returnType) {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $this->collectTags($node);

        $docComment = $node->getDocComment();
        if ($docComment) {
            $docCommentText = $docComment->getText();

            if (\stripos($docCommentText, '@inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
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
            $parameterVar = $parameter->var;
            if ($parameterVar instanceof \PhpParser\Node\Expr\Error) {
                $this->parseError[] = \sprintf(
                    '%s:%s | maybe at this position an expression is required',
                    $this->line ?? '?',
                    $this->pos ?? ''
                );

                return $this;
            }

            $parameterNameTmp = $parameterVar->name;
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
     * @param \ReflectionMethod $method
     *
     * @return $this
     */
    public function readObjectFromReflection($method): PHPFunction
    {
        $this->name = $method->getName();

        if (!$this->line) {
            $lineTmp = $method->getStartLine();
            if ($lineTmp !== false) {
                $this->line = $lineTmp;
            }
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
                if ($this->returnType && $this->returnType !== 'null' && \strpos($this->returnType, 'null|') !== 0) {
                    $this->returnType = 'null|' . $this->returnType;
                } elseif (!$this->returnType) {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $docComment = $method->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, '@inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
        }

        if (!$this->returnTypeFromPhpDoc) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create((string)$method->getDocComment());
                $returnTypeTmp = $phpDoc->getTagsByName('return');
                if (
                    \count($returnTypeTmp) === 1
                    &&
                    $returnTypeTmp[0] instanceof \phpDocumentor\Reflection\DocBlock\Tags\Return_
                ) {
                    $this->returnTypeFromPhpDoc = Utils::parseDocTypeObject($returnTypeTmp[0]->getType());
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
            $param = (new PHPParameter($this->parserContainer))->readObjectFromReflection($parameter);
            $this->parameters[$param->name] = $param;
        }

        return $this;
    }
}
