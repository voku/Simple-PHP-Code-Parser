<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Class_;
use ReflectionClass;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPClass extends BasePHPClass
{
    /**
     * @var string|null
     */
    public $parentClass;

    /**
     * @var string[]
     */
    public $interfaces = [];

    /**
     * @param Class_ $node
     * @param null   $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = $this->getFQN($node);

        if (
            ($this->usePhpReflection() === null || $this->usePhpReflection() === true)
            &&
            \class_exists($this->name)
        ) {
            try {
                $reflectionClass = new ReflectionClass($this->name);
                $this->readObjectFromReflection($reflectionClass);
            } catch (\ReflectionException $e) {
                if ($this->usePhpReflection() === true) {
                    throw $e;
                }

                // ignore
            }
        }

        if ($this->usePhpReflection() === true) {
            return $this;
        }

        $this->collectTags($node);

        if (!empty($node->extends)) {
            $this->parentClass = '';
            foreach ($node->extends->parts as $part) {
                $this->parentClass .= "\\${part}";
            }
            $this->parentClass = \ltrim($this->parentClass, '\\');
        }

        $docComment = $node->getDocComment();
        if ($docComment) {
            $propertiesPhp = $this->readPhpDocProperties($docComment->getText());
            foreach ($propertiesPhp as $propertyPhp) {
                $this->properties[$propertyPhp->name] = $propertyPhp;
            }
        }

        foreach ($node->getProperties() as $property) {
            $propertyPhp = (new PHPProperty($this->usePhpReflection()))->readObjectFromPhpNode($property);
            $this->properties[$propertyPhp->name] = $propertyPhp;
        }

        if (!empty($node->implements)) {
            foreach ($node->implements as $interfaceObject) {
                $interfaceFQN = '';
                foreach ($interfaceObject->parts as $interface) {
                    $interfaceFQN .= "\\${interface}";
                }
                $interfaceFQN = \ltrim($interfaceFQN, '\\');
                $this->interfaces[$interfaceFQN] = $interfaceFQN;
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
        $this->name = $clazz->getName();

        $parent = $clazz->getParentClass();
        if ($parent !== false) {
            $this->parentClass = $parent->getName();
        }

        $docComment = $clazz->getDocComment();
        if ($docComment) {
            $propertiesPhp = $this->readPhpDocProperties($docComment);
            foreach ($propertiesPhp as $propertyPhp) {
                $this->properties[$propertyPhp->name] = $propertyPhp;
            }
        }

        foreach ($clazz->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $this->name) {
                continue;
            }

            $propertyPhp = (new PHPProperty($this->usePhpReflection()))->readObjectFromReflection($property);
            $this->properties[$propertyPhp->name] = $propertyPhp;
        }

        foreach ($clazz->getInterfaceNames() as $interfaceName) {
            $this->interfaces[$interfaceName] = $interfaceName;
        }

        foreach ($clazz->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $this->name) {
                continue;
            }

            $this->methods[$method->name] = (new PHPMethod($this->usePhpReflection()))->readObjectFromReflection($method);
        }

        foreach ($clazz->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $this->name) {
                continue;
            }

            $this->constants[$constant->name] = (new PHPConst($this->usePhpReflection()))->readObjectFromReflection($constant);
        }

        return $this;
    }

    /**
     * @param string[] $access
     * @param bool     $skipMethodsWithLeadingUnderscore
     *
     * @return array
     *
     * @psalm-return array<string, array{type: string, typeMaybeWithComment: string, typeFromPhpDoc: string, typeFromPhpDocSimple: string, typeFromPhpDocPslam: string}>
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
            $types['typeMaybeWithComment'] = $property->typeMaybeWithComment;
            $types['typeFromPhpDoc'] = $property->typeFromPhpDoc;
            $types['typeFromPhpDocSimple'] = $property->typeFromPhpDocSimple;
            $types['typeFromPhpDocPslam'] = $property->typeFromPhpDocPslam;

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
     * @psslm-return array<string, array{fullDescription: string, paramsTypes: array<string, array{type: string, typeFromPhpDoc: string, typeFromPhpDocPslam: string, typeFromPhpDocSimple: string, typeMaybeWithComment: string}>, returnTypes: array{type: string, typeFromPhpDoc: string, typeFromPhpDocPslam: string, typeFromPhpDocSimple: string, typeMaybeWithComment: string}}>
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

            if ($skipDeprecatedMethods && $method->is_deprecated) {
                continue;
            }

            if ($skipMethodsWithLeadingUnderscore && \strpos($method->name, '_') === 0) {
                continue;
            }

            $paramsTypes = [];
            foreach ($method->parameters as $tagParam) {
                /** @var PHPParameter $tagParam */
                $paramsTypes[$tagParam->name]['type'] = $tagParam->type;
                $paramsTypes[$tagParam->name]['typeMaybeWithComment'] = $tagParam->typeMaybeWithComment;
                $paramsTypes[$tagParam->name]['typeFromPhpDoc'] = $tagParam->typeFromPhpDoc;
                $paramsTypes[$tagParam->name]['typeFromPhpDocSimple'] = $tagParam->typeFromPhpDocSimple;
                $paramsTypes[$tagParam->name]['typeFromPhpDocPslam'] = $tagParam->typeFromPhpDocPslam;
            }

            $returnTypes = [];
            $returnTypes['type'] = $method->returnType;
            $returnTypes['typeMaybeWithComment'] = $method->returnTypeMaybeWithComment;
            $returnTypes['typeFromPhpDoc'] = $method->returnTypeFromPhpDoc;
            $returnTypes['typeFromPhpDocSimple'] = $method->returnTypeFromPhpDocSimple;
            $returnTypes['typeFromPhpDocPslam'] = $method->returnTypeFromPhpDocPslam;

            $infoTmp = [];
            $infoTmp['fullDescription'] = \trim($method->summary . "\n\n" . $method->description);
            $infoTmp['paramsTypes'] = $paramsTypes;
            $infoTmp['returnTypes'] = $returnTypes;

            $allInfo[$method->name] = $infoTmp;
        }

        return $allInfo;
    }

    /**
     * @param string $docComment
     *
     * @return PHPProperty[]
     */
    private function readPhpDocProperties(string $docComment): array
    {
        // init
        /** @var PHPProperty[] $classPhpDocProperties */
        $classPhpDocProperties = [];

        try {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            /** @noinspection AdditionOperationOnArraysInspection */
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
                        $propertyPhp = new PHPProperty($this->usePhpReflection());

                        $nameTmp = $parsedPropertyTag->getVariableName();
                        if (!$nameTmp) {
                            continue;
                        }

                        $propertyPhp->name = $nameTmp;

                        $propertyPhp->access = 'public';

                        $type = $parsedPropertyTag->getType();
                        if ($type) {
                            $propertyPhp->typeFromPhpDoc = $type . '';
                        }

                        $typeMaybeWithCommentTmp = \trim((string) $parsedPropertyTag);
                        if (
                            $typeMaybeWithCommentTmp
                            &&
                            \strpos($typeMaybeWithCommentTmp, '$') !== 0
                        ) {
                            $propertyPhp->typeMaybeWithComment = $typeMaybeWithCommentTmp;
                        }

                        $returnTypeTmp = Utils::parseDocTypeObject($type);
                        if (\is_array($returnTypeTmp)) {
                            $propertyPhp->typeFromPhpDocSimple = \implode('|', $returnTypeTmp);
                        } else {
                            $propertyPhp->typeFromPhpDocSimple = $returnTypeTmp;
                        }

                        $propertyPhp->typeFromPhpDocPslam = (string) \Psalm\Type::parseString($propertyPhp->typeFromPhpDoc);

                        $classPhpDocProperties[$propertyPhp->name] = $propertyPhp;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
        }

        return $classPhpDocProperties;
    }
}
