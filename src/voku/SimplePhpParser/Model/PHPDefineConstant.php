<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPDefineConstant extends PHPConst
{
    /**
     * @param FuncCall $node
     * @param null     $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): PHPConst
    {
        $this->prepareNode($node);

        $constName = (isset($node->args[0]) && $node->args[0]->value->value && $node->args[0]->value instanceof String_)
            ? $this->getConstantFQN($node, (string) $node->args[0]->value->value)
            : '';
        if (\in_array($constName, ['null', 'true', 'false'], true)) {
            $constName = \strtoupper($constName);
        }
        $this->name = $constName;

        /* @phpstan-ignore-next-line */
        $this->value = Utils::getPhpParserValueFromNode($node->args[1]);

        $this->type = Utils::normalizePhpType(\gettype($this->value));

        $this->collectTags($node);

        return $this;
    }

    /**
     * @param \ReflectionClassConstant $constant
     *
     * @return $this
     */
    public function readObjectFromReflection($constant): PHPConst
    {
        $this->name = $constant->getName();
        $this->value = $constant->getValue();
        $this->type = Utils::normalizePhpType(\gettype($this->value));

        return $this;
    }
}
