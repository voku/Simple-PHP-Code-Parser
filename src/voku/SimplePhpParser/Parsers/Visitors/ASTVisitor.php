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
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPDefineConstant;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Model\PHPMethod;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\Utils;

final class ASTVisitor extends NodeVisitorAbstract
{
    /**
     * @var ParserContainer
     */
    private $phpCode;

    /**
     * @var bool|null
     */
    private $usePhpReflection;

    /**
     * @param ParserContainer $phpCode
     * @param bool|null       $usePhpReflection <p>
     *                                          null = Php-Parser + PHP-Reflection<br>
     *                                          true = PHP-Reflection<br>
     *                                          false = Php-Parser<br>
     *                                          <p>
     */
    public function __construct(ParserContainer $phpCode, bool $usePhpReflection = null)
    {
        $this->phpCode = $phpCode;
        $this->usePhpReflection = $usePhpReflection;
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

                $function = (new PHPFunction($this->usePhpReflection))->readObjectFromPhpNode($nodeClone);
                $this->phpCode->addFunction($function);

                break;

            case $nodeClone instanceof Const_:

                $constant = (new PHPConst($this->usePhpReflection))->readObjectFromPhpNode($nodeClone);
                if ($constant->parentName === null) {
                    $this->phpCode->addConstant($constant);
                } elseif (($phpCodeParentConstantName = $this->phpCode->getClass($constant->parentName)) !== null) {
                    $phpCodeParentConstantName->constants[$constant->name] = $constant;
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
                    $constant = (new PHPDefineConstant($this->usePhpReflection))->readObjectFromPhpNode($nodeClone);
                    $this->phpCode->addConstant($constant);
                }

                break;

            case $nodeClone instanceof ClassMethod:

                $method = (new PHPMethod($this->usePhpReflection))->readObjectFromPhpNode($nodeClone);
                if ($method->parentName) {
                    $phpCodeParentMethodName = $this->phpCode->getClass($method->parentName);
                } else {
                    $phpCodeParentMethodName = null;
                }

                if ($phpCodeParentMethodName !== null) {
                    $phpCodeParentMethodName->methods[$method->name] = $method;
                } else {
                    if ($method->parentName) {
                        $interface = $this->phpCode->getInterface($method->parentName);
                    } else {
                        $interface = null;
                    }

                    if ($interface !== null) {
                        $interface->methods[$method->name] = $method;
                    }
                }

                break;

            case $nodeClone instanceof Interface_:

                $interface = (new PHPInterface($this->usePhpReflection))->readObjectFromPhpNode($nodeClone);
                $this->phpCode->addInterface($interface);

                break;

            case $nodeClone instanceof Class_:

                $class = (new PHPClass($this->usePhpReflection))->readObjectFromPhpNode($nodeClone);
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
     * @return string[]
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

            $phpCodeParentInterfaces = $this->phpCode->getInterface($parentInterface);
            if ($phpCodeParentInterfaces !== null) {
                foreach ($this->combineParentInterfaces($phpCodeParentInterfaces) as $value) {
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

            $phpCodeInterfaces = $this->phpCode->getInterface($interface);
            if ($phpCodeInterfaces !== null) {
                $interfaces[] = $phpCodeInterfaces->parentInterfaces;
            }
        }

        if ($class->parentClass === null) {
            return $interfaces;
        }

        $parentClass = $this->phpCode->getClass($class->parentClass);
        if ($parentClass !== null) {
            $inherited = $this->combineImplementedInterfaces($parentClass);
            $interfaces = Utils::flattenArray($inherited, false);
        }

        return $interfaces;
    }
}
