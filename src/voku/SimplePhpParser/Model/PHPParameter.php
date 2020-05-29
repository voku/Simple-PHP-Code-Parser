<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionParameter;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPParameter extends BasePHPElement
{
    /**
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $type;

    /**
     * @var string|null
     */
    public $typeFromDefaultValue;

    /**
     * @var string|null
     */
    public $typeFromPhpDoc;

    /**
     * @var string|null
     */
    public $typeFromPhpDocSimple;

    /**
     * @var string|null
     */
    public $typeFromPhpDocPslam;

    /**
     * @var string|null
     */
    public $typeMaybeWithComment;

    /**
     * @var bool|null
     */
    public $is_vararg;

    /**
     * @var bool|null
     */
    public $is_passed_by_ref;

    /**
     * @var bool|null
     */
    public $is_inheritdoc;

    /**
     * @param Param        $parameter
     * @param FunctionLike $node
     *
     * @return $this
     */
    public function readObjectFromPhpNode($parameter, $node = null): self
    {
        $parameterVar = $parameter->var;
        if ($parameterVar instanceof \PhpParser\Node\Expr\Error) {
            $this->parseError[] = ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | may be at this position an expression is required';

            $this->name = \md5(\uniqid('error', true));

            return $this;
        }

        $this->name = \is_string($parameterVar->name) ? $parameterVar->name : '';

        if ($this->usePhpReflection() === true) {
            return $this;
        }

        if ($node) {
            $this->prepareNode($node);

            $docComment = $node->getDocComment();
            if ($docComment !== null) {
                $docCommentText = $docComment->getText();

                if (\stripos($docCommentText, 'inheritdoc') !== false) {
                    // TODO: inheritdoc
                    $this->is_inheritdoc = true;
                }

                $this->readPhpDoc($docCommentText, $this->name);
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

        if ($parameter->default) {
            $defaultValue = Utils::getPhpParserValueFromNode($parameter->default);
            if ($defaultValue !== Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                $this->defaultValue = $defaultValue;
            }
        }

        if ($this->defaultValue !== null) {
            $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
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
        $this->name = $parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            $this->defaultValue = $parameter->getDefaultValue();

            if ($this->defaultValue !== null) {
                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        $docComment = $this->readObjectFromReflectionParamHelper($parameter);
        if ($docComment !== null) {
            $docCommentText = '/** ' . $docComment . ' */';

            if (\stripos($docCommentText, 'inheritdoc') !== false) {
                // TODO: inheritdoc
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docCommentText, $this->name);
        }

        $type = $parameter->getType();
        if ($type !== null) {
            if (\method_exists($type, 'getName')) {
                $this->type = Utils::normalizePhpType($type->getName());
            } else {
                $this->type = Utils::normalizePhpType($type . '');
            }
            if ($this->type && \class_exists($this->type, false)) {
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
    private function readObjectFromReflectionParamHelper($parameter): ?string
    {
        // Get the content of the @param annotation.
        $method = $parameter->getDeclaringFunction();

        $phpDoc = $method->getDocComment();
        if (!$phpDoc) {
            return null;
        }

        if (\preg_match_all('/(@.*?param\s+[^\s]+\s+\$' . $parameter->getName() . ')/ui', $phpDoc, $matches)) {
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
                            $this->typeFromPhpDoc = Utils::normalizePhpType($type . '');
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

                        if ($this->typeFromPhpDoc) {
                            $this->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($this->typeFromPhpDoc);
                        }
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
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }
}
