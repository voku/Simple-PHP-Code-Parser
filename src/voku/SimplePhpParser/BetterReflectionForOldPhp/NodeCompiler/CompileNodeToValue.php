<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\BetterReflectionForOldPhp\NodeCompiler;

use function constant;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClass;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflection\ReflectionClassConstant;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Reflector\Exception\IdentifierNotFound;
use voku\SimplePhpParser\BetterReflectionForOldPhp\Util\FileHelper;

class CompileNodeToValue
{
    /**
     * Compile an expression from a node into a value.
     *
     * @param Node\Expr|Node\Stmt\Expression $node    Node has to be processed by the PhpParser\NodeVisitor\NameResolver
     * @param CompilerContext                $context
     *
     * @throws Exception\UnableToCompileNode
     *
     * @return mixed
     *
     * @psalm-return scalar|array<scalar>|null
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function __invoke(Node $node, CompilerContext $context)
    {
        if ($node instanceof Node\Stmt\Expression) {
            return $this($node->expr, $context);
        }

        $constExprEvaluator = new ConstExprEvaluator(function (Node\Expr $node) use ($context) {
            if ($node instanceof Node\Expr\ConstFetch) {
                return $this->compileConstFetch($node, $context);
            }

            if ($node instanceof Node\Expr\ClassConstFetch) {
                return $this->compileClassConstFetch($node, $context);
            }

            if ($node instanceof Node\Scalar\MagicConst\Dir) {
                return $this->compileDirConstant($context);
            }

            if ($node instanceof Node\Scalar\MagicConst\Class_) {
                return $this->compileClassConstant($context);
            }

            throw Exception\UnableToCompileNode::forUnRecognizedExpressionInContext($node, $context);
        });

        return $constExprEvaluator->evaluateDirectly($node);
    }

    /**
     * Compile constant expressions
     *
     * @param Node\Expr\ConstFetch $constNode
     * @param CompilerContext      $context
     *
     * @throws Exception\UnableToCompileNode
     *
     * @return mixed
     *
     * @psalm-return scalar|array<scalar>|null
     */
    private function compileConstFetch(Node\Expr\ConstFetch $constNode, CompilerContext $context)
    {
        $firstName = \reset($constNode->name->parts);
        switch ($firstName) {
            case 'null':
                return null;
            case 'false':
                return false;
            case 'true':
                return true;
            default:
                if (!\defined($firstName)) {
                    throw Exception\UnableToCompileNode::becauseOfNotFoundConstantReference($context, $constNode);
                }

                return \constant($firstName);
        }
    }

    /**
     * Compile class constants
     *
     * @param Node\Expr\ClassConstFetch $node
     * @param CompilerContext           $context
     *
     * @throws IdentifierNotFound
     * @throws Exception\UnableToCompileNode if a referenced constant could not be located on the expected referenced class
     *
     * @return mixed
     *
     * @psalm-return scalar|array<scalar>|null
     */
    private function compileClassConstFetch(Node\Expr\ClassConstFetch $node, CompilerContext $context)
    {
        \assert($node->name instanceof Node\Identifier);
        $nodeName = $node->name->name;
        \assert($node->class instanceof Node\Name);
        $className = $node->class->toString();

        if ($nodeName === 'class') {
            return $this->resolveClassNameForClassNameConstant($className, $context);
        }

        $classInfo = null;

        if ($className === 'self' || $className === 'static') {
            $classInfo = $context->getSelf()->hasConstant($nodeName) ? $context->getSelf() : null;
        } elseif ($className === 'parent') {
            $classInfo = $context->getSelf()->getParentClass();
        }

        if ($classInfo === null) {
            $classInfo = $context->getReflector()->reflect($className);
            \assert($classInfo instanceof ReflectionClass);
        }

        $reflectionConstant = $classInfo->getReflectionConstant($nodeName);

        if (!$reflectionConstant instanceof ReflectionClassConstant) {
            throw Exception\UnableToCompileNode::becauseOfNotFoundClassConstantReference($context, $classInfo, $node);
        }

        return $this->__invoke(
            $reflectionConstant->getAst()->consts[$reflectionConstant->getPositionInAst()]->value,
            new CompilerContext($context->getReflector(), $classInfo)
        );
    }

    /**
     * Compile a __DIR__ node
     *
     * @param CompilerContext $context
     *
     * @return string
     */
    private function compileDirConstant(CompilerContext $context): string
    {
        return FileHelper::normalizeWindowsPath(\dirname(\realpath($context->getFileName())));
    }

    /**
     * Compiles magic constant __CLASS__
     *
     * @param CompilerContext $context
     *
     * @return string
     */
    private function compileClassConstant(CompilerContext $context): string
    {
        return $context->hasSelf() ? $context->getSelf()->getName() : '';
    }

    private function resolveClassNameForClassNameConstant(string $className, CompilerContext $context): string
    {
        if ($className === 'self' || $className === 'static') {
            return $context->getSelf()->getName();
        }

        if ($className === 'parent') {
            $parentClass = $context->getSelf()->getParentClass();
            \assert($parentClass instanceof ReflectionClass);

            return $parentClass->getName();
        }

        return $className;
    }
}
