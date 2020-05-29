<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection;

use PhpParser\Node\Stmt\ClassConst;
use ReflectionProperty;
use voku\SimplePhpParser\BetterReflectionForOldPhp\NodeCompiler\CompileNodeToValue;
use voku\SimplePhpParser\BetterReflectionForOldPhp\NodeCompiler\CompilerContext;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast\ReflectionClassConstantStringCast;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\CalculateReflectionColum;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\GetFirstDocComment;

class ReflectionClassConstant
{
    /**
     * @var bool
     */
    private $valueWasCached = false;

    /**
     * @var mixed
     *
     * @psalm-var scalar|array<scalar>|null const value
     */
    private $value;

    /**
     * @var Reflector
     */
    private $reflector;

    /**
     * @var ReflectionClass Constant owner
     */
    private $owner;

    /**
     * @var ClassConst
     */
    private $node;

    /**
     * @var int
     */
    private $positionInNode;

    private function __construct()
    {
    }

    public function __toString(): string
    {
        return ReflectionClassConstantStringCast::toString($this);
    }

    /**
     * Create a reflection of a class's constant by Const Node
     *
     * @internal
     *
     * @param Reflector       $reflector
     * @param ClassConst      $node           Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     * @param int             $positionInNode
     * @param ReflectionClass $owner
     *
     * @return static
     */
    public static function createFromNode(
        Reflector $reflector,
        ClassConst $node,
        int $positionInNode,
        ReflectionClass $owner
    ): self {
        $ref = new self();
        $ref->node = $node;
        $ref->positionInNode = $positionInNode;
        $ref->owner = $owner;
        $ref->reflector = $reflector;

        return $ref;
    }

    /**
     * Get the name of the reflection (e.g. if this is a ReflectionClass this
     * will be the class name).
     */
    public function getName(): string
    {
        return $this->node->consts[$this->positionInNode]->name->name;
    }

    /**
     * Returns constant value
     *
     * @return mixed
     *
     * @psalm-return scalar|array<scalar>|null
     */
    public function getValue()
    {
        if ($this->valueWasCached !== false) {
            return $this->value;
        }

        $this->value = (new CompileNodeToValue())->__invoke(
            $this->node->consts[$this->positionInNode]->value,
            new CompilerContext($this->reflector, $this->getDeclaringClass())
        );
        $this->valueWasCached = true;

        return $this->value;
    }

    /**
     * Constant is public
     */
    public function isPublic(): bool
    {
        return $this->node->isPublic();
    }

    /**
     * Constant is private
     */
    public function isPrivate(): bool
    {
        return $this->node->isPrivate();
    }

    /**
     * Constant is protected
     */
    public function isProtected(): bool
    {
        return $this->node->isProtected();
    }

    /**
     * Returns a bitfield of the access modifiers for this constant
     */
    public function getModifiers(): int
    {
        $val = 0;
        $val += $this->isPublic() ? ReflectionProperty::IS_PUBLIC : 0;
        $val += $this->isProtected() ? ReflectionProperty::IS_PROTECTED : 0;
        $val += $this->isPrivate() ? ReflectionProperty::IS_PRIVATE : 0;

        return $val;
    }

    /**
     * Get the line number that this constant starts on.
     */
    public function getStartLine(): int
    {
        return $this->node->getStartLine();
    }

    /**
     * Get the line number that this constant ends on.
     */
    public function getEndLine(): int
    {
        return $this->node->getEndLine();
    }

    public function getStartColumn(): int
    {
        return CalculateReflectionColum::getStartColumn($this->owner->getLocatedSource()->getSource(), $this->node);
    }

    public function getEndColumn(): int
    {
        return CalculateReflectionColum::getEndColumn($this->owner->getLocatedSource()->getSource(), $this->node);
    }

    /**
     * Get the declaring class
     */
    public function getDeclaringClass(): ReflectionClass
    {
        return $this->owner;
    }

    /**
     * Returns the doc comment for this constant
     */
    public function getDocComment(): string
    {
        return GetFirstDocComment::forNode($this->node);
    }

    public function getAst(): ClassConst
    {
        return $this->node;
    }

    public function getPositionInAst(): int
    {
        return $this->positionInNode;
    }
}
