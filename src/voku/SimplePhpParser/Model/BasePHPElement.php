<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeAbstract;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;

abstract class BasePHPElement
{
    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string[]
     */
    public $parseError = [];

    /**
     * @var int|null
     */
    public $line;

    /**
     * @var int|null
     */
    public $pos;

    /**
     * @var ParserContainer
     */
    public $parserContainer;

    /**
     * @param ParserContainer $parserContainer
     */
    public function __construct($parserContainer)
    {
        $this->parserContainer = $parserContainer;
    }

    /**
     * @param \Reflector $object
     *
     * @return $this
     */
    abstract public function readObjectFromBetterReflection($object);

    /**
     * @param NodeAbstract      $mixed_1
     * @param NodeAbstract|null $mixed_2
     *
     * @return $this
     */
    abstract public function readObjectFromPhpNode($mixed_1, $mixed_2 = null);

    protected function getConstantFQN(NodeAbstract $node, string $nodeName): string
    {
        // init
        $namespace = '';

        if ($node->getAttribute('parent') instanceof Namespace_ && !empty($node->getAttribute('parent')->name)) {
            $namespace = '\\' . \implode('\\', $node->getAttribute('parent')->name->parts) . '\\';
        }

        return $namespace . $nodeName;
    }

    /**
     * @param \PhpParser\Node|string $node
     *
     * @return string
     *
     * @psalm-return class-string
     *
     * @psalm-suppress MoreSpecificReturnType or Less ?
     */
    protected function getFQN($node): string
    {
        // init
        $fqn = '';

        if (
            $node instanceof \PhpParser\Node
            &&
            \property_exists($node, 'namespacedName')
        ) {
            /** @psalm-suppress NoInterfaceProperties ? */
            if ($node->namespacedName === null) {
                $fqn = $node->name->parts[0];
            } else {
                foreach ($node->namespacedName->parts as $part) {
                    $fqn .= "${part}\\";
                }
            }
        }

        /** @psalm-suppress LessSpecificReturnStatement ? */
        return \rtrim($fqn, '\\');
    }

    /**
     * @param Node $node
     *
     * @return void
     */
    protected function prepareNode(Node $node): void
    {
        $this->line = $node->getLine();
    }
}
