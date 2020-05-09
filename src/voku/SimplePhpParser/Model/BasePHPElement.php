<?php
declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

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

        if (\property_exists($node, 'namespacedName')) {
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
}
