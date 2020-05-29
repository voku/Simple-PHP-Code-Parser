<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use Exception;
use ReflectionParameter as CoreReflectionParameter;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionMethod as BetterReflectionMethod;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionParameter as BetterReflectionParameter;

class ReflectionParameter extends CoreReflectionParameter
{
    /**
     * @var BetterReflectionParameter
     */
    private $betterReflectionParameter;

    public function __construct(BetterReflectionParameter $betterReflectionParameter)
    {
        $this->betterReflectionParameter = $betterReflectionParameter;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->betterReflectionParameter->__toString();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public static function export($function, $parameter, $return = null): void
    {
        throw new Exception('Unable to export statically');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->betterReflectionParameter->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function isPassedByReference()
    {
        return $this->betterReflectionParameter->isPassedByReference();
    }

    /**
     * {@inheritdoc}
     */
    public function canBePassedByValue()
    {
        return $this->betterReflectionParameter->canBePassedByValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getDeclaringFunction()
    {
        $function = $this->betterReflectionParameter->getDeclaringFunction();
        \assert($function instanceof BetterReflectionMethod || $function instanceof \voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction);

        if ($function instanceof BetterReflectionMethod) {
            return new ReflectionMethod($function);
        }

        return new ReflectionFunction($function);
    }

    /**
     * {@inheritdoc}
     */
    public function getDeclaringClass()
    {
        $declaringClass = $this->betterReflectionParameter->getDeclaringClass();

        if ($declaringClass === null) {
            return null;
        }

        return new ReflectionClass($declaringClass);
    }

    /**
     * {@inheritdoc}
     */
    public function getClass()
    {
        $class = $this->betterReflectionParameter->getClass();

        if ($class === null) {
            return null;
        }

        return new ReflectionClass($class);
    }

    /**
     * {@inheritdoc}
     */
    public function isArray()
    {
        return $this->betterReflectionParameter->isArray();
    }

    /**
     * {@inheritdoc}
     */
    public function isCallable()
    {
        return $this->betterReflectionParameter->isCallable();
    }

    /**
     * {@inheritdoc}
     */
    public function allowsNull()
    {
        return $this->betterReflectionParameter->allowsNull();
    }

    /**
     * {@inheritdoc}
     */
    public function getPosition()
    {
        return $this->betterReflectionParameter->getPosition();
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
        return $this->betterReflectionParameter->isOptional();
    }

    /**
     * {@inheritdoc}
     */
    public function isVariadic()
    {
        return $this->betterReflectionParameter->isVariadic();
    }

    /**
     * {@inheritdoc}
     */
    public function isDefaultValueAvailable()
    {
        return $this->betterReflectionParameter->isDefaultValueAvailable();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
        return $this->betterReflectionParameter->getDefaultValue();
    }

    /**
     * {@inheritdoc}
     */
    public function isDefaultValueConstant()
    {
        return $this->betterReflectionParameter->isDefaultValueConstant();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValueConstantName()
    {
        return $this->betterReflectionParameter->getDefaultValueConstantName();
    }

    /**
     * {@inheritdoc}
     */
    public function hasType()
    {
        return $this->betterReflectionParameter->hasType();
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return ReflectionNamedType::fromReturnTypeOrNull($this->betterReflectionParameter->getType());
    }
}
