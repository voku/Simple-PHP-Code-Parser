<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Property;
use ReflectionProperty;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPProperty extends BasePHPElement
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

    /**
     * @phpstan-var ''|'private'|'protected'|'public'
     */
    public string $access = '';

    public ?bool $is_static = null;

    public ?bool $is_readonly = null;

    public ?bool $is_inheritdoc = null;

    /**
     * @param Property    $node
     * @param string|null $classStr
     *
     * @phpstan-param class-string|null $classStr
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $classStr = null): self
    {
        $this->name = $this->getConstantFQN($node, $node->props[0]->name->name);

        $this->is_static = $node->isStatic();

        if (method_exists($node, 'isReadonly')) {
            $this->is_readonly = $node->isReadonly();
        }

        $this->prepareNode($node);

        $docComment = $node->getDocComment();
        if ($docComment) {
            $docCommentText = $docComment->getText();

            if (\stripos($docCommentText, '@inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
        }

        if ($node->type !== null) {
            if (!$this->type) {
                if (\method_exists($node->type, 'getParts')) {
                    $parts = $node->type->getParts();
                    if (!empty($parts)) {
                        $this->type = '\\' . \implode('\\', $parts);
                    }
                } elseif (\property_exists($node->type, 'name') && $node->type->name) {
                    $this->type = $node->type->name;
                }
            }

            if ($node->type instanceof \PhpParser\Node\NullableType) {
                if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                    $this->type = 'null|' . $this->type;
                } elseif (!$this->type) {
                    $this->type = 'null|mixed';
                }
            }
        }

        if ($node->props[0]->default !== null) {
            $defaultValue = Utils::getPhpParserValueFromNode($node->props[0]->default, $classStr);
            if ($defaultValue !== Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                $this->defaultValue = $defaultValue;

                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        if ($node->isPrivate()) {
            $this->access = 'private';
        } elseif ($node->isProtected()) {
            $this->access = 'protected';
        } else {
            $this->access = 'public';
        }

        return $this;
    }

    /**
     * @param ReflectionProperty $property
     *
     * @return $this
     */
    public function readObjectFromReflection($property): self
    {
        $this->name = $property->getName();

        $file = $property->getDeclaringClass()->getFileName();
        if ($file) {
            $this->file = $file;
        }

        $this->is_static = $property->isStatic();

        if ($this->is_static) {
            try {
                if (\class_exists($property->getDeclaringClass()->getName(), true)) {
                    $this->defaultValue = $property->getValue();
                }
            } catch (\Exception $e) {
                // nothing
            }

            if ($this->defaultValue !== null) {
                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        if (method_exists($property, 'isReadOnly')) {
            $this->is_readonly = $property->isReadOnly();
        }

        $docComment = $property->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, '@inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
        }

        if (\method_exists($property, 'getType')) {
            $type = $property->getType();
            if ($type !== null) {
                if (\method_exists($type, 'getName')) {
                    $this->type = Utils::normalizePhpType($type->getName(), true);
                } else {
                    $this->type = Utils::normalizePhpType($type . '', true);
                }
                try {
                    if ($this->type && \class_exists($this->type, true)) {
                        $this->type = '\\' . \ltrim($this->type, '\\');
                    }
                } catch (\Exception $e) {
                    // nothing
                }

                if ($type->allowsNull()) {
                    if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                        $this->type = 'null|' . $this->type;
                    } elseif (!$this->type) {
                        $this->type = 'null|mixed';
                    }
                }
            }
        }

        if ($property->isProtected()) {
            $access = 'protected';
        } elseif ($property->isPrivate()) {
            $access = 'private';
        } else {
            $access = 'public';
        }
        $this->access = $access;

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
    private function readPhpDoc($doc): void
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
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedParamTags = $phpDoc->getTagsByName('var');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    $parsedParamTagParam = (string) $parsedParamTag;

                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Var_) {
                        $type = $parsedParamTag->getType();

                        $this->typeFromPhpDoc = Utils::normalizePhpType($type . '');

                        $typeFromPhpDocMaybeWithCommentTmp = \trim($parsedParamTagParam);
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

                    $this->phpDocRaw = $parsedParamTagParam;
                    $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagParam);
                }
            }

            $parsedParamTags = $phpDoc->getTagsByName('psalm-var')
                               + $phpDoc->getTagsByName('phpstan-var');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if (!$parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                        continue;
                    }

                    $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                    $parsedParamTagStr = $spitedData['parsedParamTagStr'];

                    $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagStr);
                }
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }

        try {
            $this->readPhpDocByTokens($docComment);
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }

    /**
     * @throws \PHPStan\PhpDocParser\Parser\ParserException
     */
    private function readPhpDocByTokens(string $docComment): void
    {
        $tokens = Utils::modernPhpdocTokens($docComment);

        $varContent = null;
        foreach ($tokens->getTokens() as $token) {
            $content = $token[0];

            if ($content === '@var' || $content === '@psalm-var' || $content === '@phpstan-var') {
                // reset
                $varContent = '';

                continue;
            }

            if ($varContent !== null) {
                $varContent .= $content;
            }
        }

        $varContent = $varContent ? \trim($varContent) : null;
        if ($varContent) {
            if (!$this->phpDocRaw) {
                $this->phpDocRaw = $varContent;
            }
            $this->typeFromPhpDocExtended = Utils::modernPhpdoc($varContent);
        }
    }
}
