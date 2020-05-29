<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\Composer\Psr;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;

interface PsrAutoloaderMapping
{
    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    public function resolvePossibleFilePaths(Identifier $identifier): array;

    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    public function directories(): array;
}
