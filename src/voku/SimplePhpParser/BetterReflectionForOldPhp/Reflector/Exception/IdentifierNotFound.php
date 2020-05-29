<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception;

use RuntimeException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Identifier\Identifier;

class IdentifierNotFound extends RuntimeException
{
    /**
     * @var Identifier
     */
    private $identifier;

    public function __construct(string $message, Identifier $identifier)
    {
        parent::__construct($message);

        $this->identifier = $identifier;
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public static function fromIdentifier(Identifier $identifier): self
    {
        return new self(\sprintf(
            '%s "%s" could not be found in the located source',
            $identifier->getType()->getName(),
            $identifier->getName()
        ), $identifier);
    }
}
