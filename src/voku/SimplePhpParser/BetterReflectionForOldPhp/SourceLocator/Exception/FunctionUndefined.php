<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Exception;

use RuntimeException;

/**
 * This is removed in PR #236 - this should never have existed, but leaving here for BC. This exception is never thrown
 * so you should remove it from your code.
 *
 * You were probably looking to catch \voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound instead.
 *
 * @deprecated You're probably looking for `IdentifierNotFound`
 * @see \voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound
 */
class FunctionUndefined extends RuntimeException
{
}
