<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use InvalidArgumentException;
use OutOfBoundsException;
use ReflectionClass as CoreReflectionClass;
use ReflectionException as CoreReflectionException;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass as BetterReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClassConstant as BetterReflectionClassConstant;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionMethod as BetterReflectionMethod;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionObject as BetterReflectionObject;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionProperty as BetterReflectionProperty;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

class ReflectionClass extends CoreReflectionClass
{
    /**
     * @var BetterReflectionClass
     */
    private $betterReflectionClass;

    public function __construct(BetterReflectionClass $betterReflectionClass)
    {
        $this->betterReflectionClass = $betterReflectionClass;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->betterReflectionClass->__toString();
    }

    /**
     * {@inheritdoc}
     *
     * @throws CoreReflectionException
     */
    public static function export($argument, $return = false)
    {
        if (\is_string($argument) || \is_object($argument)) {
            if (\is_string($argument)) {
                $output = BetterReflectionClass::createFromName($argument)->__toString();
            } else {
                $output = BetterReflectionObject::createFromInstance($argument)->__toString();
            }

            if ($return) {
                return $output;
            }

            echo $output;

            return null;
        }

        throw new InvalidArgumentException('Class name must be provided');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->betterReflectionClass->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function isAnonymous()
    {
        return $this->betterReflectionClass->isAnonymous();
    }

    /**
     * {@inheritdoc}
     */
    public function isInternal()
    {
        return $this->betterReflectionClass->isInternal();
    }

    /**
     * {@inheritdoc}
     */
    public function isUserDefined()
    {
        return $this->betterReflectionClass->isUserDefined();
    }

    /**
     * {@inheritdoc}
     */
    public function isInstantiable()
    {
        return $this->betterReflectionClass->isInstantiable();
    }

    /**
     * {@inheritdoc}
     */
    public function isCloneable()
    {
        return $this->betterReflectionClass->isCloneable();
    }

    /**
     * {@inheritdoc}
     */
    public function getFileName()
    {
        $fileName = $this->betterReflectionClass->getFileName();

        return $fileName !== null ? FileHelper::normalizeSystemPath($fileName) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getStartLine()
    {
        return $this->betterReflectionClass->getStartLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getEndLine()
    {
        return $this->betterReflectionClass->getEndLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getDocComment()
    {
        return $this->betterReflectionClass->getDocComment() ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstructor()
    {
        try {
            return new ReflectionMethod($this->betterReflectionClass->getConstructor());
        } catch (OutOfBoundsException $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethod($name)
    {
        return $this->betterReflectionClass->hasMethod($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod($name)
    {
        return new ReflectionMethod($this->betterReflectionClass->getMethod($name));
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods($filter = null)
    {
        return \array_map(static function (BetterReflectionMethod $method): ReflectionMethod {
            return new ReflectionMethod($method);
        }, $this->betterReflectionClass->getMethods($filter));
    }

    /**
     * {@inheritdoc}
     */
    public function hasProperty($name)
    {
        return $this->betterReflectionClass->hasProperty($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperty($name)
    {
        $betterReflectionProperty = $this->betterReflectionClass->getProperty($name);

        if ($betterReflectionProperty === null) {
            throw new CoreReflectionException(\sprintf('Property "%s" does not exist', $name));
        }

        return new ReflectionProperty($betterReflectionProperty);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($filter = null)
    {
        return \array_values(\array_map(static function (BetterReflectionProperty $property): ReflectionProperty {
            return new ReflectionProperty($property);
        }, $this->betterReflectionClass->getProperties($filter)));
    }

    /**
     * {@inheritdoc}
     */
    public function hasConstant($name)
    {
        return $this->betterReflectionClass->hasConstant($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getConstants()
    {
        return $this->betterReflectionClass->getConstants();
    }

    /**
     * {@inheritdoc}
     */
    public function getConstant($name)
    {
        return $this->betterReflectionClass->getConstant($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionConstant($name)
    {
        return new ReflectionClassConstant(
            $this->betterReflectionClass->getReflectionConstant($name)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionConstants()
    {
        return \array_values(\array_map(static function (BetterReflectionClassConstant $betterConstant): ReflectionClassConstant {
            return new ReflectionClassConstant($betterConstant);
        }, $this->betterReflectionClass->getReflectionConstants()));
    }

    /**
     * {@inheritdoc}
     */
    public function getInterfaces()
    {
        $interfaces = $this->betterReflectionClass->getInterfaces();

        $wrappedInterfaces = [];
        foreach ($interfaces as $key => $interface) {
            $wrappedInterfaces[$key] = new self($interface);
        }

        return $wrappedInterfaces;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterfaceNames()
    {
        return $this->betterReflectionClass->getInterfaceNames();
    }

    /**
     * {@inheritdoc}
     */
    public function isInterface()
    {
        return $this->betterReflectionClass->isInterface();
    }

    /**
     * {@inheritdoc}
     */
    public function getTraits()
    {
        $traits = $this->betterReflectionClass->getTraits();

        /** @var array<trait-string> $traitNames */
        $traitNames = \array_map(static function (BetterReflectionClass $trait): string {
            return $trait->getName();
        }, $traits);

        $traitsByName = \array_combine(
            $traitNames,
            \array_map(static function (BetterReflectionClass $trait): self {
                return new self($trait);
            }, $traits)
        );

        \assert(
            \is_array($traitsByName),
            \sprintf(
                'Could not create an array<trait-string, ReflectionClass> for class "%s"',
                $this->betterReflectionClass->getName()
            )
        );

        return $traitsByName;
    }

    /**
     * {@inheritdoc}
     */
    public function getTraitNames()
    {
        return $this->betterReflectionClass->getTraitNames();
    }

    /**
     * {@inheritdoc}
     */
    public function getTraitAliases()
    {
        return $this->betterReflectionClass->getTraitAliases();
    }

    /**
     * {@inheritdoc}
     */
    public function isTrait()
    {
        return $this->betterReflectionClass->isTrait();
    }

    /**
     * {@inheritdoc}
     */
    public function isAbstract()
    {
        return $this->betterReflectionClass->isAbstract();
    }

    /**
     * {@inheritdoc}
     */
    public function isFinal()
    {
        return $this->betterReflectionClass->isFinal();
    }

    /**
     * {@inheritdoc}
     */
    public function getModifiers()
    {
        return $this->betterReflectionClass->getModifiers();
    }

    /**
     * {@inheritdoc}
     *
     * @see https://bugs.php.net/bug.php?id=79645
     *
     * @param mixed $object in PHP 7.x, the type declaration is absent in core reflection
     */
    public function isInstance($object)
    {
        if (!\is_object($object)) {
            return null;
        }

        return $this->betterReflectionClass->isInstance($object);
    }

    /**
     * {@inheritdoc}
     */
    public function newInstance($arg = null, ...$args): void
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function newInstanceWithoutConstructor(): void
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function newInstanceArgs(?array $args = null): void
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getParentClass()
    {
        $parentClass = $this->betterReflectionClass->getParentClass();

        if ($parentClass === null) {
            return false;
        }

        return new self($parentClass);
    }

    /**
     * {@inheritdoc}
     */
    public function isSubclassOf($class)
    {
        $realParentClassNames = $this->betterReflectionClass->getParentClassNames();

        $parentClassNames = \array_combine(\array_map(static function (string $parentClassName): string {
            return \strtolower($parentClassName);
        }, $realParentClassNames), $realParentClassNames);

        $realParentClassName = $parentClassNames[\strtolower($class)] ?? $class;

        return $this->betterReflectionClass->isSubclassOf($realParentClassName) || $this->implementsInterface($class);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticProperties()
    {
        return $this->betterReflectionClass->getStaticProperties();
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticPropertyValue($name, $default = null)
    {
        $betterReflectionProperty = $this->betterReflectionClass->getProperty($name);

        if ($betterReflectionProperty === null) {
            if (\func_num_args() === 2) {
                return $default;
            }

            throw new CoreReflectionException(\sprintf('Property "%s" does not exist', $name));
        }

        $property = new ReflectionProperty($betterReflectionProperty);

        if (!$property->isAccessible()) {
            throw new CoreReflectionException(\sprintf('Property "%s" is not accessible', $name));
        }

        if (!$property->isStatic()) {
            throw new CoreReflectionException(\sprintf('Property "%s" is not static', $name));
        }

        return $property->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function setStaticPropertyValue($name, $value): void
    {
        $betterReflectionProperty = $this->betterReflectionClass->getProperty($name);

        if ($betterReflectionProperty === null) {
            throw new CoreReflectionException(\sprintf('Property "%s" does not exist', $name));
        }

        $property = new ReflectionProperty($betterReflectionProperty);

        if (!$property->isAccessible()) {
            throw new CoreReflectionException(\sprintf('Property "%s" is not accessible', $name));
        }

        if (!$property->isStatic()) {
            throw new CoreReflectionException(\sprintf('Property "%s" is not static', $name));
        }

        $property->setValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultProperties()
    {
        return $this->betterReflectionClass->getDefaultProperties();
    }

    /**
     * {@inheritdoc}
     */
    public function isIterateable()
    {
        return $this->betterReflectionClass->isIterateable();
    }

    /**
     * {@inheritdoc}
     */
    public function implementsInterface($interface)
    {
        $realInterfaceNames = $this->betterReflectionClass->getInterfaceNames();

        $interfaceNames = \array_combine(\array_map(static function (string $interfaceName): string {
            return \strtolower($interfaceName);
        }, $realInterfaceNames), $realInterfaceNames);

        $realInterfaceName = $interfaceNames[\strtolower($interface)] ?? $interface;

        return $this->betterReflectionClass->implementsInterface($realInterfaceName);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension(): void
    {
        throw new Exception\NotImplemented('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensionName()
    {
        return $this->betterReflectionClass->getExtensionName() ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function inNamespace()
    {
        return $this->betterReflectionClass->inNamespace();
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespaceName()
    {
        return $this->betterReflectionClass->getNamespaceName();
    }

    /**
     * {@inheritdoc}
     */
    public function getShortName()
    {
        return $this->betterReflectionClass->getShortName();
    }
}
