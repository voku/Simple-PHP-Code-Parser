<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Ast\Exception;

use RuntimeException;
use Throwable;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;

class ParseToAstFailure extends RuntimeException
{
    /**
     * @param LocatedSource $locatedSource
     * @param Throwable     $previous
     *
     * @return static
     */
    public static function fromLocatedSource(LocatedSource $locatedSource, Throwable $previous): self
    {
        $additionalInformation = '';
        if ($locatedSource->getFileName() !== null) {
            $additionalInformation = \sprintf(' (in %s)', $locatedSource->getFileName());
        }

        if ($additionalInformation === '') {
            $additionalInformation = \sprintf(' (first 20 characters: %s)', \substr($locatedSource->getSource(), 0, 20));
        }

        return new self(\sprintf(
            'AST failed to parse in located source%s',
            $additionalInformation
        ), 0, $previous);
    }
}
