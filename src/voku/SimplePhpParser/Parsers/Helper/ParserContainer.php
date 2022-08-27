<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;
use voku\SimplePhpParser\Model\PHPTrait;

class ParserContainer
{
    /**
     * @var \voku\SimplePhpParser\Model\PHPConst[]
     *
     * @phpstan-var array<string, PHPConst>
     */
    private $constants = [];

    /**
     * @var \voku\SimplePhpParser\Model\PHPFunction[]
     *
     * @phpstan-var array<string, PHPFunction>
     */
    private $functions = [];

    /**
     * @var \voku\SimplePhpParser\Model\PHPClass[]
     *
     * @phpstan-var array<string, PHPClass>
     */
    private $classes = [];

    /**
     * @var \voku\SimplePhpParser\Model\PHPTrait[]
     *
     * @phpstan-var array<string, PHPTrait>
     */
    private $traits = [];

    /**
     * @var \voku\SimplePhpParser\Model\PHPInterface[]
     *
     * @phpstan-var array<string, PHPInterface>
     */
    private $interfaces = [];

    /**
     * @var string[]
     */
    private $parse_errors = [];

    /**
     * @return \voku\SimplePhpParser\Model\PHPConst[]
     */
    public function getConstants(): array
    {
        return $this->constants;
    }

