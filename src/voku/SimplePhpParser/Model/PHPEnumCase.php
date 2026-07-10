<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Stmt\EnumCase;
use ReflectionEnumUnitCase;
use voku\SimplePhpParser\Parsers\Helper\Utils;

final class PHPEnumCase extends BasePHPElement
{
    /**
     * @var int|string|null
     */
    public $value = null;

    /**
     * PHP 8.0+ attributes on this enum case.
     *
     * @var PHPAttribute[]
     */
    public array $attributes = [];

    /**
     * @param EnumCase $node
     * @param null      $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);
        $this->name = $node->name->toString();

        if ($node->expr !== null) {
            $value = Utils::getPhpParserValueFromNode($node->expr);
            if ($value !== Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                $this->value = $value;
            }
        }

        if (!empty($node->attrGroups)) {
            $this->attributes = Utils::extractAttributesFromAstNode($node->attrGroups);
        }

        return $this;
    }

    /**
     * @param ReflectionEnumUnitCase $case
     *
     * @return $this
     */
    public function readObjectFromReflection($case): self
    {
        $this->name = $case->getName();

        if (\method_exists($case, 'getBackingValue')) {
            $this->value = $case->getBackingValue();
        }

        $this->attributes = Utils::extractAttributesFromReflection($case);

        $file = $case->getDeclaringClass()->getFileName();
        if ($file !== false) {
            $this->file = $file;
        }

        return $this;
    }
}
