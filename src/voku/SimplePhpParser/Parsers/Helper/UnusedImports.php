<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

/**
 * Finds class imports (`use A\B;`, `use A\B as C;`) that nothing in the file refers to any more.
 *
 * Deliberately conservative: a name that appears in a comment or docblock counts as used, grouped, `function`
 * and `const` imports are not judged, and only imports in the file header (before the first type declaration)
 * are considered.
 */
final class UnusedImports
{
    /** @return list<array{import: string, alias: string, line: int}> */
    public static function find(string $code): array
    {
        $tokens = token_get_all($code);
        $imports = self::imports($tokens);
        if ($imports === []) {
            return [];
        }

        $used = self::usedNames($tokens);

        return array_values(array_filter(
            $imports,
            static fn (array $import): bool => !isset($used[strtolower($import['alias'])]),
        ));
    }

    /**
     * @param list<mixed> $tokens
     * @return list<array{import: string, alias: string, line: int}>
     */
    private static function imports(array $tokens): array
    {
        $imports = [];
        $count = \count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (self::isTypeDeclaration($token)) {
                break;
            }
            if (!\is_array($token) || $token[0] !== \T_USE) {
                continue;
            }

            $line = $token[2];
            $name = null;
            $alias = null;
            $skip = false;
            for (++$i; $i < $count && $tokens[$i] !== ';'; ++$i) {
                $part = $tokens[$i];
                if ($part === '{' || $part === ',' || (\is_array($part) && \in_array($part[0], [\T_FUNCTION, \T_CONST], true))) {
                    $skip = true;
                } elseif (\is_array($part) && \in_array($part[0], [\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_STRING], true)) {
                    if ($name === null) {
                        $name = ltrim($part[1], '\\');
                    } else {
                        $alias = $part[1];
                    }
                }
            }
            if (!$skip && $name !== null) {
                $segments = explode('\\', $name);
                $imports[] = ['import' => $name, 'alias' => $alias ?? end($segments), 'line' => $line];
            }
        }

        return $imports;
    }

    /**
     * @param list<mixed> $tokens
     * @return array<string, true> lower-cased names referenced outside header imports
     */
    private static function usedNames(array $tokens): array
    {
        $used = [];
        $inHeader = true;
        $inImport = false;
        $previous = null;
        foreach ($tokens as $token) {
            if (\is_array($token) && $token[0] === \T_WHITESPACE) {
                continue;
            }
            if (self::isTypeDeclaration($token)) {
                $inHeader = false;
            }
            if ($inHeader && \is_array($token) && $token[0] === \T_USE) {
                $inImport = true;
            }
            if ($token === ';') {
                $inImport = false;
            }
            if (!$inImport && \is_array($token)) {
                if (\in_array($token[0], [\T_DOC_COMMENT, \T_COMMENT], true)) {
                    foreach (preg_split('/\W+/', $token[1]) ?: [] as $word) {
                        if ($word !== '') {
                            $used[strtolower($word)] = true;
                        }
                    }
                } elseif ($token[0] === \T_STRING && !self::isMemberAccess($previous)) {
                    $used[strtolower($token[1])] = true;
                } elseif ($token[0] === \T_NAME_QUALIFIED) {
                    $used[strtolower(explode('\\', $token[1])[0])] = true;
                }
            }
            $previous = $token;
        }

        return $used;
    }

    /** @param mixed $token */
    private static function isTypeDeclaration($token): bool
    {
        // T_ENUM only exists on PHP 8.1+, which this package requires.
        return \is_array($token) && \in_array($token[0], [\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM], true);
    }

    /** @param mixed $previous */
    private static function isMemberAccess($previous): bool
    {
        return \is_array($previous) && \in_array($previous[0], [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON], true);
    }
}
