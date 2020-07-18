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

        $constName = (isset($node->args[0]->value->value) && $node->args[0]->value instanceof String_)
            ? $this->getConstantFQN($node, (string) $node->args[0]->value->value)
            : '';
        if (\in_array($constName, ['null', 'true', 'false'], true)) {
            $constName = \strtoupper($constName);
        }
        $this->name = $constName;

        $this->value = Utils::getPhpParserValueFromNode($node->args[1]);

        $this->type = Utils::normalizePhpType(\gettype($this->value));

        $this->collectTags($node);

        return $this;
    }

    /**
     * @param array $constant
     *
     * @return $this
     */
    public function readObjectFromBetterReflection($constant): PHPConst
    {
        $this->name = (string) $constant[0];

        $constantValue = $constant[1];
        if ($constantValue === null) {
            $this->value = null;
            return $this;
        }

        $this->type = Utils::normalizePhpType(\gettype($this->value));

        $this->value = \is_resource($constantValue) ? '__RESOURCE__' : $constantValue;

        return $this;
    }
}
