<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

use FilesystemIterator;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use voku\SimplePhpParser\Model\PhpCodeContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\Visitors\ASTVisitor;
use voku\SimplePhpParser\Parsers\Visitors\ParentConnector;

final class PhpCodeParser
{
    public static function getFromString(string $code): PhpCodeContainer
    {
        return self::getPhpFiles($code);
    }

    /**
     * @param string $path
     *
     * @return PhpCodeContainer
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public static function getPhpFiles(string $path): PhpCodeContainer
    {
        new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider();
        $providers = new \Psalm\Internal\Provider\Providers(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider()
        );
        new \Psalm\Internal\Analyzer\ProjectAnalyzer(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeConfig(),
            $providers
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $phpCode = new PhpCodeContainer();
        $visitor = new ASTVisitor($phpCode);

        self::process(
            $path,
            $visitor,
            static function ($file) {
                return true;
            }
        );

        foreach ($phpCode->getInterfaces() as $interface) {
            $interface->parentInterfaces = $visitor->combineParentInterfaces($interface);
        }

        foreach ($phpCode->getClasses() as $class) {
            $class->interfaces = Utils::flattenArray(
                $visitor->combineImplementedInterfaces($class),
                false
            );
        }

        return $phpCode;
    }

    /**
     * @param string              $pathOrCode
     * @param NodeVisitorAbstract $visitor
     * @param callable            $fileCondition
     *
     * @return void
     */
    private static function process(
        string $pathOrCode,
        NodeVisitorAbstract $visitor,
        callable $fileCondition
    ): void {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $nameResolver = new NameResolver(null, ['preserveOriginalNames' => true]);

        if (\is_file($pathOrCode)) {
            $phpCodeIterator = [new SplFileInfo($pathOrCode)];
        } elseif (\is_dir($pathOrCode)) {
            $phpCodeIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pathOrCode, FilesystemIterator::SKIP_DOTS)
            );
        } else {
            $phpCodeIterator[] = $pathOrCode;
        }
        foreach ($phpCodeIterator as $fileOrCode) {
            if ($fileOrCode instanceof SplFileInfo) {
                if (!$fileCondition($fileOrCode)) {
                    continue;
                }

                $pathOrCode = $fileOrCode->getRealPath();
                if (!$pathOrCode) {
                    continue;
                }

                $code = \file_get_contents($pathOrCode);
            } elseif (\is_string($fileOrCode)) {
                $code = $fileOrCode;
            } else {
                $code = null;
            }

            if (!$code) {
                continue;
            }

            /** @var \PhpParser\Node[]|null $parsedCode */
            $parsedCode = $parser->parse($code, new ParserErrorHandler());
            if ($parsedCode === null) {
                continue;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new ParentConnector());
            $traverser->addVisitor($nameResolver);
            $traverser->addVisitor($visitor);
            $traverser->traverse($parsedCode);
        }
    }
}
