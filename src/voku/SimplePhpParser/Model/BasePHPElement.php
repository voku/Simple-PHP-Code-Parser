<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeAbstract;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;

abstract class BasePHPElement
{
    public string $name = '';

    /**
     * @var string[]
     */
    public array $parseError = [];

    public ?int $line = null;

    public ?string $file = null;

    public ?int $pos = null;

    public ParserContainer $parserContainer;

    public function __construct(ParserContainer $parserContainer)
    {
        $this->parserContainer = $parserContainer;
    }

    /**
     * @param \Reflector $object
     *
     * @return $this
     */
    abstract public function readObjectFromReflection($object);

    /**
     * @param \PhpParser\NodeAbstract      $mixed_1
     * @param \PhpParser\NodeAbstract|null $mixed_2
     *
     * @return $this
     */
    abstract public function readObjectFromPhpNode($mixed_1, $mixed_2 = null);

    protected function getConstantFQN(NodeAbstract $node, string $nodeName): string
    {
        $parent = $node->getAttribute('parent');
        if (
            $parent instanceof Namespace_
            &&
            $parent->name instanceof Name
        ) {
            $namespace = '\\' . \implode('\\', $parent->name->getParts()) . '\\';
        } else {
            $namespace = '';
        }

        return $namespace . $nodeName;
    }

    /**
     * @param \PhpParser\Node|string $node
     *
     * @return string
     *
     * @psalm-return class-string
     */
    protected static function getFQN($node): string
    {
        // init
        $fqn = '';

        if (
            $node instanceof \PhpParser\Node
            &&
            \property_exists($node, 'namespacedName')
        ) {
            if ($node->namespacedName) {
                $fqn = \implode('\\', $node->namespacedName->getParts());
            } elseif (\property_exists($node, 'name') && $node->name) {
                var_dump($node->name);
                $fqn =  $node->name->name;
            }
        }

        /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
        /** @var class-string $fqn */
        $fqn = $fqn;

        return $fqn;
    }

    protected function prepareNode(Node $node): void
    {
        $this->line = $node->getLine();
    }
}
