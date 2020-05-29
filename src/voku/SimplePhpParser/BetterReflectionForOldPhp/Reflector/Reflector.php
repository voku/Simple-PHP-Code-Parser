<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector;

use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Reflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;

/**
 * This interface is used to ensure a reflector implements these basic methods.
 */
interface Reflector
{
    /**
     * Create a reflection from the named identifier.
     *
     * @param string $identifierName
     *
     * @throws IdentifierNotFound
     *
     * @return Reflection
     */
    public function reflect(string $identifierName): Reflection;
}
