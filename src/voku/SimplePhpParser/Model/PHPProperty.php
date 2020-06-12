<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Property;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPProperty extends BasePHPElement
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
    public $is_inheritdoc;

    /**
     * @param Property    $node
     * @param string|null $classStr
     *
     * @psalm-param class-string|null $classStr
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $classStr = null): self
    {
        $this->name = $this->getConstantFQN($node, $node->props[0]->name->name);

        $this->is_static = $node->isStatic();

        $this->prepareNode($node);

        $docComment = $node->getDocComment();
        if ($docComment !== null) {
            $docCommentText = $docComment->getText();

            if (\stripos($docCommentText, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docCommentText);
        }

        if ($node->type !== null) {
            if (empty($node->type->name)) {
                if (!empty($node->type->parts)) {
                    $this->type = '\\' . \implode('\\', $node->type->parts);
                }
            } else {
                $this->type = $node->type->name;
            }
        }

        if ($node->props[0]->default !== null) {
            $defaultValue = Utils::getPhpParserValueFromNode($node->props[0]->default, $classStr);
            if ($defaultValue !== Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                $this->defaultValue = $defaultValue;
            }
        }

        if ($this->defaultValue !== null) {
            $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
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
    public function readObjectFromBetterReflection($property): self
    {
        $this->name = $property->getName();

        $this->is_static = $property->isStatic();

        if ($this->is_static) {
            $this->defaultValue = $property->getValue();

            if ($this->defaultValue !== null) {
                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        $docComment = $this->readObjectFromBetterReflectionVarHelper($property);

        if ($docComment !== null) {
            $docCommentText = '/** ' . $docComment . ' */';

            if (\stripos($docCommentText, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docCommentText);
        }

        /** @noinspection ClassMemberExistenceCheckInspection */
        if (\method_exists($property, 'getType')) {
            $type = $property->getType();
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
        if ($this->typeFromPhpDocPslam) {
            return $this->typeFromPhpDocPslam;
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
     * @param ReflectionProperty $property
     *
     * @return string|null Type of the property (content of var annotation)
     */
    private function readObjectFromBetterReflectionVarHelper(ReflectionProperty $property): ?string
    {
        // Get the content of the @var annotation.

        $phpDoc = $property->getDocComment();
        if (!$phpDoc) {
            return null;
        }

        if (\preg_match_all('/(@.*?var\s+[^\s]+\s+)/ui', $phpDoc, $matches)) {
            $param = '';
            foreach ($matches[0] as $match) {
                $param .= $match . "\n";
            }
        } else {
            return null;
        }

        return $param;
    }

    private function readPhpDoc(string $docComment): void
    {
        try {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedParamTags = $phpDoc->getTagsByName('var');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Var_) {
                        $type = $parsedParamTag->getType();

                        $this->typeFromPhpDoc = Utils::normalizePhpType($type . '');

                        $typeMaybeWithCommentTmp = \trim((string) $parsedParamTag);
                        if (
                            $typeMaybeWithCommentTmp
                            &&
                            \strpos($typeMaybeWithCommentTmp, '$') !== 0
                        ) {
                            $this->typeMaybeWithComment = $typeMaybeWithCommentTmp;
                        }

                        $typeTmp = Utils::parseDocTypeObject($type);
                        if (\is_array($typeTmp) && \count($typeTmp) > 0) {
                            $this->typeFromPhpDocSimple = \implode('|', $typeTmp);
                        } elseif (\is_string($typeTmp) && $typeTmp !== '') {
                            $this->typeFromPhpDocSimple = $typeTmp;
                        }

                        if ($this->typeFromPhpDoc) {
                            /** @noinspection PhpUsageOfSilenceOperatorInspection */
                            $this->typeFromPhpDocPslam = (string) @\Psalm\Type::parseString($this->typeFromPhpDoc);
                        }
                    }
                }
            }

            /** @noinspection AdditionOperationOnArraysInspection */
            $parsedParamTags = $phpDoc->getTagsByName('pslam-var')
                               + $phpDoc->getTagsByName('phpstan-var');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                        $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                        $parsedParamTagStr = $spitedData['parsedParamTagStr'];

                        /** @noinspection PhpUsageOfSilenceOperatorInspection */
                        $this->typeFromPhpDocPslam = (string) @\Psalm\Type::parseString($parsedParamTagStr);
                    }
                }
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }
}
