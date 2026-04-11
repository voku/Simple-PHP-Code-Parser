<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use ReflectionClass;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPClass extends BasePHPClass
{
    /**
     * @phpstan-var class-string
     */
    public string $name;

    /**
     * @phpstan-var class-string|null
     */
    public ?string $parentClass = null;

    /**
     * @var string[]
     *
     * @phpstan-var class-string[]
     */
    public array $interfaces = [];

    /**
     * @param Class_ $node
     * @param null   $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        $this->is_final = $node->isFinal();

        $this->is_abstract = $node->isAbstract();

        // Keep the guard for cross-version php-parser compatibility when readonly
        // helpers are restored or backported differently in downstream installs.
        if (\method_exists($node, 'isReadonly')) {
            $this->is_readonly = $node->isReadonly();
        }

        $this->is_anonymous = $node->isAnonymous();

        // Extract PHP 8.0+ attributes
        if (!empty($node->attrGroups)) {
            $this->attributes = Utils::extractAttributesFromAstNode($node->attrGroups);
        }

        // PHP < 8.2 raises an uncatchable E_COMPILE_ERROR for certain PHP 8.2+ syntax
        // (standalone true/false/null types, DNF types, readonly class). Similarly,
        // PHP < 8.3 raises an error for PHP 8.3+ syntax (typed class constants).
        // PHP < 8.4 raises an error for PHP 8.4+ syntax (property hooks, asymmetric visibility).
        // Skip autoloading in those cases; AST data is still read from the node below.
        $canAutoload = (\PHP_VERSION_ID >= 80200 || !self::nodeUsesPHP82PlusSyntax($node))
            && (\PHP_VERSION_ID >= 80300 || !self::nodeUsesPHP83PlusSyntax($node))
            && (\PHP_VERSION_ID >= 80400 || !self::nodeUsesPHP84PlusSyntax($node));
        $classExists = false;
        if ($canAutoload) {
            try {
                if (\class_exists($this->name, true)) {
                    $classExists = true;
                }
            } catch (\Throwable $e) {
                // nothing
            }
        }
        if ($classExists) {
            $reflectionClass = Utils::createClassReflectionInstance($this->name);
            $this->readObjectFromReflection($reflectionClass);
        }

        $this->collectTags($node);

        if (!empty($node->extends)) {
            $classExtended = $node->extends->toString();
            /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
            /** @var class-string $classExtended */
            $classExtended = $classExtended;
            $this->parentClass = $classExtended;
        }

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

            if ($this->is_readonly) {
                $this->properties[$propertyNameTmp]->is_readonly = true;
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

        $this->addPromotedPropertiesFromConstructor($node);

        if (!empty($node->implements)) {
            foreach ($node->implements as $interfaceObject) {
                $interfaceFQN = $interfaceObject->toString();
                /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
                /** @var class-string $interfaceFQN */
                $interfaceFQN = $interfaceFQN;
                $this->interfaces[$interfaceFQN] = $interfaceFQN;
            }
        }

        return $this;
    }

    /**
     * @param ReflectionClass<object> $clazz
     *
     * @return $this
     */
    public function readObjectFromReflection($clazz): self
    {
        $this->name = $clazz->getName();

        if (!$this->line) {
            $lineTmp = $clazz->getStartLine();
            if ($lineTmp !== false) {
                $this->line = $lineTmp;
            }
        }

        $file = $clazz->getFileName();
        if ($file) {
            $this->file = $file;
        }

        $this->is_final = $clazz->isFinal();

        $this->is_abstract = $clazz->isAbstract();

        if (method_exists($clazz, 'isReadOnly')) {
            $this->is_readonly = $clazz->isReadOnly();
        }

        $this->is_anonymous = $clazz->isAnonymous();

        $this->is_cloneable = $clazz->isCloneable();

        $this->is_instantiable = $clazz->isInstantiable();

        $this->is_iterable = $clazz->isIterable();

        // Extract PHP 8.0+ attributes
        $this->attributes = Utils::extractAttributesFromReflection($clazz);

        $parent = $clazz->getParentClass();
        if ($parent) {
            $this->parentClass = $parent->getName();

            $classExists = false;
            try {
                if (
                    !$this->parserContainer->getClass($this->parentClass)
                    &&
                    \class_exists($this->parentClass, true)
                ) {
                    $classExists = true;
                }
            } catch (\Throwable $e) {
                // nothing
            }
            if ($classExists) {
                $reflectionClass = Utils::createClassReflectionInstance($this->parentClass);
                $class = (new self($this->parserContainer))->readObjectFromReflection($reflectionClass);
                $this->parserContainer->addClass($class);
            }
        }

        foreach ($clazz->getProperties() as $property) {
            $propertyPhp = (new PHPProperty($this->parserContainer))->readObjectFromReflection($property);
            $this->properties[$propertyPhp->name] = $propertyPhp;

            if ($this->is_readonly) {
                $this->properties[$propertyPhp->name]->is_readonly = true;
            }
        }

        foreach ($clazz->getInterfaceNames() as $interfaceName) {
            /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
            /** @var class-string $interfaceName */
            $interfaceName = $interfaceName;
            $this->interfaces[$interfaceName] = $interfaceName;
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
     * @psalm-return array<string, array{
     *     type: null|string,
     *     typeFromPhpDocMaybeWithComment: null|string,
     *     typeFromPhpDoc: null|string,
     *     typeFromPhpDocSimple: null|string,
     *     typeFromPhpDocExtended: null|string,
     *     typeFromDefaultValue: null|string
     * }>
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
     *              type?: null|string,
     *              typeFromPhpDoc?: null|string,
     *              typeFromPhpDocExtended?: null|string,
     *              typeFromPhpDocSimple?: null|string,
     *              typeFromPhpDocMaybeWithComment?: null|string,
     *              typeFromDefaultValue?: null|string
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
     * }>
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
            $infoTmp['line'] = $method->line ?? $this->line;
            $infoTmp['file'] = $method->file ?? $this->file;
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
     * @param Doc|string $doc
     */
    private function readPhpDocProperties($doc): void
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
            $tmpErrorMessage = ($this->name ?: '?') . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }

    /**
     * Returns true if the class node uses syntax that requires PHP 8.2+ and would
     * cause an uncatchable E_COMPILE_ERROR when autoloaded on PHP < 8.2.
     *
     * @param Class_ $node
     *
     * @return bool
     */
    private static function nodeUsesPHP82PlusSyntax(Class_ $node): bool
    {
        // readonly class is PHP 8.2+
        if ($node->isReadonly()) {
            return true;
        }

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
                if (self::containsPHP82PlusType($stmt->returnType)) {
                    return true;
                }
                foreach ($stmt->params as $param) {
                    if (self::containsPHP82PlusType($param->type)) {
                        return true;
                    }
                }
            } elseif ($stmt instanceof \PhpParser\Node\Stmt\Property) {
                if (self::containsPHP82PlusType($stmt->type)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns true if the class node uses syntax that requires PHP 8.3+ and would
     * cause an uncatchable E_COMPILE_ERROR when autoloaded on PHP < 8.3.
     *
     * Covers: typed class constants (Stmt\ClassConst with a non-null type).
     *
     * @param Class_ $node
     *
     * @return bool
     */
    private static function nodeUsesPHP83PlusSyntax(Class_ $node): bool
    {
        foreach ($node->stmts as $stmt) {
            // Typed class constants are PHP 8.3+
            if ($stmt instanceof \PhpParser\Node\Stmt\ClassConst && $stmt->type !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the given type node is a PHP 8.2+ type that causes an
     * uncatchable E_COMPILE_ERROR when loaded on PHP < 8.2.
     *
     * Covers: standalone true/false/null types and DNF types (union of intersections).
     *
     * @param \PhpParser\Node|null $typeNode
     *
     * @return bool
     */
    private static function containsPHP82PlusType($typeNode): bool
    {
        if ($typeNode === null) {
            return false;
        }

        // Standalone true, false, null as the *sole* type (not in a nullable like ?string)
        // are PHP 8.2+ only. PHP-Parser represents these as Identifier nodes (not Name).
        // Nullable null (?null) is syntactically invalid; NullableType wraps the inner type.
        if ($typeNode instanceof \PhpParser\Node\Identifier) {
            $name = \strtolower($typeNode->name);
            return $name === 'true' || $name === 'false' || $name === 'null';
        }

        // DNF types: union type containing an intersection type (PHP 8.2+)
        if ($typeNode instanceof \PhpParser\Node\UnionType) {
            foreach ($typeNode->types as $t) {
                if ($t instanceof \PhpParser\Node\IntersectionType || self::containsPHP82PlusType($t)) {
                    return true;
                }
            }
        }

        // Recurse into nullable type
        if ($typeNode instanceof \PhpParser\Node\NullableType) {
            return self::containsPHP82PlusType($typeNode->type);
        }

        return false;
    }

    /**
     * Returns true if the class node uses syntax that requires PHP 8.4+ and would
     * cause an uncatchable E_COMPILE_ERROR when autoloaded on PHP < 8.4.
     *
     * Covers: property hooks and asymmetric visibility modifiers.
     *
     * @param Class_ $node
     *
     * @return bool
     */
    private static function nodeUsesPHP84PlusSyntax(Class_ $node): bool
    {
        foreach ($node->stmts as $stmt) {
            // Property hooks are PHP 8.4+
            if ($stmt instanceof \PhpParser\Node\Stmt\Property && !empty($stmt->hooks)) {
                return true;
            }

            // Asymmetric visibility on properties is PHP 8.4+
            if (
                $stmt instanceof \PhpParser\Node\Stmt\Property
                && self::getAsymmetricSetVisibility($stmt) !== ''
            ) {
                return true;
            }

            // Constructor with promoted properties that have hooks or asymmetric visibility
            if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
                foreach ($stmt->params as $param) {
                    if (!empty($param->hooks)) {
                        return true;
                    }
                    if (self::getAsymmetricSetVisibility($param) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function addPromotedPropertiesFromConstructor(Class_ $node): void
    {
        foreach ($node->getMethods() as $method) {
            if ($method->name->name !== '__construct') {
                continue;
            }

            foreach ($method->params as $param) {
                if (!self::isPromotedParameter($param)) {
                    continue;
                }

                $parameterVar = $param->var;
                if (
                    !($parameterVar instanceof \PhpParser\Node\Expr\Variable)
                    || !\is_string($parameterVar->name)
                ) {
                    continue;
                }

                $promotedProperty = (new PHPProperty($this->parserContainer))
                    ->readObjectFromPromotedParam($param, $this->name);

                if (isset($this->properties[$parameterVar->name])) {
                    $this->mergePromotedPropertyData($this->properties[$parameterVar->name], $promotedProperty, $param);

                    continue;
                }

                $this->properties[$parameterVar->name] = $promotedProperty;
            }

            break;
        }
    }

    private function mergePromotedPropertyData(
        PHPProperty $existingProperty,
        PHPProperty $promotedProperty,
        \PhpParser\Node\Param $parameter
    ): void {
        if ($existingProperty->access === '' && $promotedProperty->access !== '') {
            $existingProperty->access = $promotedProperty->access;
        }

        if ($existingProperty->type === null && $promotedProperty->type !== null) {
            $existingProperty->type = $promotedProperty->type;
        }

        if ($existingProperty->is_readonly === null && $promotedProperty->is_readonly !== null) {
            $existingProperty->is_readonly = $promotedProperty->is_readonly;
        }

        if ($existingProperty->access_set === '' && $promotedProperty->access_set !== '') {
            $existingProperty->access_set = $promotedProperty->access_set;
        }

        if ($existingProperty->attributes === [] && $promotedProperty->attributes !== []) {
            $existingProperty->attributes = $promotedProperty->attributes;
        }

        if ($parameter->default !== null) {
            $existingProperty->defaultValue = $promotedProperty->defaultValue;
            $existingProperty->typeFromDefaultValue = $promotedProperty->typeFromDefaultValue;
        }
    }
}
