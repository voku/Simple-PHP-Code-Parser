<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use voku\SimplePhpParser\Parsers\PhpCodeParser;

/**
 * The namespace and class imports a file resolves written class names with.
 *
 * `Formatter` means whatever the file's namespace and `use` statements make it, so the same spelling can
 * resolve to different classes in two files. Function and constant imports are not part of this context.
 */
final class ImportContext
{
    /** @param array<string, string> $classImports lower-cased alias => imported class name without leading backslash */
    public function __construct(
        public readonly string $namespace,
        public readonly array $classImports,
    ) {
    }

    public static function fromSource(string $code): self
    {
        return self::fromAst(PhpCodeParser::getAstFromString($code));
    }

    /** @param array<Node> $ast statements as returned by `PhpCodeParser::getAstFromString()` */
    public static function fromAst(array $ast): self
    {
        $namespace = '';
        $imports = [];
        foreach ($ast as $statement) {
            if ($statement instanceof Namespace_) {
                $namespace = $statement->name !== null ? $statement->name->toString() : '';
                self::collect($statement->stmts, $imports);
            }
        }
        self::collect($ast, $imports);

        return new self($namespace, $imports);
    }

    /** Resolves a class name the way PHP does at that point of the file: `\Foo` is absolute, then imports, then the namespace. */
    public function resolveClassName(string $written): string
    {
        if (str_starts_with($written, '\\')) {
            return ltrim($written, '\\');
        }

        $parts = explode('\\', $written, 2);
        $import = $this->classImports[strtolower($parts[0])] ?? null;
        if ($import !== null) {
            return $import . (isset($parts[1]) ? '\\' . $parts[1] : '');
        }

        return ($this->namespace === '' ? '' : $this->namespace . '\\') . $written;
    }

    /**
     * @param array<Node>          $statements
     * @param array<string, string> $imports
     */
    private static function collect(array $statements, array &$imports): void
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Use_ && $statement->type === Use_::TYPE_NORMAL) {
                foreach ($statement->uses as $use) {
                    $imports[strtolower($use->getAlias()->toString())] = $use->name->toString();
                }
            } elseif ($statement instanceof GroupUse) {
                foreach ($statement->uses as $use) {
                    $type = $use->type !== Use_::TYPE_UNKNOWN ? $use->type : $statement->type;
                    if ($type === Use_::TYPE_NORMAL) {
                        $imports[strtolower($use->getAlias()->toString())] = $statement->prefix->toString() . '\\' . $use->name->toString();
                    }
                }
            }
        }
    }
}
