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
use voku\SimplePhpParser\Model\BasePHPElement;
use voku\SimplePhpParser\Model\PHPFileInfo;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Parsers\Helper\ParserContainer;
use voku\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use voku\SimplePhpParser\Parsers\Helper\ParserOptions;
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
     * Fork/process overhead was still a net loss below this boundary in the
     * #128 GitHub Actions proof. Sixteen real parser source files were 1.21x
     * faster while preserving exact normalized output.
     */
    private const PARALLEL_PARSE_MIN_FILES = 16;

    /**
     * Keep worker fan-out bounded even on very large hosts.
     */
    private const PARALLEL_PARSE_MAX_WORKERS = 8;

    /**
     * The #128 threshold sweep only became useful once each worker had enough
     * parse/model work to amortize fork and IPC overhead.
     */
    private const PARALLEL_PARSE_MIN_FILES_PER_WORKER = 4;

    /**
     * @param string              $code
     * @param string[]            $autoloaderProjectPaths
     * @param ParserOptions|null  $options
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getFromString(
        string $code,
        array $autoloaderProjectPaths = [],
        ?ParserOptions $options = null
    ): ParserContainer {
        return self::getPhpFiles(
            $code,
            $autoloaderProjectPaths,
            [],
            [],
            $options
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
     * @param string             $pathOrCode
     * @param string[]           $autoloaderProjectPaths
     * @param string[]           $pathExcludeRegex
     * @param string[]           $fileExtensions
     * @param ParserOptions|null $options                how much of the model is filled in from
     *                                                   the running runtime; reflection-enriched
     *                                                   by default
     *
     * @return \voku\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getPhpFiles(
        string $pathOrCode,
        array $autoloaderProjectPaths = [],
        array $pathExcludeRegex = [],
        array $fileExtensions = [],
        ?ParserOptions $options = null
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

        $options ??= ParserOptions::default();

        $parserContainer = new ParserContainer($options);
        $visitor = new ASTVisitor($parserContainer);

        if (!self::processPhpCodesInParallel($phpCodes, $parserContainer, $options)) {
            foreach ($phpCodes as $codeAndFileName) {
                $response = self::process(
                    $codeAndFileName['content'],
                    $codeAndFileName['fileName'],
                    $parserContainer,
                    $visitor
                );

                if ($response instanceof ParserErrorHandler) {
                    $parserContainer->setParseError($response);
                }
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
     * Parse sufficiently large AST-only inputs in bounded worker processes.
     *
     * Workers stop after parse/name-resolution/file-local model extraction.
     * The caller then performs the existing cross-file interface/inheritdoc
     * finalisation once against the merged canonical ParserContainer.
     *
     * Any unavailable runtime capability or worker/IPC failure leaves the target
     * container untouched and returns false so the canonical sequential path can
     * retry the complete input.
     *
     * @param array<string, array{content: string, fileName: null|string}> $phpCodes
     */
    private static function processPhpCodesInParallel(
        array $phpCodes,
        ParserContainer $parserContainer,
        ParserOptions $options
    ): bool {
        if (
            !$options->parallelParsing
            || $options->reflectionEnrichment
            || \PHP_SAPI !== 'cli'
            || \count($phpCodes) < self::PARALLEL_PARSE_MIN_FILES
            || Utils::getCpuCores() < 2
            || !\function_exists('pcntl_fork')
            || !\function_exists('pcntl_waitpid')
            || !\function_exists('pcntl_wifexited')
            || !\function_exists('pcntl_wexitstatus')
        ) {
            return false;
        }

        $workerCount = \min(
            self::PARALLEL_PARSE_MAX_WORKERS,
            Utils::getCpuCores(),
            \max(
                1,
                \intdiv(\count($phpCodes), self::PARALLEL_PARSE_MIN_FILES_PER_WORKER)
            )
        );
        /** @var int<1, max> $partitionSize */
        $partitionSize = \max(1, (int) \ceil(\count($phpCodes) / $workerCount));
        $partitions = \array_chunk($phpCodes, $partitionSize, true);

        try {
            $suffix = \bin2hex(\random_bytes(8));
        } catch (\Throwable) {
            return false;
        }

        $temporaryDirectory = \rtrim(\sys_get_temp_dir(), \DIRECTORY_SEPARATOR)
            . \DIRECTORY_SEPARATOR
            . 'simple-php-code-parser-'
            . \getmypid()
            . '-'
            . $suffix;

        if (!\mkdir($temporaryDirectory, 0700) && !\is_dir($temporaryDirectory)) {
            return false;
        }

        /**
         * @var list<array{pid: int, resultFile: string}> $workers
         */
        $workers = [];
        $launchFailed = false;

        try {
            foreach ($partitions as $index => $partition) {
                $resultFile = $temporaryDirectory . \DIRECTORY_SEPARATOR . 'worker-' . $index . '.ser';
                $pid = \pcntl_fork();

                if ($pid === -1) {
                    $launchFailed = true;

                    break;
                }

                if ($pid === 0) {
                    try {
                        $workerContainer = new ParserContainer($options);
                        $workerVisitor = new ASTVisitor($workerContainer);
                        $errors = [];

                        foreach ($partition as $codeAndFileName) {
                            $response = self::process(
                                $codeAndFileName['content'],
                                $codeAndFileName['fileName'],
                                $workerContainer,
                                $workerVisitor
                            );

                            if ($response instanceof ParserErrorHandler) {
                                $errors[] = $response;
                            }
                        }

                        $payload = \serialize([
                            'container' => $workerContainer,
                            'errors'    => $errors,
                        ]);
                        $written = \file_put_contents($resultFile, $payload, \LOCK_EX);

                        exit($written === false ? 71 : 0);
                    } catch (\Throwable) {
                        exit(70);
                    }
                }

                $workers[] = [
                    'pid'        => $pid,
                    'resultFile' => $resultFile,
                ];
            }

            $workersSucceeded = !$launchFailed;
            foreach ($workers as $worker) {
                $status = 0;
                $waitedPid = \pcntl_waitpid($worker['pid'], $status);

                if (
                    $waitedPid !== $worker['pid']
                    || !\pcntl_wifexited($status)
                    || \pcntl_wexitstatus($status) !== 0
                ) {
                    $workersSucceeded = false;
                }
            }

            if (!$workersSucceeded || \count($workers) !== \count($partitions)) {
                return false;
            }

            /**
             * @var list<array{
             *     container: ParserContainer,
             *     errors: list<ParserErrorHandler>
             * }> $workerResults
             */
            $workerResults = [];

            foreach ($workers as $worker) {
                $payload = \file_get_contents($worker['resultFile']);
                if (!\is_string($payload) || $payload === '') {
                    return false;
                }

                $decoded = \unserialize($payload, ['allowed_classes' => true]);
                if (
                    !\is_array($decoded)
                    || !isset($decoded['container'], $decoded['errors'])
                    || !$decoded['container'] instanceof ParserContainer
                    || !\is_array($decoded['errors'])
                ) {
                    return false;
                }

                foreach ($decoded['errors'] as $error) {
                    if (!$error instanceof ParserErrorHandler) {
                        return false;
                    }
                }

                /** @var array{container: ParserContainer, errors: list<ParserErrorHandler>} $decoded */
                $workerResults[] = $decoded;
            }

            foreach ($workerResults as $workerResult) {
                $source = $workerResult['container'];

                $parserContainer->setTraits($source->getTraits());
                $parserContainer->setClasses($source->getClasses());
                $parserContainer->setInterfaces($source->getInterfaces());
                $parserContainer->setEnums($source->getEnums());
                $parserContainer->setConstants($source->getConstants());
                $parserContainer->setFunctions($source->getFunctions());

                foreach ($workerResult['errors'] as $error) {
                    $parserContainer->setParseError($error);
                }
            }

            self::rebindParserContainerModels($parserContainer);

            return true;
        } finally {
            foreach ($workers as $worker) {
                if (\is_file($worker['resultFile'])) {
                    \unlink($worker['resultFile']);
                }
            }

            if (\is_dir($temporaryDirectory)) {
                \rmdir($temporaryDirectory);
            }
        }
    }

    /**
     * Worker models reference their worker-local ParserContainer after
     * deserialization. Rebind every nested model to the canonical aggregate
     * container before cross-file finalisation.
     */
    private static function rebindParserContainerModels(ParserContainer $parserContainer): void
    {
        /** @var \SplObjectStorage<BasePHPElement, null> $seen */
        $seen = new \SplObjectStorage();

        foreach ([
            $parserContainer->getTraits(),
            $parserContainer->getClasses(),
            $parserContainer->getInterfaces(),
            $parserContainer->getEnums(),
            $parserContainer->getConstants(),
            $parserContainer->getFunctions(),
        ] as $models) {
            self::rebindParserContainerValue($models, $parserContainer, $seen);
        }
    }

    /**
     * @param mixed $value
     * @param \SplObjectStorage<BasePHPElement, null> $seen
     */
    private static function rebindParserContainerValue(
        mixed $value,
        ParserContainer $parserContainer,
        \SplObjectStorage $seen
    ): void {
        if (\is_array($value)) {
            foreach ($value as $item) {
                self::rebindParserContainerValue($item, $parserContainer, $seen);
            }

            return;
        }

        if (!$value instanceof BasePHPElement || $seen->contains($value)) {
            return;
        }

        $seen->attach($value);
        $value->parserContainer = $parserContainer;

        foreach (\get_object_vars($value) as $property => $propertyValue) {
            if ($property === 'parserContainer') {
                continue;
            }

            self::rebindParserContainerValue($propertyValue, $parserContainer, $seen);
        }
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
                $parserContainer->options()->reflectionEnrichment
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
                    $parserContainer->options()->reflectionEnrichment
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

            if ($class->parentClass === null) {
                continue;
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
