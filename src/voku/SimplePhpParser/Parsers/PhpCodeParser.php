<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers;

use FilesystemIterator;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use voku\cache\Cache;
use voku\SimplePhpParser\Model\PHPFileInfo;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\Utils;
use voku\SimplePhpParser\Parsers\Visitors\ASTVisitor;
use voku\SimplePhpParser\Parsers\Visitors\ParentConnector;
use voku\SimplePhpParser\Parsers\Visitors\PhpDocContextConnector;

final class PhpCodeParser
{
    /**
     * @internal
     */
    private const CACHE_KEY_HELPER = 'simple-php-code-parser-v8-';

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
     * Parse PHP source into the names-resolved AST used internally to build
     * the public model layer.
     *
     * The returned nodes retain php-parser's location attributes and have a
     * `parent` attribute. NameResolver also preserves aliases as
     * `originalName` attributes while replacing resolvable names with their
     * fully-qualified form. This is an escape hatch for consumers that need
     * syntax not represented by the compact model layer.
     *
     * @return array<int, \PhpParser\Node>
     *
     * @throws \RuntimeException when the source cannot be parsed
     */
    public static function getAstFromString(string $code): array
    {
        $errorHandler = new ParserErrorHandler();
        $parsedCode = self::parseAst($code, $errorHandler);

        if ($parsedCode === null || $errorHandler->getErrors() !== []) {
            throw new \RuntimeException(self::formatParseErrors($errorHandler));
        }

        self::resolveAst($parsedCode, $errorHandler);

        if ($errorHandler->getErrors() !== []) {
            throw new \RuntimeException(self::formatParseErrors($errorHandler));
        }

        return $parsedCode;
    }

    /**
     * Parse one PHP file into the names-resolved AST used internally to build
     * the public model layer.
     *
     * @return array<int, \PhpParser\Node>
     *
     * @throws \RuntimeException when the file cannot be read or parsed
     */
    public static function getAstFromFile(string $fileName): array
    {
        $code = \file_get_contents($fileName);
        if ($code === false) {
            $lastError = \error_get_last();
            throw new \RuntimeException('Could not read file: ' . $fileName . ($lastError !== null ? ' (' . $lastError['message'] . ')' : ''));
        }

        return self::getAstFromString($code);
    }

    /**
     * Return compact namespace, import, and declare metadata for one source
     * string without forcing callers to walk the raw AST.
     */
    public static function getFileInfoFromString(string $code): PHPFileInfo
    {
        return PHPFileInfo::fromAst(self::getAstFromString($code));
    }

    /**
     * Return compact namespace, import, and declare metadata for one PHP
     * file without forcing callers to walk the raw AST.
     *
     * @throws \RuntimeException when the file cannot be read or parsed
     */
    public static function getFileInfoFromFile(string $fileName): PHPFileInfo
    {
        $code = \file_get_contents($fileName);
        if ($code === false) {
            $lastError = \error_get_last();
            throw new \RuntimeException('Could not read file: ' . $fileName . ($lastError !== null ? ' (' . $lastError['message'] . ')' : ''));
        }

        return PHPFileInfo::fromAst(self::getAstFromString($code), $fileName);
    }

