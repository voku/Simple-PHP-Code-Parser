<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use ReflectionParameter;
use voku\SimplePhpParser\Parsers\Helper\Utils;

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
     * @var bool|null
     */
    public $is_vararg;

    /**
     * @var bool|null
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
        $this->name = \is_string($parameter->var->name) ? $parameter->var->name : '';

        if ($this->usePhpReflection() === true) {
            return $this;
        }

        if ($node) {
            $this->prepareNode($node);

            $docComment = $node->getDocComment();
            if ($docComment !== null) {
                try {
                    $this->readPhpDoc($docComment->getText(), $this->name);
                } catch (\Exception $e) {
                    $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
                }
            }
        }

        $defaultValue = $parameter->default;
        if ($defaultValue) {
            if (\property_exists($defaultValue, 'value')) {
                /**
                 * @psalm-suppress UndefinedPropertyFetch - false-positive from psalm
                 */
                $this->type = \gettype($defaultValue->value);
            } elseif ($defaultValue instanceof \PhpParser\Node\Expr\Array_) {
                $this->type = 'array';
            }
        }

        if ($parameter->type !== null) {
            if (empty($parameter->type->name)) {
                if (!empty($parameter->type->parts)) {
                    $this->type = '\\' . \implode('\\', $parameter->type->parts);
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

        $docComment = $this->readObjectFromReflectionParamHelper($parameter);
        if ($docComment !== null) {
            $docComment = '/** ' . $docComment . ' */';

            try {
                $this->readPhpDoc($docComment, $this->name);
            } catch (\Exception $e) {
                $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
            }
        }

        try {
            $defaultValue = $parameter->getDefaultValue();
            $this->type = \gettype($defaultValue);
        } catch (\Exception $e) {
            $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
        }

        $type = $parameter->getType();
        if ($type !== null) {
            if (\method_exists($type, 'getName')) {
                $this->type = $type->getName();
            } else {
                $this->type = $type . '';
            }
            if ($this->type && \class_exists($this->type)) {
                $this->type = '\\' . \ltrim($this->type, '\\');
            }

            if ($type->allowsNull()) {
                if ($this->type) {
                    $this->type = 'null|' . $this->type;
                } else {
                    $this->type = 'null';
                }
            }
        }

        $this->is_vararg = $parameter->isVariadic();

        $this->is_passed_by_ref = $parameter->isPassedByReference();

        return $this;
    }

    /**
     * @param ReflectionParameter $parameter
     *
     * @return string|null Type of the property (content of var annotation)
     */
    private function readObjectFromReflectionParamHelper(ReflectionParameter $parameter): ?string
    {
        // Get the content of the @param annotation.
        $method = $parameter->getDeclaringFunction();

        $phpDoc = $method->getDocComment();
        if (!$phpDoc) {
            return null;
        }

        if (\preg_match_all('/(@.*?param\s+[^\s]+\s+\$' . $parameter->name . ')/ui', $phpDoc, $matches)) {
            $param = '';
            foreach ($matches[0] as $match) {
                $param .= $match . "\n";
            }
        } else {
            return null;
        }

        return $param;
    }

    private function readPhpDoc(string $docComment, string $parameterName): void
    {
        try {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedParamTags = $phpDoc->getTagsByName('param');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Param) {

                        // check only the current "param"-tag
                        if (\strtoupper($parameterName) !== \strtoupper((string) $parsedParamTag->getVariableName())) {
                            continue;
                        }

                        $type = $parsedParamTag->getType();
                        if ($type) {
                            $this->typeFromPhpDoc = $type . '';
                        }

                        $typeMaybeWithCommentTmp = \trim((string) $parsedParamTag);
                        if (
                            $typeMaybeWithCommentTmp
                            &&
                            \strpos($typeMaybeWithCommentTmp, '$') !== 0
                        ) {
                            $this->typeMaybeWithComment = $typeMaybeWithCommentTmp;
                        }

                        $returnTypeTmp = Utils::parseDocTypeObject($type);
                        if (\is_array($returnTypeTmp)) {
                            $this->typeFromPhpDocSimple = \implode('|', $returnTypeTmp);
                        } else {
                            $this->typeFromPhpDocSimple = $returnTypeTmp;
                        }

                        $this->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($this->typeFromPhpDoc);
                    }
                }
            }

            /** @noinspection AdditionOperationOnArraysInspection */
            $parsedParamTags = $phpDoc->getTagsByName('psalm-param')
                               + $phpDoc->getTagsByName('phpstan-param');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                        $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                        $parsedParamTagStr = $spitedData['parsedParamTagStr'];
                        $variableName = $spitedData['variableName'];

                        // check only the current "param"-tag
                        if (!$variableName || \strtoupper($parameterName) !== \strtoupper($variableName)) {
                            continue;
                        }

                        $this->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($parsedParamTagStr);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
        }
    }
}
