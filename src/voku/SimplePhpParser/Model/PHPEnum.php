<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use ReflectionClass;
use ReflectionEnum;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPEnum extends BasePHPClass
{
    /**
     * @phpstan-var class-string
     */
    public string $name;

    /**
     * Backing type of the enum (e.g. 'string', 'int'), or null for unit enums.
     */
    public ?string $scalarType = null;

    /**
     * @var string[]
     *
     * @phpstan-var class-string[]
     */
    public array $interfaces = [];

    /**
     * Enum cases, keyed by case name. Value is the backing value (string|int) or null for unit enum cases.
     *
     * @var array<string, string|int|null>
     */
    public array $cases = [];

    /**
     * @param Enum_ $node
     * @param null  $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        if ($node->scalarType !== null) {
            $this->scalarType = $node->scalarType->toString();
        }

        // Extract PHP 8.0+ attributes
        if (!empty($node->attrGroups)) {
            $this->attributes = Utils::extractAttributesFromAstNode($node->attrGroups);
        }

        $enumExists = false;
        try {
            if (\class_exists($this->name, true) || \enum_exists($this->name, true)) {
                $enumExists = true;
            }
        } catch (\Throwable $e) {
            // nothing
        }
        if ($enumExists) {
            $reflectionEnum = Utils::createClassReflectionInstance($this->name);
            $this->readObjectFromReflection($reflectionEnum);
        }

        $this->collectTags($node);

        // Extract enum cases from AST
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof EnumCase) {
                $caseName = $stmt->name->name;
                $caseValue = null;
                if ($stmt->expr !== null) {
                    $caseValue = Utils::getPhpParserValueFromNode($stmt->expr);
                    if ($caseValue === Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                        $caseValue = null;
                    }
                }
                $this->cases[$caseName] = $caseValue;
            }
        }

        if (!empty($node->implements)) {
            foreach ($node->implements as $interfaceObject) {
                $interfaceFQN = \implode('\\', $interfaceObject->getParts());
                /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
                /** @var class-string $interfaceFQN */
                $interfaceFQN = $interfaceFQN;
                $this->interfaces[$interfaceFQN] = $interfaceFQN;
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

        foreach ($node->getConstants() as $constNode) {
            foreach ($constNode->consts as $const) {
                $constNameTmp = $const->name->name;

                if (isset($this->constants[$constNameTmp])) {
                    $this->constants[$constNameTmp] = $this->constants[$constNameTmp]->readObjectFromPhpNode($const);
                } else {
                    $this->constants[$constNameTmp] = (new PHPConst($this->parserContainer))->readObjectFromPhpNode($const);
                }

                if (!$this->constants[$constNameTmp]->file) {
                    $this->constants[$constNameTmp]->file = $this->file;
                }
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

        // Extract PHP 8.0+ attributes
        $this->attributes = Utils::extractAttributesFromReflection($clazz);

        if ($clazz instanceof ReflectionEnum) {
            $backingType = $clazz->getBackingType();
            if ($backingType !== null) {
                if (\method_exists($backingType, 'getName')) {
                    $this->scalarType = $backingType->getName();
                } else {
                    $this->scalarType = (string) $backingType;
                }
            }

            foreach ($clazz->getCases() as $case) {
                $caseName = $case->getName();
                $caseValue = null;
                if ($clazz->isBacked() && \method_exists($case, 'getBackingValue')) {
                    $caseValue = $case->getBackingValue();
                }
                $this->cases[$caseName] = $caseValue;
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
}
