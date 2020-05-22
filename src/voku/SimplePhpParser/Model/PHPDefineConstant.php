<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node\Expr\FuncCall;
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

        $constName = $this->getConstantFQN($node, (string) $node->args[0]->value->value);
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
    public function readObjectFromReflection($constant): PHPConst
    {
        if (\is_string($constant[0])) {
            $this->name = \utf8_encode($constant[0]);
        } else {
            $this->name = (string) $constant[0];
        }

        $constantValue = $constant[1];
        if ($constantValue !== null) {
            $this->type = Utils::normalizePhpType(\gettype($this->value));

            if (\is_resource($constantValue)) {
                $this->value = '__RESOURCE__';
            } elseif (\is_string($constantValue) || \is_float($constantValue)) {
                $this->value = \utf8_encode((string) $constantValue);
            } else {
                $this->value = $constantValue;
            }
        } else {
            $this->value = null;
        }

        return $this;
    }
}
