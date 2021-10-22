<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

use FilesystemIterator;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
     * @phpstan-param class-string $className
     *
     * @throws \PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getFromClassName(
        string $className,
        array $autoloaderProjectPaths = []
    ): ParserContainer {
        $reflectionClass = Utils::createClassReflectionInstance($className);

        return self::getPhpFiles(
            (string) $reflectionClass->getFileName(),
            $autoloaderProjectPaths
        );
    }

    /**
     * @param string   $pathOrCode
     * @param string[] $autoloaderProjectPaths
     * @param string[] $pathExcludeRegex
     * @param string[] $fileExtensions
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getPhpFiles(
        string $pathOrCode,
        array $autoloaderProjectPaths = [],
        array $pathExcludeRegex = [],
        array $fileExtensions = []
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
            $pathExcludeRegex,
            $fileExtensions
        );

        $parserContainer = new ParserContainer();
        $visitor = new ASTVisitor($parserContainer);

        $processResults = [];
        $phpCodesChunks = \array_chunk($phpCodes, Utils::getCpuCores(), true);

        foreach ($phpCodesChunks as $phpCodesChunk) {
            foreach ($phpCodesChunk as $codeAndFileName) {
                $processResults[] = self::process(
                    $codeAndFileName['content'],
                    $codeAndFileName['fileName'],
                    $parserContainer,
                    $visitor
                );
            }
        }

        foreach ($processResults as $response) {
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
        foreach ($interfaces as &$interface) {
            $interface->parentInterfaces = $visitor->combineParentInterfaces($interface);
        }
        unset($interface);

        $pathTmp = null;
        if (\is_file($pathOrCode)) {
            $pathTmp = \realpath(\pathinfo($pathOrCode, \PATHINFO_DIRNAME));
        } elseif (\is_dir($pathOrCode)) {
            $pathTmp = \realpath($pathOrCode);
        }

        $classesTmp = &$parserContainer->getClassesByReference();
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
        unset($classTmp);

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
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer|\voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler
     */
    public static function process(
        string $phpCode,
        ?string $fileName,
        ParserContainer $parserContainer,
        ASTVisitor $visitor
    ) {
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
        $traverser->traverse($parsedCode);

        return $parserContainer;
    }

    /**
     * @param string   $pathOrCode
     * @param string[] $pathExcludeRegex
     * @param string[] $fileExtensions
     *
     * @return array
     *
     * @psalm-return array<string, array{content: string, fileName: null|string}>
     */
    private static function getCode(
        string $pathOrCode,
        array $pathExcludeRegex = [],
        array $fileExtensions = []
    ): array {
        // init
        $phpCodes = [];
        /** @var SplFileInfo[] $phpFileIterators */
        $phpFileIterators = [];
        /** @var \React\Promise\PromiseInterface[] $phpFilePromises */
        $phpFilePromises = [];

        // fallback
        if (\count($fileExtensions) === 0) {
            $fileExtensions = ['.php'];
        }

        if (\is_file($pathOrCode)) {
            $phpFileIterators = [new SplFileInfo($pathOrCode)];
        } elseif (\is_dir($pathOrCode)) {
            $phpFileIterators = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pathOrCode, FilesystemIterator::SKIP_DOTS)
            );
        } else {
            $cacheKey = 'simple-php-code-parser-1-' . \md5($pathOrCode);

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

            $fileExtensionFound = false;
            foreach ($fileExtensions as $fileExtension) {
                if (\substr($path, -\strlen($fileExtension)) === $fileExtension) {
                    $fileExtensionFound = true;

                    break;
                }
            }
            if ($fileExtensionFound === false) {
                continue;
            }

            foreach ($pathExcludeRegex as $regex) {
                if (\preg_match($regex, $path)) {
                    continue 2;
                }
            }

            $cacheKey = 'simple-php-code-parser-1-' . \md5($path) . '--' . \filemtime($path);
            if ($cache->getCacheIsReady() === true && $cache->existsItem($cacheKey)) {
                $response = $cache->getItem($cacheKey);
                /** @noinspection PhpSillyAssignmentInspection - helper for phpstan */
                /** @phpstan-var array{content: string, fileName: string, cacheKey: string} $response */
                $response = $response;

                $phpCodes[$response['cacheKey']]['content'] = $response['content'];
                $phpCodes[$response['cacheKey']]['fileName'] = $response['fileName'];

                continue;
            }

            $phpFileArray[$cacheKey] = $path;
        }

        $phpFilePromiseResponses = [[]];
        $phpFileArrayChunks = \array_chunk($phpFileArray, Utils::getCpuCores(), true);
        foreach ($phpFileArrayChunks as $phpFileArrayChunk) {
            $loop = \React\EventLoop\Loop::get();
            $filesystem = \React\Filesystem\Filesystem::create($loop);

            foreach ($phpFileArrayChunk as $cacheKey => $path) {
                $phpFilePromises[] = $filesystem->file($path)->getContents()->then(
                    function ($contents) use ($path, $cacheKey) {
                        return [
                            'content'  => $contents,
                            'fileName' => $path,
                            'cacheKey' => $cacheKey,
                        ];
                    },
                    function ($e) {
                        throw $e;
                    }
                );
            }

            $phpFilePromiseResponses[] = \Clue\React\Block\awaitAll($phpFilePromises);
        }
        $phpFilePromiseResponses = array_merge(...$phpFilePromiseResponses);
        \assert(\is_array($phpFilePromiseResponses));

        foreach ($phpFilePromiseResponses as $response) {
            /** @noinspection PhpSillyAssignmentInspection - helper for phpstan */
            /** @phpstan-var array{content: string, fileName: string, cacheKey: string} $response */
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
                $reflectionClassTmp = Utils::createClassReflectionInstance($class->parentClass);
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
        unset($property);

        foreach ($class->methods as &$method) {
            if (!$method->is_inheritdoc) {
                continue;
            }

            foreach ($class->interfaces as $interfaceStr) {
                if (
                    !isset($interfaces[$interfaceStr])
                    &&
                    \interface_exists($interfaceStr, true)
                ) {
                    $reflectionInterfaceTmp = Utils::createClassReflectionInstance($interfaceStr);
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
                        $parameterCounter = 0;
                        foreach ($value as &$parameter) {
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
                            unset($valueInner);
                        }
                        unset($parameter);
                    }
                }
                unset($value);
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
                    foreach ($value as &$parameter) {
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
