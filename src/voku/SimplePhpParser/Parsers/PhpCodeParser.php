<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

use Amp\Parallel\Worker;
use Amp\Promise;
use FilesystemIterator;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\Visitors\ASTVisitor;
use voku\SimplePhpParser\Parsers\Visitors\ParentConnector;

final class PhpCodeParser
{
    public static function getFromString(string $code): ParserContainer
    {
        return self::getPhpFiles($code, false);
    }

    /**
     * @param string    $pathOrCode
     * @param bool|null $usePhpReflection <p>
     *                                    null = Php-Parser + PHP-Reflection<br>
     *                                    true = PHP-Reflection<br>
     *                                    false = Php-Parser<br>
     *                                    <p>
     *
     * @return ParserContainer
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public static function getPhpFiles(string $pathOrCode, bool $usePhpReflection = null): ParserContainer
    {
        $phpCodes = self::getCode($pathOrCode);

        new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider();
        $providers = new \Psalm\Internal\Provider\Providers(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider()
        );
        new \Psalm\Internal\Analyzer\ProjectAnalyzer(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeConfig(),
            $providers
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $phpCode = new ParserContainer();
        $visitor = new ASTVisitor($phpCode, $usePhpReflection);

        self::process(
            $phpCodes,
            $visitor
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
     * @param string[]            $phpCodes
     * @param NodeVisitorAbstract $visitor
     *
     * @return void
     */
    private static function process(
        array $phpCodes,
        NodeVisitorAbstract $visitor
    ): void {
        $parser = (new ParserFactory())->create(
            ParserFactory::PREFER_PHP7,
            new Emulative(
                [
                    'usedAttributes' => [
                        'comments',
                        'startLine',
                        'endLine',
                        'startTokenPos',
                        'endTokenPos',
                    ],
                ]
            )
        );

        $nameResolver = new NameResolver(
            new ParserErrorHandler(),
            [
                'preserveOriginalNames' => true,
            ]
        );

        $parentConnector = new ParentConnector();

        foreach ($phpCodes as $code) {
            /** @var \PhpParser\Node[]|null $parsedCode */
            $parsedCode = $parser->parse($code, new ParserErrorHandler());
            if ($parsedCode === null) {
                return;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor($parentConnector);
            $traverser->addVisitor($nameResolver);
            $traverser->addVisitor($visitor);
            $traverser->traverse($parsedCode);
        }
    }

    /**
     * @param string $pathOrCode
     *
     * @return string[]
     */
    private static function getCode(string $pathOrCode): array {
        // init
        /** @var string[] $phpCodes */
        $phpCodes = [];
        /** @var SplFileInfo[] $phpFileIterators */
        $phpFileIterators = [];
        /** @var Promise[] $phpFilePromises */
        $phpFilePromises = [];

        if (\is_file($pathOrCode)) {
            $phpFileIterators = [new SplFileInfo($pathOrCode)];
        } elseif (\is_dir($pathOrCode)) {
            $phpFileIterators = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pathOrCode, FilesystemIterator::SKIP_DOTS)
            );
        } else {
            $phpCodes[] = $pathOrCode;
        }

        foreach ($phpFileIterators as $fileOrCode) {
            $path = $fileOrCode->getRealPath();
            if (!$path) {
                continue;
            }

            $phpFilePromises[] = Worker\enqueueCallable('file_get_contents', $path);
        }
        $phpFilePromiseResponses = Promise\wait(Promise\all($phpFilePromises));
        foreach ($phpFilePromiseResponses as $response) {
            $phpCodes[] = $response;
        }

        return $phpCodes;
    }
}
