<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPFunction extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var PHPParameter[]
     */
    public array $parameters = [];

    /**
     * PHP 8.0+ attributes on this function.
     *
     * @var PHPAttribute[]
     */
    public array $attributes = [];

    public ?string $returnPhpDocRaw = null;

    public ?string $returnType = null;

    public ?string $returnTypeFromPhpDoc = null;

    public ?string $returnTypeFromPhpDocSimple = null;

    public ?string $returnTypeFromPhpDocExtended = null;

    public ?string $returnTypeFromPhpDocMaybeWithComment = null;

    public string $summary = '';

    public string $description = '';

    /**
     * @param Function_   $node
     * @param string|null $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        // Extract PHP 8.0+ attributes
        if (!empty($node->attrGroups)) {
            $this->attributes = Utils::extractAttributesFromAstNode($node->attrGroups);
        }

        /** @noinspection NotOptimalIfConditionsInspection */
        if (\function_exists($this->name)) {
            $reflectionFunction = Utils::createFunctionReflectionInstance($this->name);
            $this->readObjectFromReflection($reflectionFunction);
        }

        if ($node->returnType) {
            if (!$this->returnType) {
                $returnTypeStr = Utils::typeNodeToString($node->returnType);
                if ($returnTypeStr !== null) {
                    $this->returnType = $returnTypeStr;
                }
            }

            if ($node->returnType instanceof \PhpParser\Node\NullableType) {
                if ($this->returnType && $this->returnType !== 'null' && \strpos($this->returnType, 'null|') !== 0) {
                    $this->returnType = 'null|' . $this->returnType;
                } elseif (!$this->returnType) {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $docComment = $node->getDocComment();
        if ($docComment) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create($docComment->getText());
                $this->summary = $phpDoc->getSummary();
                $this->description = (string) $phpDoc->getDescription();
            } catch (\Exception $e) {
                $tmpErrorMessage = \sprintf(
                    '%s:%s | %s',
                    $this->name,
                    $this->line ?? '?',
                    \print_r($e->getMessage(), true)
                );
                $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
            }
        }

        foreach ($node->getParams() as $parameter) {
            $parameterVar = $parameter->var;
            if ($parameterVar instanceof \PhpParser\Node\Expr\Error) {
                $this->parseError[] = \sprintf(
                    '%s:%s | maybe at this position an expression is required',
                    $this->line ?? '?',
                    $this->pos ?? ''
                );

                return $this;
            }

            $paramNameTmp = $parameterVar->name;
            \assert(\is_string($paramNameTmp));

            if (isset($this->parameters[$paramNameTmp])) {
                $this->parameters[$paramNameTmp] = $this->parameters[$paramNameTmp]->readObjectFromPhpNode($parameter, $node);
            } else {
                $this->parameters[$paramNameTmp] = (new PHPParameter($this->parserContainer))->readObjectFromPhpNode($parameter, $node);
            }
        }

        $this->collectTags($node);

        $docComment = $node->getDocComment();
        if ($docComment) {
            $this->readPhpDoc($docComment);
        }

        return $this;
    }

    /**
     * @param ReflectionFunction $function
     *
     * @return $this
     */
    public function readObjectFromReflection($function): self
    {
        $this->name = $function->getName();

        // Extract PHP 8.0+ attributes
        $this->attributes = Utils::extractAttributesFromReflection($function);

        if (!$this->line) {
            $lineTmp = $function->getStartLine();
            if ($lineTmp !== false) {
                $this->line = $lineTmp;
            }
        }

        $file = $function->getFileName();
        if ($file) {
            $this->file = $file;
        }

        $returnType = $function->getReturnType();
        if ($returnType !== null) {
            if (\method_exists($returnType, 'getName')) {
                $this->returnType = $returnType->getName();
            } else {
                $this->returnType = $returnType . '';
            }

            if ($returnType->allowsNull()) {
                if ($this->returnType && $this->returnType !== 'null' && \strpos($this->returnType, 'null|') !== 0) {
                    $this->returnType = 'null|' . $this->returnType;
                } elseif (!$this->returnType) {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $docComment = $function->getDocComment();
        if ($docComment) {
            $this->readPhpDoc($docComment);
        }

        if (!$this->returnTypeFromPhpDoc) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create((string)$function->getDocComment());
                $returnTypeTmp = $phpDoc->getTagsByName('return');
                if (
                    \count($returnTypeTmp) === 1
                    &&
                    $returnTypeTmp[0] instanceof \phpDocumentor\Reflection\DocBlock\Tags\Return_
                ) {
                    $this->returnTypeFromPhpDoc = Utils::parseDocTypeObject($returnTypeTmp[0]->getType());
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        foreach ($function->getParameters() as $parameter) {
            $param = (new PHPParameter($this->parserContainer))->readObjectFromReflection($parameter);
            $this->parameters[$param->name] = $param;
        }

        $docComment = $function->getDocComment();
        if ($docComment) {
            $this->readPhpDoc($docComment);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getReturnType(): ?string
    {
        if ($this->returnTypeFromPhpDocExtended) {
            return $this->returnTypeFromPhpDocExtended;
        }

        if ($this->returnType) {
            return $this->returnType;
        }

        if ($this->returnTypeFromPhpDocSimple) {
            return $this->returnTypeFromPhpDocSimple;
        }

        return null;
    }

    /**
     * @param Doc|string $doc
     */
    protected function readPhpDoc($doc): void
    {
        if ($doc instanceof Doc) {
            $docComment = $doc->getText();
        } else {
            $docComment = $doc;
        }
        if ($docComment === '') {
            return;
        }

        try {
            $phpDoc = DocFactoryProvider::getDocFactory()->create($docComment);

            $parsedReturnTag = $phpDoc->getTagsByName('return');

            if (!empty($parsedReturnTag)) {
                /** @var Return_ $parsedReturnTagReturn */
                $parsedReturnTagReturn = $parsedReturnTag[0];

                if ($parsedReturnTagReturn instanceof Return_) {
                    $this->returnTypeFromPhpDocMaybeWithComment = \trim((string) $parsedReturnTagReturn);

                    $type = $parsedReturnTagReturn->getType();

                    $this->returnTypeFromPhpDoc = Utils::normalizePhpType(\ltrim((string) $type, '\\'));

                    $typeTmp = Utils::parseDocTypeObject($type);
                    if ($typeTmp !== '') {
                        $this->returnTypeFromPhpDocSimple = $typeTmp;
                    }
                }

                $this->returnPhpDocRaw = (string) $parsedReturnTagReturn;
                $this->returnTypeFromPhpDocExtended = Utils::modernPhpdoc((string) $parsedReturnTagReturn);
            }

            $parsedReturnTag = $phpDoc->getTagsByName('psalm-return')
                               + $phpDoc->getTagsByName('phpstan-return');

            if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Generic) {
                $parsedReturnTagReturn = (string) $parsedReturnTag[0];

                $this->returnTypeFromPhpDocExtended = Utils::modernPhpdoc($parsedReturnTagReturn);
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = \sprintf(
                '%s:%s | %s',
                $this->name,
                $this->line ?? '?',
                \print_r($e->getMessage(), true)
            );
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }

        try {
            $this->readPhpDocByTokens($docComment);
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }

    /**
     * @throws \PHPStan\PhpDocParser\Parser\ParserException
     */
    private function readPhpDocByTokens(string $docComment): void
    {
        $tokens = Utils::modernPhpdocTokens($docComment);

        // Track standard (@return) and extended (@phpstan-return / @psalm-return) content separately
        // so that the more specific phpstan/psalm annotation always wins regardless of tag order.
        $returnContent = null;
        $extendedReturnContent = null;
        $currentTarget = null; // 'standard' | 'extended'

        foreach ($tokens->getTokens() as $token) {
            $content = $token[0];

            if ($content === '@return') {
                $currentTarget = 'standard';
                $returnContent = '';
                continue;
            }

            if ($content === '@psalm-return' || $content === '@phpstan-return') {
                $currentTarget = 'extended';
                $extendedReturnContent = '';
                continue;
            }

            // We can stop if we found the end.
            if ($content === '*/') {
                break;
            }

            if ($currentTarget === 'standard') {
                $returnContent .= $content;
            } elseif ($currentTarget === 'extended') {
                $extendedReturnContent .= $content;
            }
        }

        // Prefer @phpstan-return / @psalm-return over plain @return regardless of tag order.
        $bestContent = null;
        if ($extendedReturnContent !== null && \trim($extendedReturnContent) !== '') {
            $bestContent = \trim($extendedReturnContent);
        } elseif ($returnContent !== null && \trim($returnContent) !== '') {
            $bestContent = \trim($returnContent);
        }

        if ($bestContent) {
            if (!$this->returnPhpDocRaw) {
                $this->returnPhpDocRaw = $bestContent;
            }
            try {
                $this->returnTypeFromPhpDocExtended = Utils::modernPhpdoc($bestContent);
            } catch (\PHPStan\PhpDocParser\Parser\ParserException $e) {
                $recoveredType = Utils::recoverBrokenPhpdocType($bestContent);
                if ($recoveredType !== null) {
                    $normalizedRecoveredType = Utils::normalizePhpType($recoveredType);
                    $this->returnTypeFromPhpDoc = $this->returnTypeFromPhpDoc ?? $normalizedRecoveredType;
                    $this->returnTypeFromPhpDocSimple = $this->returnTypeFromPhpDocSimple ?? $normalizedRecoveredType;
                    $this->returnTypeFromPhpDocExtended = $recoveredType;
                }

                $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . $e->getMessage();
                $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
            }
        }
    }
}
