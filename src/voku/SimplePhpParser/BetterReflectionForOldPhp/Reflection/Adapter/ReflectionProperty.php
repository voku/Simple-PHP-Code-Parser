<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use Exception;
use ReflectionException as CoreReflectionException;
use ReflectionProperty as CoreReflectionProperty;
use Throwable;
use TypeError;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\NoObjectProvided;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\NotAnObject;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionProperty as BetterReflectionProperty;

class ReflectionProperty extends CoreReflectionProperty
{
    /**
     * @var BetterReflectionProperty
     */
    private $betterReflectionProperty;

    /**
     * @var bool
     */
    private $accessible = false;

    public function __construct(BetterReflectionProperty $betterReflectionProperty)
    {
        $this->betterReflectionProperty = $betterReflectionProperty;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->betterReflectionProperty->__toString();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public static function export($class, $name, $return = null): void
    {
        throw new Exception('Unable to export statically');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->betterReflectionProperty->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($object = null)
    {
        if (!$this->isAccessible()) {
            throw new CoreReflectionException('Property not accessible');
        }

        try {
            return $this->betterReflectionProperty->getValue($object);
        } catch (NoObjectProvided | TypeError $e) {
            return null;
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($object, $value = null): void
    {
        if (!$this->isAccessible()) {
            throw new CoreReflectionException('Property not accessible');
        }

        try {
            $this->betterReflectionProperty->setValue($object, $value);
        } catch (NoObjectProvided | NotAnObject $e) {
            return;
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasType()
    {
        return $this->betterReflectionProperty->hasType();
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return ReflectionNamedType::fromReturnTypeOrNull($this->betterReflectionProperty->getType());
    }

    /**
     * {@inheritdoc}
     */
    public function isPublic()
    {
        return $this->betterReflectionProperty->isPublic();
    }

    /**
     * {@inheritdoc}
     */
    public function isPrivate()
    {
        return $this->betterReflectionProperty->isPrivate();
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return $this->betterReflectionProperty->isProtected();
    }

    /**
     * {@inheritdoc}
     */
    public function isStatic()
    {
        return $this->betterReflectionProperty->isStatic();
    }

    /**
     * {@inheritdoc}
     */
    public function isDefault()
    {
        return $this->betterReflectionProperty->isDefault();
    }

    /**
     * {@inheritdoc}
     */
    public function getModifiers()
    {
        return $this->betterReflectionProperty->getModifiers();
    }

    /**
     * {@inheritdoc}
     */
    public function getDeclaringClass()
    {
        return new ReflectionClass($this->betterReflectionProperty->getImplementingClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getDocComment()
    {
        return $this->betterReflectionProperty->getDocComment() ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessible($accessible): void
    {
        $this->accessible = true;
    }

    public function isAccessible(): bool
    {
        return $this->accessible || $this->isPublic();
    }
}
