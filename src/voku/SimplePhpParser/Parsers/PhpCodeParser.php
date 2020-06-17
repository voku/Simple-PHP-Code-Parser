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
    /**
     * @param string   $code
     * @param string[] $autoloaderProjectPaths
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getFromString(
        string $code,
        array $autoloaderProjectPaths = []
    ): ParserContainer {
        return self::getPhpFiles(
            $code,
            $autoloaderProjectPaths
        );
    }

    /**
     * @param string   $pathOrCode
     * @param string[] $autoloaderProjectPaths
     * @param string[] $pathExcludeRegex
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     *
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public static function getPhpFiles(
        string $pathOrCode,
        array $autoloaderProjectPaths = [],
        array $pathExcludeRegex = []
    ): ParserContainer {
        $phpCodes = self::getCode(
            $pathOrCode,
            $pathExcludeRegex
        );

        $parserContainer = new ParserContainer();
        $visitor = new ASTVisitor($parserContainer);
        $cache = new Cache(null, null, false);

        $phpFilePromises = [];
        foreach ($phpCodes as $cacheKey => $codeAndFileName) {
            $phpFilePromises[] = Worker\enqueueCallable(
                [self::class, 'process'],
                $codeAndFileName['content'],
                $codeAndFileName['fileName'],
                $parserContainer,
                $visitor,
                $cache,
                $cacheKey,
                $autoloaderProjectPaths
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

        $pathTmp = null;
        if (\is_file($pathOrCode)) {
            $pathTmp = \realpath(\pathinfo($pathOrCode, \PATHINFO_DIRNAME));
        } elseif (\is_dir($pathOrCode)) {
            $pathTmp = \realpath($pathOrCode);
        }

        $classesTmp = &$parserContainer->getClassesByReference();
        /** @noinspection AlterInForeachInspection */
        foreach ($classesTmp as &$classTmp) {
            $classTmp->interfaces = Utils::flattenArray(
                $visitor->combineImplementedInterfaces($classTmp),
                false
            );

            self::mergeInheritdocData($classTmp, $classesTmp, $interfaces);
        }

        // remove properties / methods / classes from outside of the current file-path-scope
        if ($pathTmp) {
            $classesTmp2 = &$parserContainer->getClassesByReference();
            foreach ($classesTmp2 as $classKey => $classTmp2) {
                foreach ($classTmp2->constants as $constantKey => $constant) {
                    if ($constant->file && \strpos($constant->file, $pathTmp) === false) {
                        unset($classTmp2->constants[$constantKey]);
                    }
                }

                foreach ($classTmp2->properties as $propertyKey => $property) {
                    if ($property->file && \strpos($property->file, $pathTmp) === false) {
                        unset($classTmp2->properties[$propertyKey]);
                    }
                }

                foreach ($classTmp2->methods as $methodKey => $method) {
                    if ($method->file && \strpos($method->file, $pathTmp) === false) {
                        unset($classTmp2->methods[$methodKey]);
                    }
                }

                if ($classTmp2->file && \strpos($classTmp2->file, $pathTmp) === false) {
                    unset($classesTmp2[$classKey]);
                }
            }
        }

        return $parserContainer;
    }

    /**
     * @param string                                               $phpCode
     * @param string|null                                          $fileName
     * @param \voku\SimplePhpParser\Parsers\Helper\ParserContainer $parserContainer
     * @param \voku\SimplePhpParser\Parsers\Visitors\ASTVisitor    $visitor
     * @param \voku\cache\Cache                                    $cache
     * @param string                                               $cacheKey
     * @param string[]                                             $autoloaderProjectPaths
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer|\voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler
     */
    public static function process(
        string $phpCode,
        ?string $fileName,
        ParserContainer $parserContainer,
        ASTVisitor $visitor,
        Cache $cache,
        string $cacheKey,
        array $autoloaderProjectPaths
    ) {
        $cacheKey .= '--process';

        foreach ($autoloaderProjectPaths as $projectPath) {
            if (\file_exists($projectPath . '/vendor/autoload.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once $projectPath . '/vendor/autoload.php';
            } elseif (\file_exists($projectPath . '/../vendor/autoload.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once $projectPath . '/../vendor/autoload.php';
            } elseif (\file_exists($projectPath) && \is_file($projectPath)) {
                /** @noinspection PhpIncludeInspection */
                require_once $projectPath;
            }
        }

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

        $visitor->fileName = $fileName;

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnector());
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        /** @noinspection UnusedFunctionResultInspection */
        $traverser->traverse($parsedCode);

        return $parserContainer;
    }

    /**
     * @param string            $fileName
     * @param \voku\cache\Cache $cache
     *
     * @return array
     *
     * @psalm-return array{content: string, fileName: string, cacheKey: string}
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
     * @param string   $pathOrCode
     * @param string[] $pathExcludeRegex
     *
     * @return array
     *
     * @psalm-return array<string, array{content: string, fileName: null|string}>
     */
    private static function getCode(
        string $pathOrCode,
        array $pathExcludeRegex = []
    ): array {
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

            foreach ($pathExcludeRegex as $regex) {
                if (\preg_match($regex, $path)) {
                    continue 2;
                }
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
    private static function mergeInheritdocData(
        \voku\SimplePhpParser\Model\PHPClass $class,
        array $classes,
        array $interfaces
    ): void {
        /** @noinspection AlterInForeachInspection */
        foreach ($class->properties as &$property) {
            if (!$property->is_inheritdoc) {
                continue;
            }

            if (!isset($classes[$class->parentClass])) {
                continue;
            }

            if (!isset($classes[$class->parentClass]->properties[$property->name])) {
                continue;
            }

            $parentMethod = $classes[$class->parentClass]->properties[$property->name];

            /** @noinspection AlterInForeachInspection */
            /** @psalm-suppress RawObjectIteration */
            foreach ($property as $key => &$value) {
                if (
                    $value === null
                    &&
                    $parentMethod->{$key} !== null
                    &&
                    \stripos($key, 'typeFromPhpDoc') !== false
                ) {
                    $value = $parentMethod->{$key};
                }
            }
        }

        foreach ($class->methods as &$method) {
            if (!$method->is_inheritdoc) {
                continue;
            }

            foreach ($class->interfaces as $interfaceStr) {
                if (!isset($interfaces[$interfaceStr])) {
                    continue;
                }

                if (!isset($interfaces[$interfaceStr]->methods[$method->name])) {
                    continue;
                }

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

                    if ($key !== 'parameters') {
                        continue;
                    }

                    /** @noinspection AlterInForeachInspection */
                    foreach ($value as $parameterName => &$parameter) {
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

            if (!isset($classes[$class->parentClass])) {
                continue;
            }

            if (!isset($classes[$class->parentClass]->methods[$method->name])) {
                continue;
            }

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

                if ($key !== 'parameters') {
                    continue;
                }

                foreach ($value as $parameterName => &$parameter) {
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
