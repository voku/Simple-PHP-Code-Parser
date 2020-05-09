<?php
declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use ReflectionParameter;

class PHPParameter extends BasePHPElement
{
    /**
     * @var string
     */
    public $type = '';

    /**
     * @var string
     */
    public $typeFromPhpDoc = '';

    /**
     * @var string
     */
    public $typeFromPhpDocSimple = '';

    /**
     * @var string
     */
    public $typeFromPhpDocPslam = '';

    /**
     * @var string
     */
    public $typeMaybeWithComment = '';

    /**
     * @var bool
     */
    public $is_vararg;

    /**
     * @var bool
     */
    public $is_passed_by_ref;

    /**
     * @param Param        $parameter
     * @param FunctionLike $node
     *
     * @return $this
     */
    public function readObjectFromPhpNode($parameter, $node = null): self
    {
        if ($node) {
            $this->checkParameter($node, $parameter);
        }

        $this->name = $parameter->var->name . '';
        if ($parameter->type !== null) {
            if (empty($parameter->type->name)) {
                if (!empty($parameter->type->parts)) {
                    $this->type = $parameter->type->parts[0];
                }
            } else {
                $this->type = $parameter->type->name;
            }
        }
        $this->is_vararg = $parameter->variadic;
        $this->is_passed_by_ref = $parameter->byRef;

        return $this;
    }

    /**
     * @param ReflectionParameter $parameter
     *
     * @return $this
     */
    public function readObjectFromReflection($parameter): self
    {
        $this->name = $parameter->name;

        $type = $parameter->getType();
        if ($type !== null) {
            if (\method_exists($type, 'getName')) {
                $this->type = $type->getName();
            } else {
                $this->type = $type . '';
            }
        }

        $this->is_vararg = $parameter->isVariadic();

        $this->is_passed_by_ref = $parameter->isPassedByReference();

        return $this;
    }

    /**
     * @param FunctionLike $node
     * @param Param        $parameter
     *
     * @return void
     */
    protected function checkParameter(FunctionLike $node, Param $parameter)
    {
        if ($node->getDocComment() !== null) {
            try {
                $phpDoc = PhpFileHelper::createDocBlockInstance()->create($node->getDocComment()->getText());

                $parsedParamTags = $phpDoc->getTagsByName('param');

                if (!empty($parsedParamTags)) {
                    foreach ($parsedParamTags as $parsedParamTag) {
                        if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Param) {

                            // check only the current "param"-tag
                            if (
                                $parameter->var instanceof \PhpParser\Node\Expr\Variable
                                &&
                                \is_string($parameter->var->name)
                                &&
                                \strtoupper($parameter->var->name) !== \strtoupper((string) $parsedParamTag->getVariableName())
                            ) {
                                continue;
                            }

                            $type = $parsedParamTag->getType();

                            $this->typeFromPhpDoc = $type . '';

                            $this->typeMaybeWithComment = \trim((string) $parsedParamTag);

                            $returnTypeTmp = PhpFileHelper::parseDocTypeObject($type);
                            if (\is_array($returnTypeTmp)) {
                                $this->typeFromPhpDocSimple = \implode('|', $returnTypeTmp);
                            } else {
                                $this->typeFromPhpDocSimple = $returnTypeTmp;
                            }

                            $this->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($this->typeFromPhpDoc);
                        }
                    }
                }

                $parsedParamTags = $phpDoc->getTagsByName('psalm-param')
                                   + $phpDoc->getTagsByName('phpstan-param');

                if (!empty($parsedParamTags)) {
                    foreach ($parsedParamTags as $parsedParamTag) {
                        if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                            $spitedData = \voku\SimplePhpParser\Parsers\Helper\Utils::splitTypeAndVariable($parsedParamTag);
                            $parsedParamTagStr = $spitedData['parsedParamTagStr'];
                            $variableName = $spitedData['variableName'];

                            // check only the current "param"-tag
                            if (
                                $variableName === null
                                ||
                                (
                                    $parameter->var instanceof \PhpParser\Node\Expr\Variable
                                    &&
                                    \is_string($parameter->var->name)
                                    &&
                                    \strtoupper($parameter->var->name) !== \strtoupper($variableName)
                                )
                            ) {
                                continue;
                            }

                            $this->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($parsedParamTagStr);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->parseError = $e->getMessage();
            }
        }
    }
}
