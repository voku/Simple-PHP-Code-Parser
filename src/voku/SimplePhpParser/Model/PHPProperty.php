<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Property;
use ReflectionProperty;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPProperty extends BasePHPElement
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
     * @var string
     */
    public $access;

    /**
     * @var bool
     */
    public $is_static;

    /**
     * @param Property $node
     * @param null     $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->name = $this->getConstantFQN($node, $node->props[0]->name->name);

        if ($this->usePhpReflection() === true) {
            return $this;
        }

        $this->prepareNode($node);

        $docComment = $node->getDocComment();
        if ($docComment !== null) {
            try {
                $this->readPhpDoc($docComment->getText());
            } catch (\Exception $e) {
                $this->parseError .= $this->line . ':' . $this->pos . ' | ' . \print_r($e->getMessage(), true);
            }
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

        $this->is_static = $node->isStatic();

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
        $this->name = $property->name;

        $docComment = $this->readObjectFromReflectionVarHelper($property);

        if ($docComment !== null) {
            $docComment = '/** ' . $docComment . ' */';

            try {
                $this->readPhpDoc($docComment);
            } catch (\Exception $e) {
                $this->parseError .= $this->line . ':' . $this->pos . ' | ' . \print_r($e->getMessage(), true);
            }
        }

        /** @noinspection ClassMemberExistenceCheckInspection */
        if (\method_exists($property, 'getType')) {
            $type = $property->getType();
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
        }

        $this->is_static = $property->isStatic();

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
     * @param ReflectionProperty $property
     *
     * @return string|null Type of the property (content of var annotation)
     */
    private function readObjectFromReflectionVarHelper(ReflectionProperty $property): ?string
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

                        $this->typeFromPhpDoc = $type . '';

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
            $parsedParamTags = $phpDoc->getTagsByName('pslam-var')
                               + $phpDoc->getTagsByName('phpstan-var');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                        $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                        $parsedParamTagStr = $spitedData['parsedParamTagStr'];

                        $this->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($parsedParamTagStr);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->parseError .= $this->line . ':' . $this->pos . ' | ' . \print_r($e->getMessage(), true);
        }
    }
}
