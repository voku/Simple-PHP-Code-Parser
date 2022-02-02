<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Trait_;
use ReflectionClass;
use voku\SimplePhpParser\Parsers\Helper\Utils;

final class PHPTrait extends BasePHPClass
{
    /**
     * @var string|null
     *
     * @phpstan-var null|class-string
     */
    public $name;

    /**
     * @param Trait_ $node
     * @param null   $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        /** @noinspection NotOptimalIfConditionsInspection */
        /** @noinspection ArgumentEqualsDefaultValueInspection */
        if (\trait_exists($this->name, true)) {
            $reflectionClass = Utils::createClassReflectionInstance($this->name);
            $this->readObjectFromReflection($reflectionClass);
        }

        $this->collectTags($node);

        $docComment = $node->getDocComment();
        if ($docComment) {
            $this->readPhpDocProperties($docComment->getText());
        }

        foreach ($node->getProperties() as $property) {
            $propertyNameTmp = $this->getConstantFQN($property, $property->props[0]->name->name);

            if (isset($this->properties[$propertyNameTmp])) {
                $this->properties[$propertyNameTmp] = $this->properties[$propertyNameTmp]->readObjectFromPhpNode($property, $this->name);
            } else {
                $this->properties[$propertyNameTmp] = (new PHPProperty($this->parserContainer))->readObjectFromPhpNode($property, $this->name);
            }
        }

        foreach ($node->getMethods() as $method) {
            $methodNameTmp = $method->name->name;

            if (isset($this->methods[$methodNameTmp])) {
                $this->methods[$methodNameTmp] = $this->methods[$methodNameTmp]->readObjectFromPhpNode($method, $this->name);
            } else {
                $this->methods[$methodNameTmp] = (new PHPMethod($this->parserContainer))->readObjectFromPhpNode($method, $this->name);
            }

            if (!$this->methods[$methodNameTmp]->file) {
                $this->methods[$methodNameTmp]->file = $this->file;
            }
        }