    /**
     * @return string[]
     */
    public function getParseErrors(): array
    {
        return $this->parse_errors;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPConst $constant
     *
     * @return void
     */
    public function addConstant(PHPConst $constant): void
    {
        $this->constants[$constant->name] = $constant;
    }

    /**
     * @return \voku\SimplePhpParser\Model\PHPFunction[]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * @param bool $skipDeprecatedFunctions
     * @param bool $skipFunctionsWithLeadingUnderscore
     *
     * @return array<mixed>
     *
     * @psalm-return array<string, array{
     *     fullDescription: string,
     *     line: null|int,
     *     file: null|string,
     *     error: string,
     *     is_deprecated: bool,
     *     is_meta: bool,
     *     is_internal: bool,
     *     is_removed: bool,
     *     paramsTypes: array<string, array{
     *         type?: null|string,
     *         typeFromPhpDoc?: null|string,
     *         typeFromPhpDocExtended?: null|string,
     *         typeFromPhpDocSimple?: null|string,
     *         typeFromPhpDocMaybeWithComment?: null|string,
     *         typeFromDefaultValue?: null|string
     *     }>,
     *     returnTypes: array{
     *         type: null|string,
     *         typeFromPhpDoc: null|string,
     *         typeFromPhpDocExtended: null|string,
     *         typeFromPhpDocSimple: null|string,
     *         typeFromPhpDocMaybeWithComment: null|string
     *     },
     *     paramsPhpDocRaw: array<string, null|string>,
     *     returnPhpDocRaw: null|string
     *  }>
     */
    public function getFunctionsInfo(
        bool $skipDeprecatedFunctions = false,
        bool $skipFunctionsWithLeadingUnderscore = false
    ): array {
        // init
        $allInfo = [];

        foreach ($this->functions as $function) {
            if ($skipDeprecatedFunctions && $function->hasDeprecatedTag) {
                continue;
            }

            if ($skipFunctionsWithLeadingUnderscore && \strpos($function->name, '_') === 0) {
                continue;
            }

            $paramsTypes = [];
            foreach ($function->parameters as $tagParam) {
                $paramsTypes[$tagParam->name]['type'] = $tagParam->type;
                $paramsTypes[$tagParam->name]['typeFromPhpDocMaybeWithComment'] = $tagParam->typeFromPhpDocMaybeWithComment;
                $paramsTypes[$tagParam->name]['typeFromPhpDoc'] = $tagParam->typeFromPhpDoc;
                $paramsTypes[$tagParam->name]['typeFromPhpDocSimple'] = $tagParam->typeFromPhpDocSimple;
                $paramsTypes[$tagParam->name]['typeFromPhpDocExtended'] = $tagParam->typeFromPhpDocExtended;
                $paramsTypes[$tagParam->name]['typeFromDefaultValue'] = $tagParam->typeFromDefaultValue;
            }

            $returnTypes = [];
            $returnTypes['type'] = $function->returnType;
            $returnTypes['typeFromPhpDocMaybeWithComment'] = $function->returnTypeFromPhpDocMaybeWithComment;
            $returnTypes['typeFromPhpDoc'] = $function->returnTypeFromPhpDoc;
            $returnTypes['typeFromPhpDocSimple'] = $function->returnTypeFromPhpDocSimple;
            $returnTypes['typeFromPhpDocExtended'] = $function->returnTypeFromPhpDocExtended;

            $paramsPhpDocRaw = [];
            foreach ($function->parameters as $tagParam) {
                $paramsPhpDocRaw[$tagParam->name] = $tagParam->phpDocRaw;
            }

            $infoTmp = [];
            $infoTmp['fullDescription'] = \trim($function->summary . "\n\n" . $function->description);
            $infoTmp['paramsTypes'] = $paramsTypes;
            $infoTmp['returnTypes'] = $returnTypes;
            $infoTmp['paramsPhpDocRaw'] = $paramsPhpDocRaw;
            $infoTmp['returnPhpDocRaw'] = $function->returnPhpDocRaw;
            $infoTmp['line'] = $function->line;
            $infoTmp['file'] = $function->file;
            $infoTmp['error'] = \implode("\n", $function->parseError);
            foreach ($function->parameters as $parameter) {
                $infoTmp['error'] .= ($infoTmp['error'] ? "\n" : '') . \implode("\n", $parameter->parseError);
            }
            $infoTmp['is_deprecated'] = $function->hasDeprecatedTag;
            $infoTmp['is_meta'] = $function->hasMetaTag;
            $infoTmp['is_internal'] = $function->hasInternalTag;
            $infoTmp['is_removed'] = $function->hasRemovedTag;

            $allInfo[$function->name] = $infoTmp;
        }

        \asort($allInfo);

        return $allInfo;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPFunction $function
     *
     * @return void
     */
    public function addFunction(PHPFunction $function): void
    {
        $this->functions[$function->name] = $function;
    }

    /**
     * @param string $name
     *
     * @return \voku\SimplePhpParser\Model\PHPClass|null
     */
    public function getClass(string $name): ?PHPClass
    {
        return $this->classes[$name] ?? null;
    }

    /**
     * @return \voku\SimplePhpParser\Model\PHPClass[]
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @return \voku\SimplePhpParser\Model\PHPClass[]
     */
    public function &getClassesByReference(): array
    {
        return $this->classes;
    }

    /**
     * @param array<string, \voku\SimplePhpParser\Model\PHPInterface> $interfaces
     *
     * @return void
     */
    public function setInterfaces($interfaces): void
    {
        foreach ($interfaces as $name => $interface) {
            $this->interfaces[$name] = $interface;
        }
    }

    /**
     * @param array<string, \voku\SimplePhpParser\Model\PHPConst> $constants
     *
     * @return void
     */
    public function setConstants($constants): void
    {
        foreach ($constants as $name => $constant) {
            $this->constants[$name] = $constant;
        }
    }

    /**
     * @param array<string, \voku\SimplePhpParser\Model\PHPFunction> $functions
     *
     * @return void
     */
    public function setFunctions($functions): void
    {
        foreach ($functions as $name => $function) {
            $this->functions[$name] = $function;
        }
    }

    /**
     * @param array<string, \voku\SimplePhpParser\Model\PHPClass> $classes
     *
     * @return void
     */
    public function setClasses($classes): void
    {
        foreach ($classes as $className => $class) {
            $this->classes[$className] = $class;
        }
    }

    /**
     * @param array<string, \voku\SimplePhpParser\Model\PHPTrait> $traits
     *
     * @return void
     */
    public function setTraits($traits): void
    {
        foreach ($traits as $traitName => $trait) {
            $this->traits[$traitName] = $trait;
        }
    }

    public function addException(\Exception $exception): void
    {
        $this->parse_errors[] = $exception->getFile() . ':' . $exception->getLine() . ' | ' . $exception->getMessage();
    }

    public function setParseError(ParserErrorHandler $error): void
    {
        foreach ($error->getErrors() as $errorInner) {
            $this->parse_errors[] = $errorInner->getFile() . ':' . $errorInner->getLine() . ' | ' . $errorInner->getMessage();
        }
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPClass $class
     *
     * @return void
     */
    public function addClass(PHPClass $class): void
    {
        $this->classes[$class->name ?: \md5(\serialize($class))] = $class;
    }

    /**
     * @param string $name
     *
     * @return \voku\SimplePhpParser\Model\PHPTrait|null
     */
    public function getTrait(string $name): ?PHPTrait
    {
        return $this->traits[$name] ?? null;
    }

    /**
     * @return \voku\SimplePhpParser\Model\PHPTrait[]
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPTrait $trait
     *
     * @return void
     */
    public function addTrait(PHPTrait $trait): void
    {
        $this->traits[$trait->name ?: \md5(\serialize($trait))] = $trait;
    }

    /**
     * @param string $name
     *
     * @return \voku\SimplePhpParser\Model\PHPInterface|null
     */
    public function getInterface(string $name): ?PHPInterface
    {
        return $this->interfaces[$name] ?? null;
    }

    /**
     * @return \voku\SimplePhpParser\Model\PHPInterface[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * @param \voku\SimplePhpParser\Model\PHPInterface $interface
     *
     * @return void
     */
    public function addInterface(PHPInterface $interface): void
    {
        $this->interfaces[$interface->name ?: \md5(\serialize($interface))] = $interface;
    }
}
