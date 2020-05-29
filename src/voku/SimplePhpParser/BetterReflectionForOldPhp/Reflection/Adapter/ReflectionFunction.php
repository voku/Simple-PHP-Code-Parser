<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use Exception;
use ReflectionException as CoreReflectionException;
use ReflectionFunction as CoreReflectionFunction;
use Throwable;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter\Exception\NotImplemented;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionFunction as BetterReflectionFunction;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

class ReflectionFunction extends CoreReflectionFunction
{
    /**
     * @var BetterReflectionFunction
     */
    private $betterReflectionFunction;

    public function __construct(BetterReflectionFunction $betterReflectionFunction)
    {
        $this->betterReflectionFunction = $betterReflectionFunction;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->betterReflectionFunction->__toString();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public static function export($name, $return = null): void
    {
        throw new Exception('Unable to export statically');
    }

    /**
     * {@inheritdoc}
     */
    public function inNamespace()
    {
        return $this->betterReflectionFunction->inNamespace();
    }

    /**
     * {@inheritdoc}
     */
    public function isClosure()
    {
        return $this->betterReflectionFunction->isClosure();
    }

    /**
     * {@inheritdoc}
     */
    public function isDeprecated()
    {
        return $this->betterReflectionFunction->isDeprecated();
    }

    /**
     * {@inheritdoc}
     */
    public function isInternal()
    {
        return $this->betterReflectionFunction->isInternal();
    }

    /**
     * {@inheritdoc}
     */
    public function isUserDefined()
    {
        return $this->betterReflectionFunction->isUserDefined();
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
        return $this->betterReflectionFunction->getDocComment() ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndLine()
    {
        return $this->betterReflectionFunction->getEndLine();
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
        return $this->betterReflectionFunction->getExtensionName() ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileName()
    {
        $fileName = $this->betterReflectionFunction->getFileName();

        return $fileName !== null ? FileHelper::normalizeSystemPath($fileName) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->betterReflectionFunction->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespaceName()
    {
        return $this->betterReflectionFunction->getNamespaceName();
    }

    /**
     * {@inheritdoc}
     */
    public function getNumberOfParameters()
    {
        return $this->betterReflectionFunction->getNumberOfParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function getNumberOfRequiredParameters()
    {
        return $this->betterReflectionFunction->getNumberOfRequiredParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        $parameters = $this->betterReflectionFunction->getParameters();

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
        return ReflectionNamedType::fromReturnTypeOrNull($this->betterReflectionFunction->getReturnType());
    }

    /**
     * {@inheritdoc}
     */
    public function getShortName()
    {
        return $this->betterReflectionFunction->getShortName();
    }

    /**
     * {@inheritdoc}
     */
    public function getStartLine()
    {
        return $this->betterReflectionFunction->getStartLine();
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
        return $this->betterReflectionFunction->returnsReference();
    }

    /**
     * {@inheritdoc}
     */
    public function isGenerator()
    {
        return $this->betterReflectionFunction->isGenerator();
    }

    /**
     * {@inheritdoc}
     */
    public function isVariadic()
    {
        return $this->betterReflectionFunction->isVariadic();
    }

    /**
     * {@inheritdoc}
     */
    public function isDisabled()
    {
        return $this->betterReflectionFunction->isDisabled();
    }

    /**
     * {@inheritdoc}
     */
    public function invoke($args = null)
    {
        try {
            return $this->betterReflectionFunction->invoke(...\func_get_args());
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invokeArgs(array $args)
    {
        try {
            return $this->betterReflectionFunction->invokeArgs($args);
        } catch (Throwable $e) {
            throw new CoreReflectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClosure()
    {
        try {
            return $this->betterReflectionFunction->getClosure();
        } catch (Throwable $e) {
            return null;
        }
    }
}
