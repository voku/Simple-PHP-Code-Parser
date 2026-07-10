<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use voku\SimplePhpParser\Parsers\Helper\Utils;

/**
 * Compact file-scope metadata for consumers that need to resolve a symbol or
 * PHPDoc without reading the source header again.
 */
final class PHPFileInfo
{
    /**
     * @param list<array{
     *     name: string,
     *     line: int|null,
     *     endLine: int|null,
     *     imports: list<array{name: string, alias: string, kind: 'class'|'const'|'function', line: int|null, endLine: int|null}>,
     *     declares: array<string, bool|float|int|string|null>
     * }> $namespaces
     */
    public function __construct(
        public ?string $file,
        public array $namespaces,
    ) {
    }

    /**
     * @param array<int, Node> $ast
     */
    public static function fromAst(array $ast, ?string $file = null): self
    {
        $namespaces = [];
        $globalStatements = [];

        foreach ($ast as $statement) {
            if ($statement instanceof Namespace_) {
                $namespaces[] = self::scope($statement->name?->toString() ?? '', $statement->stmts, $statement);

                continue;
            }

            $globalStatements[] = $statement;
        }

        if ($globalStatements !== []) {
            \array_unshift($namespaces, self::scope('', $globalStatements));
        }

        return new self($file, $namespaces);
    }

    /**
     * @param array<int, Node> $statements
     *
     * @return array{
     *     name: string,
     *     line: int|null,
     *     endLine: int|null,
     *     imports: list<array{name: string, alias: string, kind: 'class'|'const'|'function', line: int|null, endLine: int|null}>,
     *     declares: array<string, bool|float|int|string|null>
     * }
     */
    private static function scope(string $name, array $statements, ?Namespace_ $namespace = null): array
    {
        $imports = [];
        $declares = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Use_) {
                $imports = \array_merge($imports, self::imports($statement->uses, $statement->type));

                continue;
            }

            if ($statement instanceof GroupUse) {
                $imports = \array_merge($imports, self::imports($statement->uses, $statement->type, $statement->prefix->toString()));

                continue;
            }

            if (!$statement instanceof Declare_) {
                continue;
            }

            foreach ($statement->declares as $declare) {
                $value = Utils::getPhpParserValueFromNode($declare->value);
                if ($value === Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER || !\is_scalar($value) && $value !== null) {
                    continue;
                }

                $declares[$declare->key->toString()] = $value;
            }
        }

        return [
            'name'     => $name,
            'line'     => $namespace === null ? null : self::line($namespace, 'getStartLine'),
            'endLine'  => $namespace === null ? null : self::line($namespace, 'getEndLine'),
            'imports'  => $imports,
            'declares' => $declares,
        ];
    }

    /**
     * @param array<int, \PhpParser\Node\UseItem> $uses
     *
     * @return list<array{name: string, alias: string, kind: 'class'|'const'|'function', line: int|null, endLine: int|null}>
     */
    private static function imports(array $uses, int $parentType, string $prefix = ''): array
    {
        $imports = [];

        foreach ($uses as $use) {
            $type = $use->type === Use_::TYPE_UNKNOWN ? $parentType : $use->type;
            $name = $use->name->toString();
            if ($prefix !== '') {
                $name = $prefix . '\\' . $name;
            }

            $imports[] = [
                'name'    => $name,
                'alias'   => $use->getAlias()->toString(),
                'kind'    => self::importKind($type),
                'line'    => self::line($use, 'getStartLine'),
                'endLine' => self::line($use, 'getEndLine'),
            ];
        }

        return $imports;
    }

    /**
     * @return 'class'|'const'|'function'
     */
    private static function importKind(int $type): string
    {
        if ($type === Use_::TYPE_FUNCTION) {
            return 'function';
        }

        if ($type === Use_::TYPE_CONSTANT) {
            return 'const';
        }

        return 'class';
    }

    private static function line(Node $node, string $method): ?int
    {
        $line = $node->{$method}();

        return $line >= 0 ? $line : null;
    }
}
