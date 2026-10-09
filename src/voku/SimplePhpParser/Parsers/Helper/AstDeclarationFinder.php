<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Finds declarations in the AST returned by `PhpCodeParser::getAstFromString()`.
 *
 * Consumers that must map evidence ("this method, on these lines") to exactly one node get all candidates and
 * decide themselves what zero or several matches mean.
 */
final class AstDeclarationFinder
{
    /** The fully qualified name of a named class-like, without leading backslash; null for anonymous classes. */
    public static function classLikeFqn(ClassLike $class): ?string
    {
        $resolved = $class->getAttribute('namespacedName');
        if (!$resolved instanceof Name) {
            $resolved = $class->namespacedName ?? null;
        }
        if ($resolved instanceof Name) {
            return ltrim($resolved->toString(), '\\');
        }

        return $class->name !== null ? $class->name->toString() : null;
    }

    /**
     * @param array<Node> $ast
     *
     * @return list<ClassLike> class-likes whose fully qualified name equals $fqn (case-insensitive)
     */
    public static function classLikes(array $ast, string $fqn): array
    {
        $wanted = ltrim($fqn, '\\');

        /** @var list<ClassLike> */
        return (new NodeFinder())->find(
            $ast,
            static function (Node $node) use ($wanted): bool {
                if (!$node instanceof ClassLike) {
                    return false;
                }
                $actual = self::classLikeFqn($node);

                return $actual !== null && \strcasecmp($actual, $wanted) === 0;
            }
        );
    }

    /**
     * @param array<Node> $ast
     *
     * @return list<ClassMethod> methods named $name (case-insensitive), optionally exactly spanning the given lines
     */
    public static function methods(array $ast, string $name, ?int $startLine = null, ?int $endLine = null): array
    {
        /** @var list<ClassMethod> */
        return (new NodeFinder())->find(
            $ast,
            static fn (Node $node): bool => $node instanceof ClassMethod
                && \strcasecmp($node->name->toString(), $name) === 0
                && ($startLine === null || $node->getStartLine() === $startLine)
                && ($endLine === null || $node->getEndLine() === $endLine)
        );
    }
}
