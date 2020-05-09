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
     * @return void
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Function_) {
            $function = (new PHPFunction())->readObjectFromPhpNode($node);
            $this->phpCode->addFunction($function);
        } elseif ($node instanceof Const_) {
            $constant = (new PHPConst())->readObjectFromPhpNode($node);
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
        } elseif ($node instanceof FuncCall) {
            if (
                $node->name instanceof Node\Name
                &&
                $node->name->parts[0] === 'define'
            ) {
                $constant = (new PHPDefineConstant())->readObjectFromPhpNode($node);
                $this->phpCode->addConstant($constant);
            }
        } elseif ($node instanceof ClassMethod) {
            $method = (new PHPMethod())->readObjectFromPhpNode($node);
            if ($this->phpCode->getClass($method->parentName) !== null) {
                $this->phpCode->getClass($method->parentName)->methods[$method->name] = $method;
            } else {
                $interface = $this->phpCode->getInterface($method->parentName);
                if ($interface !== null) {
                    $interface->methods[$method->name] = $method;
                }
            }
        } elseif ($node instanceof Interface_) {
            $interface = (new PHPInterface())->readObjectFromPhpNode($node);
            $this->phpCode->addInterface($interface);
        } elseif ($node instanceof Class_) {
            $class = (new PHPClass())->readObjectFromPhpNode($node);
            $this->phpCode->addClass($class);
        }
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
