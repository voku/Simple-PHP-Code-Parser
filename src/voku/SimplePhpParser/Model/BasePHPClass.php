<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

abstract class BasePHPClass extends BasePHPElement
{
    use PHPDocElement;

    private const PHP_VERSION_8_2_0 = 80200;

    private const PHP_VERSION_8_3_0 = 80300;

    private const PHP_VERSION_8_4_0 = 80400;

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

    /**
     * Traits composed directly by this class-like declaration, keyed by their
     * fully-qualified names.
     *
     * @var array<string, string>
     *
     * @phpstan-var array<class-string, class-string>
     */
    public array $traitUses = [];

    /**
     * Trait conflict-resolution and alias rules in source order.
     *
     * An alias rule has type "alias" and may omit trait when PHP's shorthand
     * syntax is used. A precedence rule has type "precedence" and lists the
     * traits replaced by the selected method.
     *
     * @var list<array{
     *     type: 'alias'|'precedence',
     *     trait: class-string|null,
     *     method: string,
     *     alias?: string|null,
     *     visibility?: ''|'private'|'protected'|'public',
     *     insteadOf?: list<class-string>
     * }>
     */
    public array $traitAdaptations = [];

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

        if (\PHP_VERSION_ID < self::PHP_VERSION_8_4_0 && self::containsPHP84PlusSyntax($node)) {
            return false;
        }

        return true;
    }

    protected function collectTraitUsesFromPhpNode(\PhpParser\Node $node): void
    {
        foreach ($node->stmts ?? [] as $statement) {
            if (!$statement instanceof \PhpParser\Node\Stmt\TraitUse) {
                continue;
            }

            foreach ($statement->traits as $trait) {
                $traitName = $trait->toString();
                /** @var class-string $traitName */
                $traitName = $traitName;
                $this->traitUses[$traitName] = $traitName;
            }

            foreach ($statement->adaptations as $adaptation) {
                $traitName = $adaptation->trait?->toString();
                if ($traitName !== null) {
                    /** @var class-string $traitName */
                    $traitName = $traitName;
                }

                if ($adaptation instanceof \PhpParser\Node\Stmt\TraitUseAdaptation\Alias) {
                    $this->traitAdaptations[] = [
                        'type'       => 'alias',
                        'trait'      => $traitName,
                        'method'     => $adaptation->method->toString(),
                        'alias'      => $adaptation->newName?->toString(),
                        'visibility' => self::getVisibilityFromModifierFlags($adaptation->newModifier ?? 0),
                    ];

                    continue;
                }

                if (!$adaptation instanceof \PhpParser\Node\Stmt\TraitUseAdaptation\Precedence) {
                    continue;
                }

                $insteadOf = [];
                foreach ($adaptation->insteadof as $replacedTrait) {
                    $replacedTraitName = $replacedTrait->toString();
                    /** @var class-string $replacedTraitName */
                    $replacedTraitName = $replacedTraitName;
                    $insteadOf[] = $replacedTraitName;
                }

                $this->traitAdaptations[] = [
                    'type'      => 'precedence',
                    'trait'     => $traitName,
                    'method'    => $adaptation->method->toString(),
                    'insteadOf' => $insteadOf,
                ];
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    protected function collectTraitUsesFromReflection(\ReflectionClass $class): void
    {
        foreach ($class->getTraitNames() as $traitName) {
            /** @var class-string $traitName */
            $traitName = $traitName;
            $this->traitUses[$traitName] = $traitName;
        }
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

    /**
     * Detect PHP 8.4-only syntax within a class-like AST such as property hooks
     * and asymmetric visibility modifiers.
     */
    protected static function containsPHP84PlusSyntax(\PhpParser\Node $node): bool
    {
        // Property hooks (PHP 8.4+)
        if ($node instanceof \PhpParser\Node\Stmt\Property && !empty($node->hooks)) {
            return true;
        }

        // Asymmetric visibility on properties (PHP 8.4+)
        if (
            $node instanceof \PhpParser\Node\Stmt\Property
            && self::getAsymmetricSetVisibility($node) !== ''
        ) {
            return true;
        }

        // Property hooks on promoted constructor parameters (PHP 8.4+)
        if ($node instanceof \PhpParser\Node\Param && !empty($node->hooks)) {
            return true;
        }

        // Asymmetric visibility on promoted constructor parameters (PHP 8.4+)
        if (
            $node instanceof \PhpParser\Node\Param
            && self::getAsymmetricSetVisibility($node) !== ''
        ) {
            return true;
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof \PhpParser\Node && self::containsPHP84PlusSyntax($subNode)) {
                return true;
            }

            if (!\is_array($subNode)) {
                continue;
            }

            foreach ($subNode as $subNodeInner) {
                if ($subNodeInner instanceof \PhpParser\Node && self::containsPHP84PlusSyntax($subNodeInner)) {
                    return true;
                }
            }
        }

        return false;
    }
}
