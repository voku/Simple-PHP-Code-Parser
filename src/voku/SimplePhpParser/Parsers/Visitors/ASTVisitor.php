<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Visitors;

use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeVisitorAbstract;
use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPDefineConstant;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\Utils;

final class ASTVisitor extends NodeVisitorAbstract
{
    /**
     * @var string|null
     */
    public $fileName;

    /**
     * @var \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    private $parserContainer;

    /**
     * @param \voku\SimplePhpParser\Parsers\Helper\ParserContainer $parserContainer
     */
    public function __construct(ParserContainer $parserContainer)
    {
        $this->parserContainer = $parserContainer;
    }

    /**
     * @param \PhpParser\Node $node
     *
     * @return int|\PhpParser\Node|null
     */
    public function enterNode(Node $node)
    {
        switch (true) {
            case $node instanceof Function_:

                $function = (new PHPFunction($this->parserContainer))->readObjectFromPhpNode($node);
                if (!$function->file) {
                    $function->file = $this->fileName;
                }
                $this->parserContainer->addFunction($function);

                break;

            case $node instanceof Const_:

                $constant = (new PHPConst($this->parserContainer))->readObjectFromPhpNode($node);
                if (!$constant->file) {
                    $constant->file = $this->fileName;
                }
                if ($constant->parentName === null) {
                    $this->parserContainer->addConstant($constant);
                } elseif (($phpCodeParentConstantName = $this->parserContainer->getClass($constant->parentName)) !== null) {
                    $phpCodeParentConstantName->constants[$constant->name] = $constant;
                } else {
                    $interface = $this->parserContainer->getInterface($constant->parentName);
                    if ($interface) {
                        $interface->constants[$constant->name] = $constant;
                    }
                }

                break;

            case $node instanceof FuncCall:

                if (
                    $node->name instanceof Node\Name
                    &&
                    $node->name->parts[0] === 'define'
                ) {
                    $constant = (new PHPDefineConstant($this->parserContainer))->readObjectFromPhpNode($node);
                    if (!$constant->file) {
                        $constant->file = $this->fileName;
                    }
                    $this->parserContainer->addConstant($constant);
                }

                break;

            case $node instanceof Interface_:

                $interface = (new PHPInterface($this->parserContainer))->readObjectFromPhpNode($node);
                if (!$interface->file) {
                    $interface->file = $this->fileName;
                }
                $this->parserContainer->addInterface($interface);

                break;

            case $node instanceof Class_:

                $class = (new PHPClass($this->parserContainer))->readObjectFromPhpNode($node);
                if (!$class->file) {
                    $class->file = $this->fileName;
                }
                $this->parserContainer->addClass($class);

                break;

            default:

                // DEBUG
                //\var_dump($node);

                break;
        }

        return $node;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPInterface $interface
     *
     * @return string[]
     *
     * @psalm-return class-string[]
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

            $phpCodeParentInterfaces = $this->parserContainer->getInterface($parentInterface);
            if ($phpCodeParentInterfaces !== null) {
                foreach ($this->combineParentInterfaces($phpCodeParentInterfaces) as $value) {
                    $parents[] = $value;
                }
            }
        }

        return $parents;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPClass $class
     *
     * @return array
     */
    public function combineImplementedInterfaces($class): array
    {
        // init
        $interfaces = [];

        foreach ($class->interfaces as $interface) {
            $interfaces[] = $interface;

            $phpCodeInterfaces = $this->parserContainer->getInterface($interface);
            if ($phpCodeInterfaces !== null) {
                $interfaces[] = $phpCodeInterfaces->parentInterfaces;
            }
        }

        if ($class->parentClass === null) {
            return $interfaces;
        }

        $parentClass = $this->parserContainer->getClass($class->parentClass);
        if ($parentClass !== null) {
            $inherited = $this->combineImplementedInterfaces($parentClass);
            $interfaces = Utils::flattenArray($inherited, false);
        }

        return $interfaces;
    }
}
