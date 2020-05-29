<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter;

use ReflectionException as CoreReflectionException;
use ReflectionObject as CoreReflectionObject;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass as BetterReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionMethod as BetterReflectionMethod;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionObject as BetterReflectionObject;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionProperty as BetterReflectionProperty;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

class ReflectionObject extends CoreReflectionObject
{
    /**
     * @var BetterReflectionObject
     */
    private $betterReflectionObject;

    public function __construct(BetterReflectionObject $betterReflectionObject)
    {
        $this->betterReflectionObject = $betterReflectionObject;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->betterReflectionObject->__toString();
    }

    /**
     * {@inheritdoc}
     *
     * @throws CoreReflectionException
     */
    public static function export($argument, $return = null)
    {
        $output = BetterReflectionObject::createFromInstance($argument)->__toString();

        if ($return) {
            return $output;
        }

        echo $output;

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->betterReflectionObject->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function isInternal()
    {
        return $this->betterReflectionObject->isInternal();
    }

    /**
     * {@inheritdoc}
     */
    public function isUserDefined()
    {
        return $this->betterReflectionObject->isUserDefined();
    }

    /**
     * {@inheritdoc}
     */
    public function isInstantiable()
    {
        return $this->betterReflectionObject->isInstantiable();
    }

    /**
     * {@inheritdoc}
     */
    public function isCloneable()
    {
        return $this->betterReflectionObject->isCloneable();
    }

    /**
     * {@inheritdoc}
     */
    public function getFileName()
    {
        $fileName = $this->betterReflectionObject->getFileName();

        return $fileName !== null ? FileHelper::normalizeSystemPath($fileName) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getStartLine()
    {
        return $this->betterReflectionObject->getStartLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getEndLine()
    {
        return $this->betterReflectionObject->getEndLine();
    }

    /**
     * {@inheritdoc}
     */
    public function getDocComment()
    {
        return $this->betterReflectionObject->getDocComment() ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstructor()
    {
        return new ReflectionMethod($this->betterReflectionObject->getConstructor());
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethod($name)
    {
        return $this->betterReflectionObject->hasMethod($this->getMethodRealName($name));
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod($name)
    {
        return new ReflectionMethod($this->betterReflectionObject->getMethod($this->getMethodRealName($name)));
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods($filter = null)
    {
        $methods = $this->betterReflectionObject->getMethods();

        $wrappedMethods = [];
        foreach ($methods as $key => $method) {
            $wrappedMethods[$key] = new ReflectionMethod($method);
        }

        return $wrappedMethods;
    }

    /**
     * {@inheritdoc}
     */
    public function hasProperty($name)
    {
        return $this->betterReflectionObject->hasProperty($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperty($name)
    {
        $property = $this->betterReflectionObject->getProperty($name);

        if ($property === null) {
            throw new CoreReflectionException(\sprintf('Property "%s" does not exist', $name));
        }

        return new ReflectionProperty($property);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($filter = null)
    {
        return \array_values(\array_map(static function (BetterReflectionProperty $property): ReflectionProperty {
            return new ReflectionProperty($property);
        }, $this->betterReflectionObject->getProperties()));
    }

    /**
     * {@inheritdoc}
     */
    public function hasConstant($name)
    {
        return $this->betterReflectionObject->hasConstant($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getConstants()
    {
        return $this->betterReflectionObject->getConstants();
    }

    /**
     * {@inheritdoc}
     */
    public function getConstant($name)
    {
        return $this->betterReflectionObject->getConstant($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getInterfaces()
    {
        $interfaces = $this->betterReflectionObject->getInterfaces();

        $wrappedInterfaces = [];
        foreach ($interfaces as $key => $interface) {
            $wrappedInterfaces[$key] = new ReflectionClass($interface);
        }

        return $wrappedInterfaces;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterfaceNames()
    {
        return $this->betterReflectionObject->getInterfaceNames();
    }

    /**
     * {@inheritdoc}
     */
    public function isInterface()
    {
        return $this->betterReflectionObject->isInterface();
    }

    /**
     * {@inheritdoc}
     */
    public function getTraits()
    {
        $traits = $this->betterReflectionObject->getTraits();

        /** @var array<trait-string> $traitNames */
        $traitNames = \array_map(static function (BetterReflectionClass $trait): string {
            return $trait->getName();
        }, $traits);

        $traitsByName = \array_combine(
            $traitNames,
            \array_map(static function (BetterReflectionClass $trait): ReflectionClass {
                return new ReflectionClass($trait);
            }, $traits)
        );

        \assert(
            \is_array($traitsByName),
            \sprintf(
                'Could not create an array<trait-string, ReflectionClass> for class "%s"',
                $this->betterReflectionObject->getName()
            )
        );

        return $traitsByName;
    }

    /**
     * {@inheritdoc}
     */
    public function getTraitNames()
    {
        return $this->betterReflectionObject->getTraitNames();
    }

    /**
     * {@inheritdoc}
     */
    public function getTraitAliases()
    {
        return $this->betterReflectionObject->getTraitAliases();
    }

    /**
     * {@inheritdoc}
     */
    public function isTrait()
    {
        return $this->betterReflectionObject->isTrait();
    }

    /**
     * {@inheritdoc}
     */
    public function isAbstract()
    {
        return $this->betterReflectionObject->isAbstract();
    }

    /**
     * {@inheritdoc}
     */
    public function isFinal()
    {
        return $this->betterReflectionObject->isFinal();
    }

    /**
     * {@inheritdoc}
     */
    public function getModifiers()
    {
        return $this->betterReflectionObject->getModifiers();
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

        return $this->betterReflectionObject->isInstance($object);
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
        $parentClass = $this->betterReflectionObject->getParentClass();

        if ($parentClass === null) {
            return false;
        }

        return new ReflectionClass($parentClass);
    }

    /**
     * {@inheritdoc}
     */
    public function isSubclassOf($class)
    {
        $realParentClassNames = $this->betterReflectionObject->getParentClassNames();

        $parentClassNames = \array_combine(\array_map(static function (string $parentClassName): string {
            return \strtolower($parentClassName);
        }, $realParentClassNames), $realParentClassNames);

        $realParentClassName = $parentClassNames[\strtolower($class)] ?? $class;

        return $this->betterReflectionObject->isSubclassOf($realParentClassName);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticProperties()
    {
        return $this->betterReflectionObject->getStaticProperties();
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticPropertyValue($name, $default = null)
    {
        $betterReflectionProperty = $this->betterReflectionObject->getProperty($name);

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
        $betterReflectionProperty = $this->betterReflectionObject->getProperty($name);

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
        return $this->betterReflectionObject->getDefaultProperties();
    }

    /**
     * {@inheritdoc}
     */
    public function isIterateable()
    {
        return $this->betterReflectionObject->isIterateable();
    }

    /**
     * {@inheritdoc}
     */
    public function implementsInterface($interface)
    {
        $realInterfaceNames = $this->betterReflectionObject->getInterfaceNames();

        $interfaceNames = \array_combine(\array_map(static function (string $interfaceName): string {
            return \strtolower($interfaceName);
        }, $realInterfaceNames), $realInterfaceNames);

        $realInterfaceName = $interfaceNames[\strtolower($interface)] ?? $interface;

        return $this->betterReflectionObject->implementsInterface($realInterfaceName);
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
        return $this->betterReflectionObject->getExtensionName() ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function inNamespace()
    {
        return $this->betterReflectionObject->inNamespace();
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespaceName()
    {
        return $this->betterReflectionObject->getNamespaceName();
    }

    /**
     * {@inheritdoc}
     */
    public function getShortName()
    {
        return $this->betterReflectionObject->getShortName();
    }

    private function getMethodRealName(string $name): string
    {
        $realMethodNames = \array_map(static function (BetterReflectionMethod $method): string {
            return $method->getName();
        }, $this->betterReflectionObject->getMethods());

        $methodNames = \array_combine(\array_map(static function (string $methodName): string {
            return \strtolower($methodName);
        }, $realMethodNames), $realMethodNames);

        return $methodNames[\strtolower($name)] ?? $name;
    }
}
