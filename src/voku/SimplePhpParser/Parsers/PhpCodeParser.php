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
use Roave\BetterReflection\Reflection\ReflectionClass;
use SplFileInfo;
use voku\cache\Cache;
use voku\SimplePhpParser\Model\PHPInterface;
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
     * @param string   $className
     * @param string[] $autoloaderProjectPaths
     *
     * @pslam-param class-string $className
     *
     * @throws \Roave\BetterReflection\Reflector\Exception\IdentifierNotFound
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getFromClassName(
        string $className,
        array $autoloaderProjectPaths = []
    ): ParserContainer {
        $reflectionClass = ReflectionClass::createFromName($className);

        return self::getPhpFiles(
            (string) $reflectionClass->getFileName(),
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
        \restore_error_handler();

        $phpCodes = self::getCode(
            $pathOrCode,
            $pathExcludeRegex
        );

        $parserContainer = new ParserContainer();
        $visitor = new ASTVisitor($parserContainer);

        $phpFilePromises = [];
        $processPromiseResponses = [];
        $phpCodesChunks = \array_chunk($phpCodes, Utils::getCpuCores(), true);

        foreach ($phpCodesChunks as $phpCodesChunk) {
            foreach ($phpCodesChunk as $cacheKey => $codeAndFileName) {
                $phpFilePromises[] = Worker\enqueueCallable(
                    [self::class, 'process'],
                    $codeAndFileName['content'],
                    $codeAndFileName['fileName'],
                    $parserContainer,
                    $visitor,
                    $autoloaderProjectPaths
                );
            }

            try {
                $processPromiseResponses += Promise\wait(Promise\all($phpFilePromises));
            } catch (\Amp\Parallel\Worker\TaskFailureThrowable $exception) {
                throw new \Exception($exception . ' | ' . \print_r($exception->getOriginalTrace(), true));
            }
        }
        \assert(\is_array($processPromiseResponses));

        foreach ($processPromiseResponses as $response) {
            if ($response instanceof ParserContainer) {
                $parserContainer->setTraits($response->getTraits());
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

            self::mergeInheritdocData(
                $classTmp,
                $classesTmp,
                $interfaces,
                $parserContainer
            );
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
     * @param string[]                                             $autoloaderProjectPaths
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer|\voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler
     */
    public static function process(
        string $phpCode,
        ?string $fileName,
        ParserContainer $parserContainer,
        ASTVisitor $visitor,
        array $autoloaderProjectPaths
    ) {
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
        \restore_error_handler();

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

        /** @var \PhpParser\Node[]|null $parsedCode */
        $parsedCode = $parser->parse($phpCode, $errorHandler);

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
     * @param string $fileName
     * @param string $cacheKey
     *
     * @return array
     *
     * @psalm-return array{content: string, fileName: string, cacheKey: string}
     *
     * @internal
     */
    public static function file_get_contents_with_cache(string $fileName, string $cacheKey): array
    {
        $content = (string) \file_get_contents($fileName);

        return [
            'content'  => $content,
            'fileName' => $fileName,
            'cacheKey' => $cacheKey,
        ];
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

        $phpFileArray = [];
        foreach ($phpFileIterators as $fileOrCode) {
            $path = $fileOrCode->getRealPath();
            if (!$path) {
                continue;
            }

            if (\substr($path, -\strlen('.php')) !== '.php') {
                continue;
            }

            foreach ($pathExcludeRegex as $regex) {
                if (\preg_match($regex, $path)) {
                    continue 2;
                }
            }

            $cacheKey = 'simple-php-code-parser-' . \md5($path) . '--' . \filemtime($path);
            if ($cache->getCacheIsReady() === true && $cache->existsItem($cacheKey)) {
                $response = $cache->getItem($cacheKey);
                /** @noinspection PhpSillyAssignmentInspection - helper for phpstan */
                /** @psalm-var array{content: string, fileName: string, cacheKey: string} $response */
                $response = $response;

                $phpCodes[$response['cacheKey']]['content'] = $response['content'];
                $phpCodes[$response['cacheKey']]['fileName'] = $response['fileName'];

                continue;
            }

            $phpFileArray[$cacheKey] = $path;
        }

        $phpFilePromiseResponses = [];
        $phpFileArrayChunks = \array_chunk($phpFileArray, Utils::getCpuCores(), true);
        foreach ($phpFileArrayChunks as $phpFileArrayChunk) {
            foreach ($phpFileArrayChunk as $cacheKey => $path) {
                $phpFilePromises[] = Worker\enqueueCallable(
                    [self::class, 'file_get_contents_with_cache'],
                    $path,
                    $cacheKey
                );
            }

            $phpFilePromiseResponses += Promise\wait(Promise\all($phpFilePromises));
        }
        \assert(\is_array($phpFilePromiseResponses));

        foreach ($phpFilePromiseResponses as $response) {
            /** @noinspection PhpSillyAssignmentInspection - helper for phpstan */
            /** @psalm-var array{content: string, fileName: string, cacheKey: string} $response */
            $response = $response;

            $cache->setItem($response['cacheKey'], $response);

            $phpCodes[$response['cacheKey']]['content'] = $response['content'];
            $phpCodes[$response['cacheKey']]['fileName'] = $response['fileName'];
        }

        return $phpCodes;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPClass   $class
     * @param \voku\SimplePhpParser\Model\PHPClass[] $classes
     * @param PHPInterface[]                         $interfaces
     * @param ParserContainer                        $parserContainer
     *
     * @return void
     */
    private static function mergeInheritdocData(
        \voku\SimplePhpParser\Model\PHPClass $class,
        array $classes,
        array $interfaces,
        ParserContainer $parserContainer
    ): void {
        /** @noinspection AlterInForeachInspection */
        foreach ($class->properties as &$property) {
            if (!$class->parentClass) {
                break;
            }

            if (!$property->is_inheritdoc) {
                continue;
            }

            /** @noinspection NotOptimalIfConditionsInspection */
            /** @noinspection ArgumentEqualsDefaultValueInspection */
            if (
                !isset($classes[$class->parentClass])
                &&
                \class_exists($class->parentClass, true)
            ) {
                $reflectionClassTmp = ReflectionClass::createFromName($class->parentClass);
                $classTmp = (new \voku\SimplePhpParser\Model\PHPClass($parserContainer))->readObjectFromBetterReflection($reflectionClassTmp);
                if ($classTmp->name) {
                    $classes[$classTmp->name] = $classTmp;
                }
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

                /** @noinspection NotOptimalIfConditionsInspection */
                /** @noinspection ArgumentEqualsDefaultValueInspection */
                if (
                    !isset($interfaces[$interfaceStr])
                    &&
                    \interface_exists($interfaceStr, true)
                ) {
                    $reflectionInterfaceTmp = ReflectionClass::createFromName($interfaceStr);
                    $interfaceTmp = (new PHPInterface($parserContainer))->readObjectFromBetterReflection($reflectionInterfaceTmp);
                    if ($interfaceTmp->name) {
                        $interfaces[$interfaceTmp->name] = $interfaceTmp;
                    }
                }

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

                    if ($key === 'parameters') {
                        /** @noinspection AlterInForeachInspection */
                        $parameterCounter = 0;
                        foreach ($value as $parameterName => &$parameter) {
                            ++$parameterCounter;

                            \assert($parameter instanceof \voku\SimplePhpParser\Model\PHPParameter);

                            $interfaceMethodParameter = null;
                            $parameterCounterInterface = 0;
                            foreach ($interfaceMethod->parameters as $parameterInterface) {
                                ++$parameterCounterInterface;

                                if ($parameterCounterInterface === $parameterCounter) {
                                    $interfaceMethodParameter = $parameterInterface;
                                }
                            }

                            if (!$interfaceMethodParameter) {
                                continue;
                            }

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

                if ($key === 'parameters') {
                    $parameterCounter = 0;
                    foreach ($value as $parameterName => &$parameter) {
                        ++$parameterCounter;

                        \assert($parameter instanceof \voku\SimplePhpParser\Model\PHPParameter);

                        $parentMethodParameter = null;
                        $parameterCounterParent = 0;
                        foreach ($parentMethod->parameters as $parameterParent) {
                            ++$parameterCounterParent;

                            if ($parameterCounterParent === $parameterCounter) {
                                $parentMethodParameter = $parameterParent;
                            }
                        }

                        if (!$parentMethodParameter) {
                            continue;
                        }

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
