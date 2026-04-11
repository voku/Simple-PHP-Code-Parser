<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

abstract class BasePHPClass extends BasePHPElement
{
    use PHPDocElement;

    private const PHP_VERSION_8_2_0 = 80200;

    private const PHP_VERSION_8_3_0 = 80300;

    /**
     * @var array<string, PHPMethod>
     */
    public array $methods = [];

    /**
     * @var array<string, PHPProperty>
     */
    public array $properties = [];

    /**
     * @var array<string, PHPConst>
     */
    public array $constants = [];

    /**
     * PHP 8.0+ attributes on this class/interface/trait/enum.
     *
     * @var PHPAttribute[]
     */
    public array $attributes = [];

    public ?bool $is_final = null;

    public ?bool $is_abstract = null;

    public ?bool $is_readonly = null;

    public ?bool $is_anonymous = null;

    public ?bool $is_cloneable = null;

    public ?bool $is_instantiable = null;

    public ?bool $is_iterable = null;

    /**
     * Check if the parsed class-like node can be safely autoloaded on the
     * current runtime without triggering fatal syntax errors from newer PHP features.
     */
    protected static function canAutoloadFromPhpNode(\PhpParser\Node $node): bool
    {
        if (\PHP_VERSION_ID < self::PHP_VERSION_8_2_0 && self::containsPHP82PlusSyntax($node)) {
            return false;
        }

        if (\PHP_VERSION_ID < self::PHP_VERSION_8_3_0 && self::containsPHP83PlusSyntax($node)) {
            return false;
        }

        return true;
    }

    /**
     * Detect PHP 8.2-only syntax within a class-like AST such as readonly classes,
     * DNF types, and standalone null/true/false types.
     */
    private static function containsPHP82PlusSyntax(\PhpParser\Node $node): bool
    {
        if (
            $node instanceof \PhpParser\Node\Stmt\Class_
            &&
            \method_exists($node, 'isReadonly')
            &&
            $node->isReadonly()
        ) {
            return true;
        }

        if ($node instanceof \PhpParser\Node\UnionType) {
            foreach ($node->types as $innerType) {
                if ($innerType instanceof \PhpParser\Node\IntersectionType) {
                    return true;
                }
            }
        }

        if ($node instanceof \PhpParser\Node\Identifier) {
            $typeName = \strtolower($node->name);

            // Standalone null/true/false are represented as Identifier nodes too,
            // so they are only PHP 8.2+ when they are not part of a union type.
            if (
                ($typeName === 'null' || $typeName === 'true' || $typeName === 'false')
                &&
                !($node->getAttribute('parent') instanceof \PhpParser\Node\UnionType)
            ) {
                return true;
            }
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof \PhpParser\Node && self::containsPHP82PlusSyntax($subNode)) {
                return true;
            }

            if (!\is_array($subNode)) {
                continue;
            }

            foreach ($subNode as $subNodeInner) {
                if ($subNodeInner instanceof \PhpParser\Node && self::containsPHP82PlusSyntax($subNodeInner)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect PHP 8.3-only syntax within a class-like AST such as typed class constants.
     */
    private static function containsPHP83PlusSyntax(\PhpParser\Node $node): bool
    {
        if (
            $node instanceof \PhpParser\Node\Stmt\ClassConst
            &&
            \property_exists($node, 'type')
            &&
            $node->type !== null
        ) {
            return true;
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof \PhpParser\Node && self::containsPHP83PlusSyntax($subNode)) {
                return true;
            }

            if (!\is_array($subNode)) {
                continue;
            }

            foreach ($subNode as $subNodeInner) {
                if ($subNodeInner instanceof \PhpParser\Node && self::containsPHP83PlusSyntax($subNodeInner)) {
                    return true;
                }
            }
        }

        return false;
    }
}
