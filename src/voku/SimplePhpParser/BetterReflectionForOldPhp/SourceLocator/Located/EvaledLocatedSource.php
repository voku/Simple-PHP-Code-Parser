<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located;

class EvaledLocatedSource extends LocatedSource
{
    /**
     * {@inheritdoc}
     */
    public function __construct(string $source)
    {
        parent::__construct($source, null);
    }

    public function isEvaled(): bool
    {
        return true;
    }
}
