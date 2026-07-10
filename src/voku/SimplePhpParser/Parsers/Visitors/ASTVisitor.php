<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Visitors;

use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;
use voku\SimplePhpParser\Model\BasePHPClass;
use voku\SimplePhpParser\Model\BasePHPElement;
use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPDefineConstant;
use voku\SimplePhpParser\Model\PHPEnum;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Model\PHPTrait;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;

final class ASTVisitor extends NodeVisitorAbstract
{
    /**
     * @var string|null
     */
    public ?string $fileName;

    /**
     * @var \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    private ParserContainer $parserContainer;

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
     */
    public function enterNode(Node $node): Node
    {
        switch (true) {
            case $node instanceof Function_:

                $function = new PHPFunction($this->parserContainer);
                $function->file = $this->fileName;
                $function = $function->readObjectFromPhpNode($node);
                $this->propagateFileName($function);
                $this->parserContainer->addFunction($function);

                break;

            case $node instanceof Const_:

                $constant = new PHPConst($this->parserContainer);
                $constant->file = $this->fileName;
                $constant = $constant->readObjectFromPhpNode($node);
                $this->propagateFileName($constant);
                if ($constant->parentName === null) {
                    $this->parserContainer->addConstant($constant);
                } elseif (($phpCodeParentConstantName = $this->parserContainer->getClass($constant->parentName)) !== null) {
                    $phpCodeParentConstantName->constants[$constant->name] = $constant;
                } elseif (($enum = $this->parserContainer->getEnum($constant->parentName)) !== null) {
                    $enum->constants[$constant->name] = $constant;
                } else {
                    $interface = $this->parserContainer->getInterface($constant->parentName);
                    if ($interface) {
                        $interface->constants[$constant->name] = $constant;
                    } else {
                        $trait = $this->parserContainer->getTrait($constant->parentName);
                        if ($trait) {
                            $trait->constants[$constant->name] = $constant;
                        }
                    }
                }

                break;

            case $node instanceof FuncCall:

                if (
                    $node->name instanceof Node\Name
                    &&
                    $node->name->toString() === 'define'
                ) {
                    $constant = new PHPDefineConstant($this->parserContainer);
                    $constant->file = $this->fileName;
                    $constant = $constant->readObjectFromPhpNode($node);
                    $this->propagateFileName($constant);
                    $this->parserContainer->addConstant($constant);
                }

                break;

            case $node instanceof Interface_:

                $interface = new PHPInterface($this->parserContainer);
                $interface->file = $this->fileName;
                $interface = $interface->readObjectFromPhpNode($node);
                $this->propagateFileName($interface);
                $this->parserContainer->addInterface($interface);

                break;

            case $node instanceof Trait_:

                $trait = new PHPTrait($this->parserContainer);
                $trait->file = $this->fileName;
                $trait = $trait->readObjectFromPhpNode($node);
                $this->propagateFileName($trait);
                $this->parserContainer->addTrait($trait);

                break;

            case $node instanceof Class_:

                $class = new PHPClass($this->parserContainer);
                $class->file = $this->fileName;
                $class = $class->readObjectFromPhpNode($node);
                $this->propagateFileName($class);
                $this->parserContainer->addClass($class);

                break;

            case $node instanceof Enum_:

                $enum = new PHPEnum($this->parserContainer);
                $enum->file = $this->fileName;
                $enum = $enum->readObjectFromPhpNode($node);
                $this->propagateFileName($enum);
                $this->parserContainer->addEnum($enum);

                break;

            default:

                // DEBUG
                //\var_dump($node);

                break;
        }

        return $node;
    }

    private function propagateFileName(BasePHPElement $element): void
    {
        if ($element->file === null) {
            $element->file = $this->fileName;
        }

        if ($element instanceof PHPFunction) {
            foreach ($element->parameters as $parameter) {
                $this->propagateFileName($parameter);
            }
        }

        if (!$element instanceof BasePHPClass) {
            return;
        }

        foreach ($element->properties as $property) {
            $this->propagateFileName($property);
        }

        foreach ($element->constants as $constant) {
            $this->propagateFileName($constant);
        }

        foreach ($element->methods as $method) {
            $this->propagateFileName($method);
        }
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
     * @return class-string[]
     */
    public function combineImplementedInterfaces($class): array
    {
        // init
        $interfaces = [];

        foreach ($class->interfaces as $interface) {
            $interfaces[] = $interface;

            $phpCodeInterfaces = $this->parserContainer->getInterface($interface);
            if ($phpCodeInterfaces !== null) {
                foreach ($phpCodeInterfaces->parentInterfaces as $parentInterface) {
                    $interfaces[] = $parentInterface;
                }
            }
        }

        if ($class->parentClass !== null) {
            $parentClass = $this->parserContainer->getClass($class->parentClass);
            if ($parentClass !== null) {
                $interfaces = \array_merge($interfaces, $this->combineImplementedInterfaces($parentClass));
            }
        }

        /** @var class-string[] $interfaces */
        $interfaces = \array_values(\array_unique($interfaces));

        return $interfaces;
    }
}
