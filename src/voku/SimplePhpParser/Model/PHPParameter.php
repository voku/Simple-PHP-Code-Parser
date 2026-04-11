<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Comment\Doc;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use ReflectionParameter;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPParameter extends BasePHPElement
{
    /**
     * @var mixed|null
     */
    public $defaultValue;

    public ?string $phpDocRaw = null;

    public ?string $type = null;

    public ?string $typeFromDefaultValue = null;

    public ?string $typeFromPhpDoc = null;

    public ?string $typeFromPhpDocSimple = null;

    public ?string $typeFromPhpDocExtended = null;

    public ?string $typeFromPhpDocMaybeWithComment = null;

    public ?bool $is_vararg = null;

    public ?bool $is_passed_by_ref = null;

    public ?bool $is_inheritdoc = null;

    /**
     * PHP 8.0+ attributes on this parameter.
     *
     * @var PHPAttribute[]
     */
    public array $attributes = [];

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

                if (\stripos($docCommentText, '@inheritdoc') !== false) {
                    $this->is_inheritdoc = true;
                }

                $this->readPhpDoc($docComment, $this->name);
            }
        }

        if ($parameter->type !== null) {
            if (!$this->type) {
                $typeStr = Utils::typeNodeToString($parameter->type);
                if ($typeStr !== null) {
                    $this->type = $typeStr;
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

        // Extract PHP 8.0+ attributes (only if not already populated by reflection)
        if (empty($this->attributes) && !empty($parameter->attrGroups)) {
            $this->attributes = Utils::extractAttributesFromAstNode($parameter->attrGroups);
        }

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

        $method = $parameter->getDeclaringFunction();
        if (!$this->line) {
            $lineTmp = $method->getStartLine();
            if ($lineTmp !== false) {
                $this->line = $lineTmp;
            }
        }

        $fileTmp = $method->getFileName();
        if ($fileTmp !== false) {
            $this->file = $fileTmp;
        }

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

        $docComment = $method->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, '@inheritdoc') !== false) {
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
            if ($this->type && \class_exists($this->type, true)) {
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

        // Extract PHP 8.0+ attributes
        $this->attributes = Utils::extractAttributesFromReflection($parameter);

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

    /**
     * @param Doc|string $doc
     */
    private function readPhpDoc($doc, string $parameterName): void
    {
        if ($doc instanceof Doc) {
            $docComment = $doc->getText();
        } else {
            $docComment = $doc;
        }
        if ($docComment === '') {
            return;
        }

        try {
            $phpDoc = DocFactoryProvider::getDocFactory()->create($docComment);

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
                    $variableName = $spitedData['variableName'];

                    // check only the current "param"-tag
                    if ($variableName && \strtoupper($parameterName) === \strtoupper($variableName)) {
                        $this->phpDocRaw = $parsedParamTagParam;
                        $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagParam);
                    }

                    break;
                }
            }

            $parsedParamTags = $phpDoc->getTagsByName('psalm-param')
                               + $phpDoc->getTagsByName('phpstan-param');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if (!$parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                        continue;
                    }

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
        } catch (\Exception $e) {
            $this->addParseError($e);
        }

        try {
            $this->readPhpDocByTokens($docComment, $parameterName);
        } catch (\Exception $e) {
            $this->addParseError($e);
        }

        $this->reportBrokenParamTagWithoutType($docComment, $parameterName);
    }

    /**
     * @throws \PHPStan\PhpDocParser\Parser\ParserException
     */
    private function readPhpDocByTokens(string $docComment, string $parameterName): void
    {
        $tokens = Utils::modernPhpdocTokens($docComment);

        // Track standard (@param) and extended (@phpstan-param / @psalm-param) content separately
        // so that the more specific phpstan/psalm annotation always wins regardless of tag order.
        // We scan the ENTIRE docblock to find all occurrences of both tag types for this parameter.
        $paramContent = null;
        $extendedParamContent = null;
        $currentTarget = null; // 'standard' | 'extended'
        $currentContent = '';

        foreach ($tokens->getTokens() as $token) {
            $content = $token[0];

            if ($content === '@param') {
                $currentTarget = 'standard';
                $currentContent = '';
                continue;
            }

            if ($content === '@psalm-param' || $content === '@phpstan-param') {
                $currentTarget = 'extended';
                $currentContent = '';
                continue;
            }

            if ($currentTarget !== null) {
                // Check if we hit the target parameter variable e.g. `$param`.
                if ($content === '$' . $parameterName) {
                    if ($currentTarget === 'standard') {
                        $paramContent = \trim($currentContent);
                    } else {
                        $extendedParamContent = \trim($currentContent);
                    }
                    $currentTarget = null;
                    $currentContent = '';
                    continue;
                }

                // Check if we hit a different parameter variable — discard this tag.
                if (\strlen($content) > 1 && $content[0] === '$') {
                    $currentTarget = null;
                    $currentContent = '';
                    continue;
                }

                $currentContent .= $content;
            }
        }

        // Prefer @phpstan-param / @psalm-param over plain @param regardless of tag order.
        $bestContent = null;
        if ($extendedParamContent !== null && $extendedParamContent !== '') {
            $bestContent = $extendedParamContent;
        } elseif ($paramContent !== null && $paramContent !== '') {
            $bestContent = $paramContent;
        }

        if ($bestContent) {
            if (!$this->phpDocRaw) {
                $this->phpDocRaw = $bestContent . ' ' . '$' . $parameterName;
            }
            try {
                $this->typeFromPhpDocExtended = Utils::modernPhpdoc($bestContent);
            } catch (\PHPStan\PhpDocParser\Parser\ParserException $e) {
                $recoveredType = Utils::recoverBrokenPhpdocType($bestContent);
                if ($recoveredType !== null) {
                    $normalizedRecoveredType = Utils::normalizePhpType($recoveredType);
                    $this->typeFromPhpDoc = $this->typeFromPhpDoc ?? $normalizedRecoveredType;
                    $this->typeFromPhpDocSimple = $this->typeFromPhpDocSimple ?? $normalizedRecoveredType;
                    $this->typeFromPhpDocExtended = $recoveredType;
                }

                $this->addParseError($e);
            }
        }
    }

    private function reportBrokenParamTagWithoutType(string $docComment, string $parameterName): void
    {
        if ($this->line === null) {
            return;
        }

        if (!\preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/u', $parameterName)) {
            return;
        }

        if (
            !\preg_match(
                '#@(param|psalm-param|phpstan-param)[ \t]+\$' . $parameterName . '(?=[ \t\r\n\*]|$)#u',
                $docComment
            )
        ) {
            return;
        }

        try {
            // Re-parse the malformed tag payload to preserve the original parser
            // error message even though the docblock library now falls back to mixed.
            Utils::modernPhpdoc('$' . $parameterName);
        } catch (\Exception $e) {
            $this->addParseError($e);
        }
    }

    private function addParseError(\Exception $e): void
    {
        $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
        $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
    }
}
