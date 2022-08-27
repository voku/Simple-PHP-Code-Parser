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
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $phpDocRaw;

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
    public $typeFromPhpDocExtended;

    /**
     * @var string|null
     */
    public $typeFromPhpDocMaybeWithComment;

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
     * @param mixed|null   $classStr
     *
     * @return $this
     */
    public function readObjectFromPhpNode($parameter, $node = null, $classStr = null): self
    {
        $parameterVar = $parameter->var;
        if ($parameterVar instanceof \PhpParser\Node\Expr\Error) {
            $this->parseError[] = ($this->line ?? '?') . ':' . ($this->pos ?? '') . ' | may be at this position an expression is required';

            $this->name = \md5(\uniqid('error', true));

            return $this;
        }

        $this->name = \is_string($parameterVar->name) ? $parameterVar->name : '';

        if ($node) {
            $this->prepareNode($node);

            $docComment = $node->getDocComment();
            if ($docComment) {
                $docCommentText = $docComment->getText();

                if (\stripos($docCommentText, 'inheritdoc') !== false) {
                    $this->is_inheritdoc = true;
                }

                $this->readPhpDoc($docCommentText, $this->name);
            }
        }

        if ($parameter->type !== null) {
            if (!$this->type) {
                if (empty($parameter->type->name)) {
                    if (!empty($parameter->type->parts)) {
                        $this->type = '\\' . \implode('\\', $parameter->type->parts);
                    }
                } else {
                    $this->type = $parameter->type->name;
                }
            }

            if ($parameter->type instanceof \PhpParser\Node\NullableType) {
                if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                    $this->type = 'null|' . $this->type;
                } elseif (!$this->type) {
                    $this->type = 'null|mixed';
                }
            }
        }

        if ($parameter->default) {
            $defaultValue = Utils::getPhpParserValueFromNode($parameter->default, $classStr, $this->parserContainer);
            if ($defaultValue !== Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                $this->defaultValue = $defaultValue;

                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
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
        $this->name = $parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            try {
                $this->defaultValue = $parameter->getDefaultValue();
            } catch (\ReflectionException $e) {
                // nothing
            }
            if ($this->defaultValue !== null) {
                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        $method = $parameter->getDeclaringFunction();

        $docComment = $method->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment, $this->name);
        }

        try {
            $type = $parameter->getType();
        } catch (\ReflectionException $e) {
            $type = null;
        }
        if ($type !== null) {
            if (\method_exists($type, 'getName')) {
                $this->type = Utils::normalizePhpType($type->getName(), true);
            } else {
                $this->type = Utils::normalizePhpType($type . '', true);
            }
            if ($this->type && \class_exists($this->type, false)) {
                $this->type = '\\' . \ltrim($this->type, '\\');
            }

            try {
                $constNameTmp = $parameter->getDefaultValueConstantName();
                if ($constNameTmp && \defined($constNameTmp)) {
                    $defaultTmp = \constant($constNameTmp);
                    if ($defaultTmp === null) {
                        if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                            $this->type = 'null|' . $this->type;
                        } elseif (!$this->type) {
                            $this->type = 'null|mixed';
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                if ($type->allowsNull()) {
                    if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                        $this->type = 'null|' . $this->type;
                    } elseif (!$this->type) {
                        $this->type = 'null|mixed';
                    }
                }
            }
        }

        $this->is_vararg = $parameter->isVariadic();

        $this->is_passed_by_ref = $parameter->isPassedByReference();

        return $this;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        if ($this->typeFromPhpDocExtended) {
            return $this->typeFromPhpDocExtended;
        }

        if ($this->type) {
            return $this->type;
        }

        if ($this->typeFromPhpDocSimple) {
            return $this->typeFromPhpDocSimple;
        }

        return null;
    }

    private function readPhpDoc(string $docComment, string $parameterName): void
    {
        if ($docComment === '') {
            return;
        }

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

                        $this->typeFromPhpDoc = Utils::normalizePhpType($type . '');

                        $typeFromPhpDocMaybeWithCommentTmp = \trim((string) $parsedParamTag);
                        if (
                            $typeFromPhpDocMaybeWithCommentTmp
                            &&
                            \strpos($typeFromPhpDocMaybeWithCommentTmp, '$') !== 0
                        ) {
                            $this->typeFromPhpDocMaybeWithComment = $typeFromPhpDocMaybeWithCommentTmp;
                        }

                        $typeTmp = Utils::parseDocTypeObject($type);
                        if ($typeTmp !== '') {
                            $this->typeFromPhpDocSimple = $typeTmp;
                        }
                    }

                    $parsedParamTagParam = (string) $parsedParamTag;
                    $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                    $parsedParamTagStr = $spitedData['parsedParamTagStr'];
                    $variableName = $spitedData['variableName'];

                    // check only the current "param"-tag
                    if ($variableName && \strtoupper($parameterName) === \strtoupper($variableName)) {
                        $this->phpDocRaw = (string) $parsedParamTag;
                        $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagParam);
                    }
                }
            }

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

                        $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagStr);
                    }
                }
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }
}
