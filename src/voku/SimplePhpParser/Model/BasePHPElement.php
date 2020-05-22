<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeAbstract;

abstract class BasePHPElement
{
    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $parseError = '';

    /**
     * @var int|null
     */
    public $line;

    /**
     * @var int|null
     */
    public $pos;

    /**
     * @var bool|null
     */
    private $usePhpReflection;

    /**
     * @param bool|null $usePhpReflection <p>
     *                                    null = Php-Parser + PHP-Reflection<br>
     *                                    true = PHP-Reflection<br>
     *                                    false = Php-Parser<br>
     *                                    <p>
     */
    public function __construct($usePhpReflection)
    {
        $this->usePhpReflection = $usePhpReflection;
    }

    /**
     * @param \Reflector $object
     *
     * @return $this
     */
    abstract public function readObjectFromReflection($object);

    /**
     * @param NodeAbstract      $mixed_1
     * @param NodeAbstract|null $mixed_2
     *
     * @return $this
     */
    abstract public function readObjectFromPhpNode($mixed_1, $mixed_2 = null);

    protected function usePhpReflection(): ?bool
    {
        return $this->usePhpReflection;
    }

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