    /**
     * @param string   $className
     * @param string[] $autoloaderProjectPaths
     *
     * @phpstan-param class-string $className
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
        // Push a disposable handler so restore_error_handler() below will only
        // pop this one entry, leaving any pre-existing handlers (e.g. PHPUnit's)
        // intact on the stack.
        \set_error_handler(null);
        try {
            foreach ($autoloaderProjectPaths as $projectPath) {
                if (\file_exists($projectPath) && \is_file($projectPath)) {
                    require_once $projectPath;
                } elseif (\file_exists($projectPath . '/vendor/autoload.php')) {
                    require_once $projectPath . '/vendor/autoload.php';
                } elseif (\file_exists($projectPath . '/../vendor/autoload.php')) {
                    require_once $projectPath . '/../vendor/autoload.php';
                }
            }
        } finally {
            \restore_error_handler();
        }

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
                $parserContainer->setEnums($response->getEnums());
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
        $errorHandler = new ParserErrorHandler();

        $parsedCode = self::parseAst($phpCode, $errorHandler);

        if ($parsedCode === null) {
            return $errorHandler;
        }

        self::resolveAst($parsedCode, $errorHandler);

        $visitor->fileName = $fileName;

        // Pass 2: extract model objects from the already-resolved AST.
        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor($visitor);
        $traverser2->traverse($parsedCode);

        return $parserContainer;
    }

    /**
     * @return array<int, \PhpParser\Node>|null
     */
    private static function parseAst(string $phpCode, ParserErrorHandler $errorHandler): ?array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        return $parser->parse($phpCode, $errorHandler);
    }

    /**
     * @param array<int, \PhpParser\Node> $parsedCode
     */
    private static function resolveAst(array $parsedCode, ParserErrorHandler $errorHandler): void
    {
        $nameResolver = new NameResolver(
            $errorHandler,
            [
                'preserveOriginalNames' => true,
            ]
        );

        // Set parent attributes and fully resolve all names before model
        // extraction. ASTVisitor reads class members eagerly when it enters a
        // class-like node, so a single traversal would resolve their types too
        // late.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnector());
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor(new PhpDocContextConnector());
        $traverser->traverse($parsedCode);
    }

    private static function formatParseErrors(ParserErrorHandler $errorHandler): string
    {
        $messages = [];
        foreach ($errorHandler->getErrors() as $error) {
            $messages[] = $error->getMessage();
        }

        return $messages === [] ? 'Could not parse PHP code.' : \implode("\n", $messages);
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
            $cacheKey = self::CACHE_KEY_HELPER . \md5($pathOrCode);

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

            $cacheKey = self::CACHE_KEY_HELPER . \md5($path) . '--' . \filemtime($path);
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

        foreach ($phpFileArray as $cacheKey => $path) {
            $content = \file_get_contents($path);
            if ($content === false) {
                $lastError = \error_get_last();
                throw new \RuntimeException('Could not read file: ' . $path . ($lastError !== null ? ' (' . $lastError['message'] . ')' : ''));
            }

            $response = [
                'content'  => $content,
                'fileName' => $path,
                'cacheKey' => $cacheKey,
            ];

            @$cache->setItem($cacheKey, $response);

            $phpCodes[$cacheKey]['content'] = $content;
            $phpCodes[$cacheKey]['fileName'] = $path;
        }

        return $phpCodes;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPClass   $class
     * @param \voku\SimplePhpParser\Model\PHPClass[] $classes
     * @param PHPInterface[]                         $interfaces
     * @param ParserContainer                        $parserContainer
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

            if (
                !isset($classes[$class->parentClass])
                &&
                \class_exists($class->parentClass, true)
            ) {
                $reflectionClassTmp = Utils::createClassReflectionInstance($class->parentClass);
                $classTmp = (new \voku\SimplePhpParser\Model\PHPClass($parserContainer))->readObjectFromReflection($reflectionClassTmp);
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
            self::mergeMissingTypeFields($property, $parentMethod);
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
                    $interfaceTmp = (new PHPInterface($parserContainer))->readObjectFromReflection($reflectionInterfaceTmp);
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

                self::mergeMissingTypeFields($method, $interfaceMethod);
                $method->parameters = self::mergeMissingParameterTypeFields($method->parameters, $interfaceMethod->parameters);
            }

            if (!isset($classes[$class->parentClass])) {
                continue;
            }

            if (!isset($classes[$class->parentClass]->methods[$method->name])) {
                continue;
            }

            $parentMethod = $classes[$class->parentClass]->methods[$method->name];

            self::mergeMissingTypeFields($method, $parentMethod);
            $method->parameters = self::mergeMissingParameterTypeFields($method->parameters, $parentMethod->parameters);
        }
    }

    private static function mergeMissingTypeFields(object $target, object $source): void
    {
        foreach (\array_keys(\get_object_vars($target)) as $key) {
            if (\stripos($key, 'type') === false) {
                continue;
            }

            if ($target->{$key} === null && $source->{$key} !== null) {
                $target->{$key} = $source->{$key};
            }
        }
    }

    /**
     * @param array<string, \voku\SimplePhpParser\Model\PHPParameter> $targetParameters
     * @param array<string, \voku\SimplePhpParser\Model\PHPParameter> $sourceParameters
     *
     * @return array<string, \voku\SimplePhpParser\Model\PHPParameter>
     */
    private static function mergeMissingParameterTypeFields(array $targetParameters, array $sourceParameters): array
    {
        $sourceParameters = \array_values($sourceParameters);

        $position = 0;
        foreach ($targetParameters as $parameterName => $parameter) {
            $sourceParameter = $sourceParameters[$position] ?? null;
            ++$position;

            if ($sourceParameter === null) {
                continue;
            }

            self::mergeMissingTypeFields($parameter, $sourceParameter);
            $targetParameters[$parameterName] = $parameter;
        }

        return $targetParameters;
    }
}
