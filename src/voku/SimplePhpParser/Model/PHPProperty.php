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
    public $typeFromPhpDocMaybeWithComment;

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
        if ($docComment) {
            $docCommentText = $docComment->getText();

            if (\stripos($docCommentText, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docCommentText);
        }

        if (!$this->type && $node->type !== null) {
            /** @noinspection MissingIssetImplementationInspection */
            if (empty($node->type->name)) {
                /** @noinspection MissingIssetImplementationInspection */
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
    public function readObjectFromBetterReflection($property): self
    {
        $this->name = $property->getName();

        $file = $property->getDeclaringClass()->getFileName();
        if ($file) {
            $this->file = $file;
        }

        $this->is_static = $property->isStatic();

        if ($this->is_static) {
            if (\class_exists($property->getDeclaringClass()->getName(), true)) {
                $this->defaultValue = $property->getValue();
            }

            if ($this->defaultValue !== null) {
                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        $docComment = $property->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, 'inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment);
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
                    if ($this->type && $this->type !== 'null') {
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

    private function readPhpDoc(string $docComment): void
    {
        if ($docComment === '') {
            return;
        }

        try {
            $regexIntValues = '/@.*?var\s+(?<intValues>\d[\|\d]*)(?<comment>.*)/ui';
            if (\preg_match($regexIntValues, $docComment, $matchesIntValues)) {
                $this->typeFromPhpDoc = 'int';
                $this->typeFromPhpDocMaybeWithComment = 'int' . (\trim($matchesIntValues['comment']) ? ' ' . \trim($matchesIntValues['comment']) : '');
                $this->typeFromPhpDocSimple = 'int';
                $this->typeFromPhpDocPslam = $matchesIntValues['intValues'];

                return;
            }

            $regexAnd = '/@.*?var\s+(?<type>(?<type1>[\S]+)&(?<type2>[\S]+))(?<comment>.*)/ui';
            if (\preg_match($regexAnd, $docComment, $matchesAndValues)) {
                $this->typeFromPhpDoc = $matchesAndValues['type1'] . '|' . $matchesAndValues['type2'];
                $this->typeFromPhpDocMaybeWithComment = $matchesAndValues['type'] . (\trim($matchesAndValues['comment']) ? ' ' . \trim($matchesAndValues['comment']) : '');
                $this->typeFromPhpDocSimple = $matchesAndValues['type1'] . '|' . $matchesAndValues['type2'];
                $this->typeFromPhpDocPslam = $matchesAndValues['type'];

                return;
            }

            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedParamTags = $phpDoc->getTagsByName('var');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Var_) {
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

                        if ($this->typeFromPhpDoc) {
                            $this->typeFromPhpDocPslam = Utils::modernPhpdoc($this->typeFromPhpDoc);
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

                        $this->typeFromPhpDocPslam = Utils::modernPhpdoc($parsedParamTagStr);
                    }
                }
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);

            // DEBUG
            //\var_dump($tmpErrorMessage, $e->getTraceAsString());

            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }
}
