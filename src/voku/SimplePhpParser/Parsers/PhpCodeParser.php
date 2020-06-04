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
use voku\cache\Cache;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\Visitors\ASTVisitor;
use voku\SimplePhpParser\Parsers\Visitors\ParentConnector;

final class PhpCodeParser
{
    public static function getFromString(string $code): ParserContainer
    {
        return self::getPhpFiles($code);
    }

    /**
     * @param string $pathOrCode
     *
     * @return ParserContainer
     *
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public static function getPhpFiles(string $pathOrCode): ParserContainer
    {
        $phpCodes = self::getCode($pathOrCode);

        $parserContainer = new ParserContainer();
        $visitor = new ASTVisitor($parserContainer);
        $cache = new Cache(null, null, false);

        $phpFilePromises = [];
        foreach ($phpCodes as $cacheKey => $codeAndFileName) {
            $phpFilePromises[] = Worker\enqueueCallable(
                [self::class, 'process'],
                $codeAndFileName['content'],
                $parserContainer,
                $visitor,
                $cache,
                $cacheKey
            );
        }

        try {
            $phpFilePromiseResponses = Promise\wait(Promise\all($phpFilePromises));
        } catch (\Amp\Parallel\Worker\TaskFailureThrowable $exception) {
            throw new \Exception($exception . ' | ' . \print_r($exception->getOriginalTrace(), true));
        }

        foreach ($phpFilePromiseResponses as $response) {
            if ($response instanceof ParserContainer) {
                $parserContainer->setClasses($response->getClasses());
                $parserContainer->setInterfaces($response->getInterfaces());
                $parserContainer->setConstants($response->getConstants());
                $parserContainer->setFunctions($response->getFunctions());
            } elseif ($response instanceof ParserErrorHandler) {
                $parserContainer->setParseError($response);
            }
        }

        $interfaces = $parserContainer->getInterfaces();
        /** @noinspection AlterInForeachInspection */
        foreach ($interfaces as &$interface) {
            $interface->parentInterfaces = $visitor->combineParentInterfaces($interface);
        }

        $classes = $parserContainer->getClasses();
        foreach ($classes as &$class) {
            $class->interfaces = Utils::flattenArray(
                $visitor->combineImplementedInterfaces($class),
                false
            );

            self::mergeIngeritdocData($class, $classes, $interfaces);
        }

