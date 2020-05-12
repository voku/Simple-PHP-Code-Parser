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
    public $name;

    /**
     * @var string|null
     */
    public $parseError;

    /**
     * @param mixed $object
     *
     * @return mixed
     */
    abstract public function readObjectFromReflection($object);

    /**
     * @param mixed $mixed_1
     * @param mixed $mixed_2
     *
     * @return mixed
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
     * @param \PhpParser\Node $node
     *
     * @return string
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
            if ($node->namespacedName === null) {
                $fqn = $node->name->parts[0];
            } else {
                foreach ($node->namespacedName->parts as $part) {
                    $fqn .= "${part}\\";
                }
            }
        }

        return \rtrim($fqn, '\\');
    }

    /**
     * @param Node $nodeClone
     *
     * @return void
     */
    protected function checkForPhpDocErrors(Node $nodeClone): void
    {
        foreach ($nodeClone->getComments() as $comment) {
            if ($comment instanceof \PhpParser\Comment\Doc) {
                try {
                    $parsed_docblock = \Psalm\DocComment::parsePreservingLength($comment);

                    foreach ($parsed_docblock['specials'] as $type_key => $type_tokens) {
                        $type_token = \trim(\array_values($type_tokens)[0]);

                        $nonEmptyTypeKeys = [
                            'param',
                            'psalm-param',
                            'phpstan-param',
                            'return',
                            'psalm-return',
                            'phpstan-return',
                            'var',
                            'psalm-var',
                            'phpstan-var',
                            'property',
                            'psalm-property',
                            'phpstan-property',
                        ];

                        if (
                            (
                                !$type_token
                                ||
                                \strpos($type_token, '$') === 0
                            )
                            &&
                            \in_array($type_key, $nonEmptyTypeKeys, true)
                        ) {
                            throw new \Exception('Empty type: ' . \print_r($parsed_docblock, true));
                        }
                    }
                } catch (\Exception $e) {
                    $this->parseError .= $e . "\n";
                }
            }
        }
    }
}
