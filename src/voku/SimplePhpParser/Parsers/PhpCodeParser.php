<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

use Amp\Parallel\Worker;
use Amp\Promise;
use FilesystemIterator;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
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
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public static function getPhpFiles(string $pathOrCode, bool $usePhpReflection = null): ParserContainer
    {
        $phpCodes = self::getCode($pathOrCode);

        $parserContainer = new ParserContainer();
        $visitor = new ASTVisitor($parserContainer, $usePhpReflection);

        $phpFilePromises = [];
        foreach ($phpCodes as $code) {
            $phpFilePromises[] = Worker\enqueueCallable(
                [self::class, 'process'],
                $code,
                $parserContainer,
                $usePhpReflection
            );
        }

        try {
            $phpFilePromiseResponses = Promise\wait(Promise\all($phpFilePromises));
        } catch (\Amp\Parallel\Worker\TaskFailureThrowable $exception) {
            throw new \Exception($exception . ' | ' . \print_r($exception->getOriginalTrace(), true));
        }

        foreach ($phpFilePromiseResponses as $response) {
            \assert($response instanceof ParserContainer);
            $parserContainer->setClasses($response->getClasses());
            $parserContainer->setInterfaces($response->getInterfaces());
            $parserContainer->setConstants($response->getConstants());
            $parserContainer->setFunctions($response->getFunctions());
        }

        foreach ($parserContainer->getInterfaces() as $interface) {
            $interface->parentInterfaces = $visitor->combineParentInterfaces($interface);
        }

        foreach ($parserContainer->getClasses() as $class) {
            $class->interfaces = Utils::flattenArray(
                $visitor->combineImplementedInterfaces($class),
                false
            );
        }

        return $parserContainer;
    }

    /**
     * @param string          $phpCode
     * @param ParserContainer $phpContainer
     * @param bool|null       $usePhpReflection
     *
     * @return ParserContainer|null
     */
    public static function process(
        string $phpCode,
        ParserContainer $phpContainer,
        ?bool $usePhpReflection
    ): ?ParserContainer {
        new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider();
        $providers = new \Psalm\Internal\Provider\Providers(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider()
        );
        new \Psalm\Internal\Analyzer\ProjectAnalyzer(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeConfig(),
            $providers
        );

        $visitor = new ASTVisitor($phpContainer, $usePhpReflection);

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

        /** @var \PhpParser\Node[]|null $parsedCode */
        $parsedCode = $parser->parse($phpCode, new ParserErrorHandler());
        if ($parsedCode === null) {
            return null;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor($parentConnector);
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($parsedCode);

        return $phpContainer;
    }

    /**
     * @param string $pathOrCode
     *
     * @return string[]
     */
    private static function getCode(string $pathOrCode): array
    {
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
