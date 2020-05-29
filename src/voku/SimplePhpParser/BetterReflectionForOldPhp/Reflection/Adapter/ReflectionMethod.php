<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use Exception;
use ReflectionException as CoreReflectionException;
use ReflectionMethod as CoreReflectionMethod;
use Throwable;
use TypeError;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter\Exception\NotImplemented;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\NoObjectProvided;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionMethod as BetterReflectionMethod;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

class ReflectionMethod extends CoreReflectionMethod
{
    /**
     * @var BetterReflectionMethod
     */
    private $betterReflectionMethod;

    /**
     * @var bool
     */
    private $accessible = false;

    /**
     * @param BetterReflectionMethod $betterReflectionMethod
     */
    public function __construct(BetterReflectionMethod $betterReflectionMethod)
    {
        $this->betterReflectionMethod = $betterReflectionMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->betterReflectionMethod->__toString();
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
    public function inNamespace()
    {
        return $this->betterReflectionMethod->inNamespace();
    }

    /**
     * {@inheritdoc}
     */
    public function isClosure()
    {
        return $this->betterReflectionMethod->isClosure();
    }

    /**
     * {@inheritdoc}
     */
    public function isDeprecated()
    {
        return $this->betterReflectionMethod->isDeprecated();
    }

    /**
     * {@inheritdoc}
     */
    public function isInternal()
    {
        return $this->betterReflectionMethod->isInternal();
    }

    /**
     * {@inheritdoc}
     */
    public function isUserDefined()
    {
        return $this->betterReflectionMethod->isUserDefined();
    }

    /**
     * {@inheritdoc}
     */
    public function getClosureThis(): void
    {
        throw new NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getClosureScopeClass(): void
    {
        throw new NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getDocComment()
    {
        return $this->betterReflectionMethod->getDocComment() ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndLine()
    {
        return $this->betterReflectionMethod->getEndLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension(): void
    {
        throw new NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensionName()
    {
        return $this->betterReflectionMethod->getExtensionName() ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileName()
    {
        $fileName = $this->betterReflectionMethod->getFileName();

        return $fileName !== null ? FileHelper::normalizeSystemPath($fileName) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->betterReflectionMethod->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespaceName()
    {
        return $this->betterReflectionMethod->getNamespaceName();
    }

    /**
     * {@inheritdoc}
     */
    public function getNumberOfParameters()
    {
        return $this->betterReflectionMethod->getNumberOfParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function getNumberOfRequiredParameters()
    {
        return $this->betterReflectionMethod->getNumberOfRequiredParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        $parameters = $this->betterReflectionMethod->getParameters();

        $wrappedParameters = [];
        foreach ($parameters as $key => $parameter) {
            $wrappedParameters[$key] = new ReflectionParameter($parameter);
        }

        return $wrappedParameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnType()
    {
        return ReflectionNamedType::fromReturnTypeOrNull($this->betterReflectionMethod->getReturnType());
    }

    /**
     * {@inheritdoc}
     */
    public function getShortName()
    {
        return $this->betterReflectionMethod->getShortName();
    }

    /**
     * {@inheritdoc}
     */
    public function getStartLine()
    {
        return $this->betterReflectionMethod->getStartLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticVariables(): void
    {
        throw new NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function returnsReference()
    {
        return $this->betterReflectionMethod->returnsReference();
    }

    /**
     * {@inheritdoc}
     */
    public function isGenerator()
    {
        return $this->betterReflectionMethod->isGenerator();
    }

    /**
     * {@inheritdoc}
     */
    public function isVariadic()
    {
        return $this->betterReflectionMethod->isVariadic();
    }

    /**
     * {@inheritdoc}
     */
    public function isPublic()
    {
        return $this->betterReflectionMethod->isPublic();
    }

    /**
     * {@inheritdoc}
     */
    public function isPrivate()
    {
        return $this->betterReflectionMethod->isPrivate();
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return $this->betterReflectionMethod->isProtected();
    }

    /**
     * {@inheritdoc}
     */
    public function isAbstract()
    {
        return $this->betterReflectionMethod->isAbstract();
    }

    /**
     * {@inheritdoc}
     */
    public function isFinal()
    {
        return $this->betterReflectionMethod->isFinal();
    }

    /**
     * {@inheritdoc}
     */
    public function isStatic()
    {
        return $this->betterReflectionMethod->isStatic();
    }

    /**
     * {@inheritdoc}
     */
    public function isConstructor()
    {
        return $this->betterReflectionMethod->isConstructor();
    }

    /**
     * {@inheritdoc}
     */
    public function isDestructor()
    {
        return $this->betterReflectionMethod->isDestructor();
    }

    /**
     * {@inheritdoc}
     */
    public function getClosure($object = null)
    {
        try {
            return $this->betterReflectionMethod->getClosure($object);
        } catch (NoObjectProvided | TypeError $e) {
            return null;
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getModifiers()
    {
        return $this->betterReflectionMethod->getModifiers();
    }

    /**
     * {@inheritdoc}
     */
    public function invoke($object = null, $args = null)
    {
        if (!$this->isAccessible()) {
            throw new CoreReflectionException('Method not accessible');
        }

        try {
            return $this->betterReflectionMethod->invoke(...\func_get_args());
        } catch (NoObjectProvided | TypeError $e) {
            return null;
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invokeArgs($object = null, array $args = [])
    {
        if (!$this->isAccessible()) {
            throw new CoreReflectionException('Method not accessible');
        }

        try {
            return $this->betterReflectionMethod->invokeArgs($object, $args);
        } catch (NoObjectProvided | TypeError $e) {
            return null;
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDeclaringClass()
    {
        return new ReflectionClass($this->betterReflectionMethod->getImplementingClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getPrototype()
    {
        return new self($this->betterReflectionMethod->getPrototype());
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessible($accessible): void
    {
        $this->accessible = true;
    }

    private function isAccessible(): bool
    {
        return $this->accessible || $this->isPublic();
    }
}
