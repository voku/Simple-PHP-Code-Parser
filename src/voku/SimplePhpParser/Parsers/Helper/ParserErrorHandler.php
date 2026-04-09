<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use PhpParser\Error;
use PhpParser\ErrorHandler;

class ParserErrorHandler extends ErrorHandler\Collecting
{
    /**
     * Handle an error generated during lexing, parsing or some other operation.
     *
     * @param \PhpParser\Error $error The error that needs to be handled
     */
    public function handleError(Error $error): void
    {
        $error->setRawMessage($error->getRawMessage() . "\n" . $error->getFile());

        parent::handleError($error);
    }
}
