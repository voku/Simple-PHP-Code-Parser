<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use voku\SimplePhpParser\Model\PHPClass;
use voku\SimplePhpParser\Model\PHPConst;
use voku\SimplePhpParser\Model\PHPFunction;
use voku\SimplePhpParser\Model\PHPInterface;

class ParserContainer
{
    /**
     * @var PHPConst[]
     *
     * @psalm-var array<string, PHPConst>
     */
    private $constants = [];

    /**
     * @var PHPFunction[]
     *
     * @psalm-var array<string, PHPFunction>
     */
    private $functions = [];

    /**
     * @var PHPClass[]
     *
     * @psalm-var array<string, PHPClass>
     */
    private $classes = [];

    /**
     * @var PHPInterface[]
     *
     * @psalm-var array<string, PHPInterface>
     */
    private $interfaces = [];

    /**
     * @var string[]
     */
    private $parse_errors = [];

    /**
     * @return PHPConst[]
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
     * @param PHPConst $constant
     *
     * @return void
     */
    public function addConstant(PHPConst $constant): void
    {
        $this->constants[$constant->name] = $constant;
    }

    /**
     * @return PHPFunction[]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * @param bool $skipDeprecatedMethods
     * @param bool $skipFunctionsWithLeadingUnderscore
     *
     * @return array<mixed>
     *
     * @psalm-return array<string, array{fullDescription: string, line: null|int, error: string, is_deprecated: bool, is_meta: bool, is_internal: bool, is_removed: bool, paramsTypes: array<string, array{type: null|string, typeFromPhpDoc: null|string, typeFromPhpDocPslam: null|string, typeFromPhpDocSimple: null|string, typeMaybeWithComment: null|string, typeFromDefaultValue: null|string}>, returnTypes: array{type: null|string, typeFromPhpDoc: null|string, typeFromPhpDocPslam: null|string, typeFromPhpDocSimple: null|string, typeMaybeWithComment: null|string}}>
     *
     * @psalm-suppress MoreSpecificReturnType or Less ?
     */
    public function getFunctionsInfo(
        bool $skipDeprecatedMethods = false,
        bool $skipFunctionsWithLeadingUnderscore = false
    ): array {
        // init
        $allInfo = [];

        foreach ($this->functions as $function) {
            if ($skipDeprecatedMethods && $function->hasDeprecatedTag) {
                continue;
            }

            if ($skipFunctionsWithLeadingUnderscore && \strpos($function->name, '_') === 0) {
                continue;
            }

            $paramsTypes = [];
            foreach ($function->parameters as $tagParam) {
                $paramsTypes[$tagParam->name]['type'] = $tagParam->type;
                $paramsTypes[$tagParam->name]['typeMaybeWithComment'] = $tagParam->typeMaybeWithComment;
                $paramsTypes[$tagParam->name]['typeFromPhpDoc'] = $tagParam->typeFromPhpDoc;
                $paramsTypes[$tagParam->name]['typeFromPhpDocSimple'] = $tagParam->typeFromPhpDocSimple;
                $paramsTypes[$tagParam->name]['typeFromPhpDocPslam'] = $tagParam->typeFromPhpDocPslam;
                $paramsTypes[$tagParam->name]['typeFromDefaultValue'] = $tagParam->typeFromDefaultValue;
            }

            $returnTypes = [];
            $returnTypes['type'] = $function->returnType;
            $returnTypes['typeMaybeWithComment'] = $function->returnTypeMaybeWithComment;
            $returnTypes['typeFromPhpDoc'] = $function->returnTypeFromPhpDoc;
            $returnTypes['typeFromPhpDocSimple'] = $function->returnTypeFromPhpDocSimple;
            $returnTypes['typeFromPhpDocPslam'] = $function->returnTypeFromPhpDocPslam;

            $infoTmp = [];
            $infoTmp['fullDescription'] = \trim($function->summary . "\n\n" . $function->description);
            $infoTmp['paramsTypes'] = $paramsTypes;
            $infoTmp['returnTypes'] = $returnTypes;
            $infoTmp['line'] = $function->line;
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

        /** @psalm-suppress LessSpecificReturnStatement ? */
        return $allInfo;
    }

    /**
     * @param PHPFunction $function
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
     * @return PHPClass|null
     */
    public function getClass(string $name): ?PHPClass
    {
        return $this->classes[$name] ?? null;
    }

    /**
     * @return PHPClass[]
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @param array<string, PHPInterface> $interfaces
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
     * @param array<string, PHPConst> $constants
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
     * @param array<string, PHPFunction> $functions
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
     * @param array<string, PHPClass> $classes
     *
     * @return void
     */
    public function setClasses($classes): void
    {
        foreach ($classes as $className => $class) {
            $this->classes[$className] = $class;
        }
    }

    public function setParseError(ParserErrorHandler $error): void
    {
        foreach ($error->getErrors() as $errorInner) {
            $this->parse_errors[] = $errorInner->getFile() . ':' . $errorInner->getLine() . ' | ' . $errorInner->getMessage();
        }
    }

    /**
     * @param PHPClass $class
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
     * @return PHPInterface|null
     */
    public function getInterface(string $name): ?PHPInterface
    {
        return $this->interfaces[$name] ?? null;
    }

    /**
     * @return PHPInterface[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * @param PHPInterface $interface
     *
     * @return void
     */
    public function addInterface(PHPInterface $interface): void
    {
        $this->interfaces[$interface->name] = $interface;
    }
}
