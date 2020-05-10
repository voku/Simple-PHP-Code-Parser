<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use Exception;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;

class PHPFunction extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var bool
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
        $this->name = $this->getFQN($node);

        if (\function_exists($this->name)) {
            try {
                $reflectionFunction = new ReflectionFunction($this->name);
                $this->readObjectFromReflection($reflectionFunction);
            } catch (\ReflectionException $e) {
                // ignore
            }
        }

        if ($node->returnType) {
            if (\method_exists($node->returnType, 'toString')) {
                $this->returnType = $node->returnType->toString();
            } elseif (\property_exists($node->returnType, 'name')) {
                $this->returnType = $node->returnType->name;
            } elseif ($node->returnType instanceof \PhpParser\Node\NullableType) {
                $node->returnType->type->toString();
            }
        }

        $doc = $node->getDocComment();
        if ($doc) {
            $phpDoc = PhpFileHelper::createDocBlockInstance()->create($doc->getText());
            $this->summary = $phpDoc->getSummary();
            $this->description = (string) $phpDoc->getDescription();
        }

        foreach ($node->getParams() as $parameter) {
            $param = (new PHPParameter())->readObjectFromPhpNode($parameter, $node);
            $this->parameters[$param->name] = $param;
        }

        $this->collectTags($node);
        $this->checkDeprecationTag($node);
        $this->checkReturnTag($node);

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
            $param = (new PHPParameter())->readObjectFromReflection($parameter);
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
        if ($node->getDocComment() !== null) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create($node->getDocComment()->getText());
                if (empty($phpDoc->getTagsByName('deprecated'))) {
                    $this->is_deprecated = false;
                } else {
                    $this->is_deprecated = true;
                }
            } catch (Exception $e) {
                $this->parseError = $e->getMessage();
            }
        }
    }

    /**
     * @param FunctionLike $node
     *
     * @return void
     */
    protected function checkReturnTag(FunctionLike $node): void
    {
        if ($node->getDocComment() !== null) {
            try {
                $phpDoc = PhpFileHelper::createDocBlockInstance()->create($node->getDocComment()->getText());

                $parsedReturnTag = $phpDoc->getTagsByName('return');

                if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Return_) {
                    /** @var Return_ $parsedReturnTagReturn */
                    $parsedReturnTagReturn = $parsedReturnTag[0];

                    $this->returnTypeMaybeWithComment = \trim((string) $parsedReturnTagReturn);

                    $type = $parsedReturnTagReturn->getType();

                    $this->returnTypeFromPhpDoc = $type . '';

                    $returnTypeTmp = PhpFileHelper::parseDocTypeObject($type);
                    if (\is_array($returnTypeTmp)) {
                        $this->returnTypeFromPhpDocSimple = \implode('|', $returnTypeTmp);
                    } else {
                        $this->returnTypeFromPhpDocSimple = $returnTypeTmp;
                    }

                    $this->returnTypeFromPhpDocPslam = (string) \Psalm\Type::parseString($this->returnTypeFromPhpDoc);
                }

                $parsedReturnTag = $phpDoc->getTagsByName('psalm-return')
                                   + $phpDoc->getTagsByName('phpstan-return');

                if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Generic) {
                    $parsedReturnTagReturn = $parsedReturnTag[0] . '';

                    $this->returnTypeFromPhpDocPslam = (string) \Psalm\Type::parseString($parsedReturnTagReturn);
                }
            } catch (Exception $e) {
                $this->parseError = $e->getMessage();
            }
        }
    }
}
