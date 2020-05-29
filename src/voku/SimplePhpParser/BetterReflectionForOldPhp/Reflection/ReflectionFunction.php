<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection;

use Closure;
use PhpParser\Node;
use PhpParser\Node\FunctionLike as FunctionNode;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use voku\SimplePhpParser\BetterReflectionForOldPhp\BetterReflection;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Adapter\Exception\NotImplemented;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\Exception\FunctionDoesNotExist;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\StringCast\ReflectionFunctionStringCast;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\FunctionReflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Reflector;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Located\LocatedSource;
use voku\SimplePhpParser\BetterReflectionForOldPhp\SourceLocator\Type\ClosureSourceLocator;

class ReflectionFunction extends ReflectionFunctionAbstract implements Reflection
{
    public function __toString(): string
    {
        return ReflectionFunctionStringCast::toString($this);
    }

    /**
     * @param string $functionName
     *
     * @throws IdentifierNotFound
     *
     * @return static
     */
    public static function createFromName(string $functionName): self
    {
        return (new BetterReflection())->functionReflector()->reflect($functionName);
    }

    /**
     * @param Closure $closure
     *
     * @throws IdentifierNotFound
     *
     * @return static
     */
    public static function createFromClosure(Closure $closure): self
    {
        $configuration = new BetterReflection();

        return (new FunctionReflector(
            new ClosureSourceLocator($closure, $configuration->phpParser()),
            $configuration->classReflector()
        ))->reflect(self::CLOSURE_NAME);
    }

    /**
     * @param Reflector                                                   $reflector
     * @param Node\Expr\Closure|Node\Stmt\ClassMethod|Node\Stmt\Function_ $node          Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     * @param LocatedSource                                               $locatedSource
     * @param NamespaceNode|null                                          $namespaceNode
     *
     * @return static
     *
     * @noinspection PhpDocSignatureInspection
     *
     * @internal
     */
    public static function createFromNode(
        Reflector $reflector,
        FunctionNode $node,
        LocatedSource $locatedSource,
        ?NamespaceNode $namespaceNode = null
    ): self {
        $function = new self();

        $function->populateFunctionAbstract($reflector, $node, $locatedSource, $namespaceNode);

        return $function;
    }

    /**
     * Check to see if this function has been disabled (by the PHP INI file
     * directive `disable_functions`).
     *
     * Note - we cannot reflect on internal functions (as there is no PHP source
     * code we can access. This means, at present, we can only EVER return false
     * from this function, because you cannot disable user-defined functions.
     *
     * @see https://php.net/manual/en/ini.core.php#ini.disable-functions
     *
     * @todo https://github.com/Roave/BetterReflection/issues/14
     */
    public function isDisabled(): bool
    {
        return false;
    }

    /**
     * @throws NotImplemented
     * @throws FunctionDoesNotExist
     */
    public function getClosure(): Closure
    {
        $this->assertIsNoClosure();

        $functionName = $this->getName();

        $this->assertFunctionExist($functionName);

        return static function (...$args) use ($functionName) {
            return $functionName(...$args);
        };
    }

    /**
     * @param mixed ...$args
     *
     * @throws NotImplemented
     * @throws FunctionDoesNotExist
     *
     * @return mixed
     */
    public function invoke(...$args)
    {
        return $this->invokeArgs($args);
    }

    /**
     * @param mixed[] $args
     *
     * @throws NotImplemented
     * @throws FunctionDoesNotExist
     *
     * @return mixed
     */
    public function invokeArgs(array $args = [])
    {
        $this->assertIsNoClosure();

        $functionName = $this->getName();

        $this->assertFunctionExist($functionName);

        return $functionName(...$args);
    }

    /**
     * @throws NotImplemented
     */
    private function assertIsNoClosure(): void
    {
        if ($this->isClosure()) {
            throw new NotImplemented('Not implemented for closures');
        }
    }

    /**
     * @param string $functionName
     *
     * @psalm-assert callable-string $functionName
     *
     * @return void
     */
    private function assertFunctionExist(string $functionName): void
    {
        if (!\function_exists($functionName)) {
            throw FunctionDoesNotExist::fromName($functionName);
        }
    }
}
