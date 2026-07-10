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

    /**
     * Last line covered by this element, inclusive.
     *
     * This is populated from php-parser AST nodes. Reflection-backed models
     * populate it when the corresponding reflection API exposes an end line.
     */
    public ?int $endLine = null;

    /**
     * Zero-based byte offset of the first character covered by this element.
     *
     * The value is null when the active php-parser lexer does not expose file
     * offsets or when the element was created from reflection.
     */
    public ?int $startFilePos = null;

    /**
     * Zero-based byte offset of the last character covered by this element,
     * inclusive.
     *
     * The value is null when the active php-parser lexer does not expose file
     * offsets or when the element was created from reflection.
     */
    public ?int $endFilePos = null;

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
            $namespace = '\\' . $parent->name->toString() . '\\';
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
                $fqn = $node->namespacedName->toString();
            } elseif (\property_exists($node, 'name') && $node->name) {
                $fqn = $node->name instanceof Name ? $node->name->toString() : (string) $node->name;
            }
        }

        /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
        /** @var class-string $fqn */
        $fqn = $fqn;

        return $fqn;
    }

    protected function prepareNode(Node $node): void
    {
        // Keep the legacy getLine() fallback because we support multiple php-parser
        // versions and some restored compatibility code paths still rely on the alias.
        // @phpstan-ignore function.alreadyNarrowedType
        $this->line = \method_exists($node, 'getStartLine')
            ? $node->getStartLine()
            : $node->getLine();

        $this->endLine = self::nodePosition($node, 'getEndLine');
        $this->startFilePos = self::nodePosition($node, 'getStartFilePos');
        $this->endFilePos = self::nodePosition($node, 'getEndFilePos');

        // "pos" predates the explicit source-range properties and was never
        // populated. Keep it as a backwards-compatible alias for consumers
        // that already use it as a source position.
        $this->pos = $this->startFilePos;
    }

    private static function nodePosition(Node $node, string $method): ?int
    {
        if (!\method_exists($node, $method)) {
            return null;
        }

        /** @var mixed $position */
        $position = $node->{$method}();

        return \is_int($position) && $position >= 0 ? $position : null;
    }

    protected static function getPhpDocContext(Node $node): ?\phpDocumentor\Reflection\Types\Context
    {
        $context = $node->getAttribute('phpDocContext');

        return $context instanceof \phpDocumentor\Reflection\Types\Context ? $context : null;
    }

    /**
     * @phpstan-return ''|'private'|'protected'|'public'
     */
    protected static function getAsymmetricSetVisibility(object $node): string
    {
        if (\method_exists($node, 'isPublicSet') && $node->isPublicSet()) {
            return 'public';
        }

        if (\method_exists($node, 'isProtectedSet') && $node->isProtectedSet()) {
            return 'protected';
        }

        if (\method_exists($node, 'isPrivateSet') && $node->isPrivateSet()) {
            return 'private';
        }

        return '';
    }

    protected static function isPromotedParameter(\PhpParser\Node\Param $parameter): bool
    {
        return ($parameter->flags & \PhpParser\Node\Stmt\Class_::VISIBILITY_MODIFIER_MASK) !== 0;
    }

    /**
     * @phpstan-return ''|'private'|'protected'|'public'
     */
    protected static function getVisibilityFromModifierFlags(int $flags): string
    {
        if (($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE) !== 0) {
            return 'private';
        }

        if (($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED) !== 0) {
            return 'protected';
        }

        if (($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC) !== 0) {
            return 'public';
        }

        return '';
    }

    protected static function hasReadonlyModifier(int $flags): bool
    {
        return ($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_READONLY) !== 0;
    }

    protected static function hasFinalModifier(int $flags): bool
    {
        return ($flags & \PhpParser\Node\Stmt\Class_::MODIFIER_FINAL) !== 0;
    }
}
