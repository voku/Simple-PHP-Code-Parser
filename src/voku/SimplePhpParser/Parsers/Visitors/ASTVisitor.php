<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Visitors;

use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeVisitorAbstract;
use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PhpCodeContainer;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPDefineConstant;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Model\PHPMethod;
use voku\SimplePhpParser\Parsers\Helper\Utils;

final class ASTVisitor extends NodeVisitorAbstract
{
    /**
     * @var PhpCodeContainer
     */
    private $phpCode;

    public function __construct(PhpCodeContainer $phpCode)
    {
        $this->phpCode = $phpCode;
    }

    /**
     * @param Node $node
     *
     * @return int|Node|null
     */
    public function enterNode(Node $node)
    {
        // init
        $nodeClone = clone $node;

        switch (true) {
            case $nodeClone instanceof Function_:

                $function = (new PHPFunction())->readObjectFromPhpNode($nodeClone);
                $this->phpCode->addFunction($function);

                break;

            case $nodeClone instanceof Const_:

                $constant = (new PHPConst())->readObjectFromPhpNode($nodeClone);
                if ($constant->parentName === null) {
                    $this->phpCode->addConstant($constant);
                } elseif ($this->phpCode->getClass($constant->parentName) !== null) {
                    $this->phpCode->getClass($constant->parentName)->constants[$constant->name] = $constant;
                } else {
                    $interface = $this->phpCode->getInterface($constant->parentName);
                    if ($interface) {
                        $interface->constants[$constant->name] = $constant;
                    }
                }

                break;

            case $nodeClone instanceof FuncCall:

                if (
                    $nodeClone->name instanceof Node\Name
                    &&
                    $nodeClone->name->parts[0] === 'define'
                ) {
                    $constant = (new PHPDefineConstant())->readObjectFromPhpNode($nodeClone);
                    $this->phpCode->addConstant($constant);
                }

                break;

            case $nodeClone instanceof ClassMethod:

                $method = (new PHPMethod())->readObjectFromPhpNode($nodeClone);
                if ($this->phpCode->getClass($method->parentName) !== null) {
                    $this->phpCode->getClass($method->parentName)->methods[$method->name] = $method;
                } else {
                    $interface = $this->phpCode->getInterface($method->parentName);
                    if ($interface !== null) {
                        $interface->methods[$method->name] = $method;
                    }
                }

                break;

            case $nodeClone instanceof Interface_:

                $interface = (new PHPInterface())->readObjectFromPhpNode($nodeClone);
                $this->phpCode->addInterface($interface);

                break;

            case $nodeClone instanceof Class_:

                $class = (new PHPClass())->readObjectFromPhpNode($nodeClone);
                $this->phpCode->addClass($class);

                break;

            default:

                // DEBUG
                //\var_dump($nodeClone);

                break;
        }

        return $node;
    }

    /**
     * @param PHPInterface $interface
     *
     * @return array
     */
    public function combineParentInterfaces($interface): array
    {
        // init
        $parents = [];

        if (empty($interface->parentInterfaces)) {
            return $parents;
        }

        foreach ($interface->parentInterfaces as $parentInterface) {
            $parents[] = $parentInterface;
            if ($this->phpCode->getInterface($parentInterface) !== null) {
                foreach ($this->combineParentInterfaces($this->phpCode->getInterface($parentInterface)) as $value) {
                    $parents[] = $value;
                }
            }
        }

        return $parents;
    }

    /**
     * @param PHPClass $class
     *
     * @return array
     */
    public function combineImplementedInterfaces($class): array
    {
        // init
        $interfaces = [];

        foreach ($class->interfaces as $interface) {
            $interfaces[] = $interface;
            if ($this->phpCode->getInterface($interface) !== null) {
                $interfaces[] = $this->phpCode->getInterface($interface)->parentInterfaces;
            }
        }

        if ($class->parentClass === null) {
            return $interfaces;
        }

        if ($this->phpCode->getClass($class->parentClass) !== null) {
            $inherited = $this->combineImplementedInterfaces($this->phpCode->getClass($class->parentClass));
            $interfaces[] = Utils::flattenArray($inherited, false);
        }

        return $interfaces;
    }
}
