<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPFunction extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var bool|null
     */
    public $is_deprecated;

    /**
     * @var PHPParameter[]
     */
    public $parameters = [];

    /**
     * @var string
     */
    public $returnType = '';

    /**
     * @var string
     */
    public $returnTypeFromPhpDoc = '';

    /**
     * @var string
     */
    public $returnTypeFromPhpDocSimple = '';

    /**
     * @var string
     */
    public $returnTypeFromPhpDocPslam = '';

    /**
     * @var string
     */
    public $returnTypeMaybeWithComment = '';

    /**
     * @var string
     */
    public $summary = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @param Function_ $node
     * @param null      $dummy
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
            \function_exists($this->name)
        ) {
            try {
                $reflectionFunction = new ReflectionFunction($this->name);
                $this->readObjectFromReflection($reflectionFunction);
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

        if ($node->returnType) {
            if (\method_exists($node->returnType, 'toString')) {
                $this->returnType = $node->returnType->toString();
            } elseif (\property_exists($node->returnType, 'name')) {
                $this->returnType = $node->returnType->name;
            } elseif ($node->returnType instanceof \PhpParser\Node\NullableType) {
                $this->returnType = $node->returnType->type->toString();
            }
        }

        $docComment = $node->getDocComment();
        if ($docComment) {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment->getText());
            $this->summary = $phpDoc->getSummary();
            $this->description = (string) $phpDoc->getDescription();
        }

        foreach ($node->getParams() as $parameter) {
            $param = (new PHPParameter($this->usePhpReflection()))->readObjectFromPhpNode($parameter, $node);
            $this->parameters[$param->name] = $param;
        }

        $this->collectTags($node);

        $this->checkDeprecationTag($node);

        $nodeDoc = $node->getDocComment();
        if ($nodeDoc) {
            $this->readPhpDoc($nodeDoc->getText());
        }

        return $this;
    }

    /**
     * @param ReflectionFunction $function
     *
     * @return $this
     */
    public function readObjectFromReflection($function): self
    {
        $this->name = $function->name;

        $this->is_deprecated = $function->isDeprecated();

        foreach ($function->getParameters() as $parameter) {
            $param = (new PHPParameter($this->usePhpReflection()))->readObjectFromReflection($parameter);
            $this->parameters[$param->name] = $param;
        }

        return $this;
    }

    /**
     * @param FunctionLike $node
     *
     * @return void
     */
    protected function checkDeprecationTag(FunctionLike $node): void
    {
        $docComment = $node->getDocComment();
        if ($docComment !== null) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create($docComment->getText());
                if (empty($phpDoc->getTagsByName('deprecated'))) {
                    $this->is_deprecated = false;
                } else {
                    $this->is_deprecated = true;
                }
            } catch (\Exception $e) {
                $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
            }
        }
    }

    /**
     * @param \ReflectionFunctionAbstract $function
     *
     * @return string|null Type of the property (content of var annotation)
     */
    protected function readObjectFromReflectionReturnHelper(\ReflectionFunctionAbstract $function): ?string
    {
        $phpDoc = $function->getDocComment();
        if (!$phpDoc) {
            return null;
        }

        // Get the content of the @return annotation.
        if (\preg_match_all('/(@.*?return\s+[^\s]+.*)/ui', $phpDoc, $matches)) {
            $return = '';
            foreach ($matches[0] as $match) {
                $return .= $match . "\n";
            }
        } else {
            return null;
        }

        return $return;
    }

    protected function readPhpDoc(string $docComment): void
    {
        try {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedReturnTag = $phpDoc->getTagsByName('return');

            if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Return_) {
                /** @var Return_ $parsedReturnTagReturn */
                $parsedReturnTagReturn = $parsedReturnTag[0];

                $this->returnTypeMaybeWithComment = \trim((string) $parsedReturnTagReturn);

                $type = $parsedReturnTagReturn->getType();
                if ($type) {
                    $this->returnTypeFromPhpDoc = $type . '';
                }

                $returnTypeTmp = Utils::parseDocTypeObject($type);
                if (\is_array($returnTypeTmp)) {
                    $this->returnTypeFromPhpDocSimple = \implode('|', $returnTypeTmp);
                } else {
                    $this->returnTypeFromPhpDocSimple = $returnTypeTmp;
                }

                $this->returnTypeFromPhpDocPslam = (string) \Psalm\Type::parseString($this->returnTypeFromPhpDoc);
            }

            /** @noinspection AdditionOperationOnArraysInspection */
            $parsedReturnTag = $phpDoc->getTagsByName('psalm-return')
                               + $phpDoc->getTagsByName('phpstan-return');

            if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Generic) {
                $parsedReturnTagReturn = $parsedReturnTag[0] . '';

                $this->returnTypeFromPhpDocPslam = (string) \Psalm\Type::parseString($parsedReturnTagReturn);
            }
        } catch (\Exception $e) {
            $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
        }
    }
}