        return $this;
    }

    /**
     * @param ReflectionClass $clazz
     *
     * @return $this
     */
    public function readObjectFromReflection($clazz): self
    {
        if (!$clazz->isTrait()) {
            return $this;
        }

        $this->name = $clazz->getName();

        if (!$this->line) {
            $this->line = $clazz->getStartLine();
        }

        $file = $clazz->getFileName();
        if ($file) {
            $this->file = $file;
        }

        foreach ($clazz->getProperties() as $property) {
            $propertyPhp = (new PHPProperty($this->parserContainer))->readObjectFromReflection($property);
            $this->properties[$propertyPhp->name] = $propertyPhp;
        }

        foreach ($clazz->getMethods() as $method) {
            $methodNameTmp = $method->getName();

            $this->methods[$methodNameTmp] = (new PHPMethod($this->parserContainer))->readObjectFromReflection($method);

            if (!$this->methods[$methodNameTmp]->file) {
                $this->methods[$methodNameTmp]->file = $this->file;
            }
        }

        foreach ($clazz->getReflectionConstants() as $constant) {
            $constantNameTmp = $constant->getName();

            $this->constants[$constantNameTmp] = (new PHPConst($this->parserContainer))->readObjectFromReflection($constant);

            if (!$this->constants[$constantNameTmp]->file) {
                $this->constants[$constantNameTmp]->file = $this->file;
            }
        }

        return $this;
    }

    /**
     * @param string[] $access
     * @param bool     $skipMethodsWithLeadingUnderscore
     *
     * @return array
     *
     * @psalm-return array<string, array{type: null|string, typeFromPhpDocMaybeWithComment: null|string, typeFromPhpDoc: null|string, typeFromPhpDocSimple: null|string, typeFromPhpDocExtended: null|string, typeFromDefaultValue: null|string}>
     */
    public function getPropertiesInfo(
        array $access = ['public', 'protected', 'private'],
        bool $skipMethodsWithLeadingUnderscore = false
    ): array {
        // init
        $allInfo = [];

        foreach ($this->properties as $property) {
            if (!\in_array($property->access, $access, true)) {
                continue;
            }

            if ($skipMethodsWithLeadingUnderscore && \strpos($property->name, '_') === 0) {
                continue;
            }

            $types = [];
            $types['type'] = $property->type;
            $types['typeFromPhpDocMaybeWithComment'] = $property->typeFromPhpDocMaybeWithComment;
            $types['typeFromPhpDoc'] = $property->typeFromPhpDoc;
            $types['typeFromPhpDocSimple'] = $property->typeFromPhpDocSimple;
            $types['typeFromPhpDocExtended'] = $property->typeFromPhpDocExtended;
            $types['typeFromDefaultValue'] = $property->typeFromDefaultValue;

            $allInfo[$property->name] = $types;
        }

        return $allInfo;
    }

    /**
     * @param string[] $access
     * @param bool     $skipDeprecatedMethods
     * @param bool     $skipMethodsWithLeadingUnderscore
     *
     * @return array<mixed>
     *
     * @psalm-return array<string, array{
     *     fullDescription: string,
     *     line: null|int,
     *     file: null|string,
     *     error: string,
     *     is_deprecated: bool,
     *     is_static: null|bool,
     *     is_meta: bool,
     *     is_internal: bool,
     *     is_removed: bool,
     *     paramsTypes: array<string,
     *         array{
     *              ?type: null|string,
     *              ?typeFromPhpDoc: null|string,
     *              ?typeFromPhpDocExtended: null|string,
     *              ?typeFromPhpDocSimple: null|string,
     *              ?typeFromPhpDocMaybeWithComment: null|string,
     *              ?typeFromDefaultValue: null|string
     *         }
     *     >,
     *     returnTypes: array{
     *         type: null|string,
     *         typeFromPhpDoc: null|string,
     *         typeFromPhpDocExtended: null|string,
     *         typeFromPhpDocSimple: null|string,
     *         typeFromPhpDocMaybeWithComment: null|string
     *     },
     *     paramsPhpDocRaw: array<string, null|string>,
     *     returnPhpDocRaw: null|string
     *  }>
     */
    public function getMethodsInfo(
        array $access = ['public', 'protected', 'private'],
        bool $skipDeprecatedMethods = false,
        bool $skipMethodsWithLeadingUnderscore = false
    ): array {
        // init
        $allInfo = [];

        foreach ($this->methods as $method) {
            if (!\in_array($method->access, $access, true)) {
                continue;
            }

            if ($skipDeprecatedMethods && $method->hasDeprecatedTag) {
                continue;
            }

            if ($skipMethodsWithLeadingUnderscore && \strpos($method->name, '_') === 0) {
                continue;
            }

            $paramsTypes = [];
            foreach ($method->parameters as $tagParam) {
                $paramsTypes[$tagParam->name]['type'] = $tagParam->type;
                $paramsTypes[$tagParam->name]['typeFromPhpDocMaybeWithComment'] = $tagParam->typeFromPhpDocMaybeWithComment;
                $paramsTypes[$tagParam->name]['typeFromPhpDoc'] = $tagParam->typeFromPhpDoc;
                $paramsTypes[$tagParam->name]['typeFromPhpDocSimple'] = $tagParam->typeFromPhpDocSimple;
                $paramsTypes[$tagParam->name]['typeFromPhpDocExtended'] = $tagParam->typeFromPhpDocExtended;
                $paramsTypes[$tagParam->name]['typeFromDefaultValue'] = $tagParam->typeFromDefaultValue;
            }

            $returnTypes = [];
            $returnTypes['type'] = $method->returnType;
            $returnTypes['typeFromPhpDocMaybeWithComment'] = $method->returnTypeFromPhpDocMaybeWithComment;
            $returnTypes['typeFromPhpDoc'] = $method->returnTypeFromPhpDoc;
            $returnTypes['typeFromPhpDocSimple'] = $method->returnTypeFromPhpDocSimple;
            $returnTypes['typeFromPhpDocExtended'] = $method->returnTypeFromPhpDocExtended;

            $paramsPhpDocRaw = [];
            foreach ($method->parameters as $tagParam) {
                $paramsPhpDocRaw[$tagParam->name] = $tagParam->phpDocRaw;
            }

            $infoTmp = [];
            $infoTmp['fullDescription'] = \trim($method->summary . "\n\n" . $method->description);
            $infoTmp['paramsTypes'] = $paramsTypes;
            $infoTmp['returnTypes'] = $returnTypes;
            $infoTmp['paramsPhpDocRaw'] = $paramsPhpDocRaw;
            $infoTmp['returnPhpDocRaw'] = $method->returnPhpDocRaw;
            $infoTmp['line'] = $method->line;
            $infoTmp['file'] = $method->file;
            $infoTmp['error'] = \implode("\n", $method->parseError);
            foreach ($method->parameters as $parameter) {
                $infoTmp['error'] .= ($infoTmp['error'] ? "\n" : '') . \implode("\n", $parameter->parseError);
            }
            $infoTmp['is_deprecated'] = $method->hasDeprecatedTag;
            $infoTmp['is_static'] = $method->is_static;
            $infoTmp['is_meta'] = $method->hasMetaTag;
            $infoTmp['is_internal'] = $method->hasInternalTag;
            $infoTmp['is_removed'] = $method->hasRemovedTag;

            $allInfo[$method->name] = $infoTmp;
        }

        \asort($allInfo);

        return $allInfo;
    }

    /**
     * @param string $docComment
     *
     * @return void
     */
    private function readPhpDocProperties(string $docComment): void
    {
        if ($docComment === '') {
            return;
        }

        // hack, until this is merged: https://github.com/phpDocumentor/TypeResolver/pull/139
        $docComment = preg_replace('#int<.*>#i', 'int', $docComment);

        try {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedPropertyTags = $phpDoc->getTagsByName('property')
                               + $phpDoc->getTagsByName('property-read')
                               + $phpDoc->getTagsByName('property-write');

            if (!empty($parsedPropertyTags)) {
                foreach ($parsedPropertyTags as $parsedPropertyTag) {
                    if (
                        $parsedPropertyTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\PropertyRead
                        ||
                        $parsedPropertyTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite
                        ||
                        $parsedPropertyTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Property
                    ) {
                        $propertyPhp = new PHPProperty($this->parserContainer);

                        $nameTmp = $parsedPropertyTag->getVariableName();
                        if (!$nameTmp) {
                            continue;
                        }

                        $propertyPhp->name = $nameTmp;

                        $propertyPhp->access = 'public';

                        $type = $parsedPropertyTag->getType();

                        $propertyPhp->typeFromPhpDoc = Utils::normalizePhpType($type . '');

                        $typeFromPhpDocMaybeWithCommentTmp = \trim((string) $parsedPropertyTag);
                        if (
                            $typeFromPhpDocMaybeWithCommentTmp
                            &&
                            \strpos($typeFromPhpDocMaybeWithCommentTmp, '$') !== 0
                        ) {
                            $propertyPhp->typeFromPhpDocMaybeWithComment = $typeFromPhpDocMaybeWithCommentTmp;
                        }

                        $typeTmp = Utils::parseDocTypeObject($type);
                        if ($typeTmp !== '') {
                            $propertyPhp->typeFromPhpDocSimple = $typeTmp;
                        }

                        if ($propertyPhp->typeFromPhpDoc) {
                            $propertyPhp->typeFromPhpDocExtended = Utils::modernPhpdoc($propertyPhp->typeFromPhpDoc);
                        }

                        $this->properties[$propertyPhp->name] = $propertyPhp;
                    }
                }
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = ($this->name ?? '?') . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }
}
