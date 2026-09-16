<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

/**
 * Chooses how much of the model is filled in from the running PHP runtime.
 *
 * By default the parser autoloads every class-like it parses and merges the
 * reflection view over the AST view. That is what makes inherited members,
 * resolved constant values and transitive interfaces visible, and it is the
 * behaviour every existing caller gets.
 *
 * It is also the expensive half: the analysed code is compiled into the parsing
 * process, its parents are reflected recursively, and every inherited docblock
 * is parsed again for each child. A consumer that only wants the declarations
 * written in one file - a symbol index, a navigation map - pays for a vendor
 * class hierarchy it then throws away, and sees members that the file does not
 * declare.
 *
 * `astOnly()` restricts the model to what the source text states.
 */
final class ParserOptions
{
    private function __construct(
        /**
         * Autoload parsed class-likes and merge runtime reflection into the model.
         */
        public readonly bool $reflectionEnrichment
    ) {
    }

    /** Reflection-enriched parsing: inherited and resolved data, at runtime cost. */
    public static function default(): self
    {
        return new self(true);
    }

    /** Only what the parsed source declares; nothing is autoloaded or reflected. */
    public static function astOnly(): self
    {
        return new self(false);
    }
}