        return $parserContainer;
    }

    /**
     * @param string          $phpCode
     * @param ParserContainer $parserContainer
     * @param ASTVisitor      $visitor
     * @param Cache           $cache
     * @param string          $cacheKey
     *
     * @return ParserContainer|ParserErrorHandler
     */
    public static function process(
        string $phpCode,
        ParserContainer $parserContainer,
        ASTVisitor $visitor,
        Cache $cache,
        string $cacheKey
    ) {
        $cacheKey .= '--process';

        new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider();
        $providers = new \Psalm\Internal\Provider\Providers(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeFileProvider()
        );
        new \Psalm\Internal\Analyzer\ProjectAnalyzer(
            new \voku\SimplePhpParser\Parsers\Helper\Psalm\FakeConfig(),
            $providers
        );

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

        $errorHandler = new ParserErrorHandler();

        $nameResolver = new NameResolver(
            $errorHandler,
            [
                'preserveOriginalNames' => true,
            ]
        );

        if ($cache->getCacheIsReady() === true && $cache->existsItem($cacheKey)) {
            $parsedCode = $cache->getItem($cacheKey);
        } else {
            /** @var \PhpParser\Node[]|null $parsedCode */
            $parsedCode = $parser->parse($phpCode, $errorHandler);

            $cache->setItem($cacheKey, $parsedCode);
        }

        if ($parsedCode === null) {
            return $errorHandler;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnector());
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($parsedCode);

        return $parserContainer;
    }

    /**
     * @param string $fileName
     * @param Cache  $cache
     *
     * @return array{content: string, fileName: string, cacheKey: string}
     *
     * @internal
     */
    public static function file_get_contents_with_cache(string $fileName, Cache $cache): array
    {
        $cacheKey = 'simple-php-code-parser-' . \md5($fileName) . '--' . \filemtime($fileName);

        if ($cache->getCacheIsReady() === true && $cache->existsItem($cacheKey)) {
            return $cache->getItem($cacheKey);
        }

        $content = (string) \file_get_contents($fileName);

        $return = [
            'content'  => $content,
            'fileName' => $fileName,
            'cacheKey' => $cacheKey,
        ];

        $cache->setItem($cacheKey, $return);

        return $return;
    }

    /**
     * @param string $pathOrCode
     *
     * @return array
     *
     * @psalm-return array<string, array{content: string, fileName: null|string}>
     */
    private static function getCode(string $pathOrCode): array
    {
        // init
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
            $cacheKey = 'simple-php-code-parser-' . \md5($pathOrCode);

            $phpCodes[$cacheKey]['content'] = $pathOrCode;
            $phpCodes[$cacheKey]['fileName'] = null;
        }

        $cache = new Cache(null, null, false);

        foreach ($phpFileIterators as $fileOrCode) {
            $path = $fileOrCode->getRealPath();
            if (!$path) {
                continue;
            }

            $phpFilePromises[] = Worker\enqueueCallable(
                [self::class, 'file_get_contents_with_cache'],
                $path,
                $cache
            );
        }
        $phpFilePromiseResponses = Promise\wait(Promise\all($phpFilePromises));
        foreach ($phpFilePromiseResponses as $response) {
            /** @noinspection PhpSillyAssignmentInspection - helper for phpstan */
            /** @psalm-var array{content: string, fileName: string, cacheKey: string} $response */
            $response = $response;

            $phpCodes[$response['cacheKey']]['content'] = $response['content'];
            $phpCodes[$response['cacheKey']]['fileName'] = $response['fileName'];
        }

        return $phpCodes;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPClass       $class
     * @param \voku\SimplePhpParser\Model\PHPClass[]     $classes
     * @param \voku\SimplePhpParser\Model\PHPInterface[] $interfaces
     *
     * @return void
     */
    private static function mergeIngeritdocData(
        \voku\SimplePhpParser\Model\PHPClass $class,
        array $classes,
        array $interfaces
    ): void {
        foreach ($class->methods as $method) {
            if (!$method->is_inheritdoc) {
                continue;
            }

            foreach ($class->interfaces as $interfaceStr) {
                /** @noinspection UnnecessaryIssetArgumentsInspection */
                if (isset($interfaces[$interfaceStr], $interfaces[$interfaceStr]->methods[$method->name])) {
                    $interfaceMethod = $interfaces[$interfaceStr]->methods[$method->name];

                    /** @noinspection AlterInForeachInspection */
                    /** @psalm-suppress RawObjectIteration */
                    foreach ($method as $key => &$value) {
                        if (
                            $value === null
                            &&
                            $interfaceMethod->{$key} !== null
                            &&
                            \stripos($key, 'typeFromPhpDoc') !== false
                        ) {
                            $value = $interfaceMethod->{$key};
                        }

                        if ($key === 'parameters') {
                            foreach ($value as $parameterName => $parameter) {
                                \assert($parameter instanceof \voku\SimplePhpParser\Model\PHPParameter);

                                if (!isset($interfaceMethod->parameters[$parameterName])) {
                                    continue;
                                }

                                $interfaceMethodParameter = $interfaceMethod->parameters[$parameterName];

                                /** @noinspection AlterInForeachInspection */
                                /** @psalm-suppress RawObjectIteration */
                                foreach ($parameter as $keyInner => &$valueInner) {
                                    if (
                                        $valueInner === null
                                        &&
                                        $interfaceMethodParameter->{$keyInner} !== null
                                        &&
                                        \stripos($keyInner, 'typeFromPhpDoc') !== false
                                    ) {
                                        $valueInner = $interfaceMethodParameter->{$keyInner};
                                    }
                                }
                            }
                        }
                    }
                }
            }

            /** @noinspection UnnecessaryIssetArgumentsInspection */
            if (isset($classes[$class->parentClass], $classes[$class->parentClass]->methods[$method->name])) {
                $parentMethod = $classes[$class->parentClass]->methods[$method->name];

                /** @psalm-suppress RawObjectIteration */
                foreach ($method as $key => &$value) {
                    if (
                        $value === null
                        &&
                        $parentMethod->{$key} !== null
                        &&
                        \stripos($key, 'typeFromPhpDoc') !== false
                    ) {
                        $value = $parentMethod->{$key};
                    }

                    if ($key === 'parameters') {
                        foreach ($value as $parameterName => $parameter) {
                            \assert($parameter instanceof \voku\SimplePhpParser\Model\PHPParameter);

                            if (!isset($parentMethod->parameters[$parameterName])) {
                                continue;
                            }

                            $parentMethodParameter = $parentMethod->parameters[$parameterName];
                            /** @psalm-suppress RawObjectIteration */
                            foreach ($parameter as $keyInner => &$valueInner) {
                                if (
                                    $valueInner === null
                                    &&
                                    $parentMethodParameter->{$keyInner} !== null
                                    &&
                                    \stripos($keyInner, 'typeFromPhpDoc') !== false
                                ) {
                                    $valueInner = $parentMethodParameter->{$keyInner};
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
