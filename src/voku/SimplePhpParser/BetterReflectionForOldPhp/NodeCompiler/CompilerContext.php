<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\NodeCompiler;

use RuntimeException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;

class CompilerContext
{
    /**
     * @var Reflector
     */
    private $reflector;

    /**
     * @var ReflectionClass|null
     */
    private $self;

    /**
     * @param Reflector            $reflector
     * @param ReflectionClass|null $self
     */
    public function __construct(Reflector $reflector, ?ReflectionClass $self)
    {
        $this->reflector = $reflector;
        $this->self = $self;
    }

    /**
     * Does the current context have a "self" or "this"
     *
     * (e.g. if the context is a function, then no, there will be no self)
     */
    public function hasSelf(): bool
    {
        return $this->self !== null;
    }

    public function getSelf(): ReflectionClass
    {
        if (!$this->hasSelf()) {
            throw new RuntimeException('The current context does not have a class for self');
        }

        return $this->self;
    }

    public function getReflector(): Reflector
    {
        return $this->reflector;
    }

    public function getFileName(): string
    {
        return $this->getSelf()->getFileName();
    }
}
