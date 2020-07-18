<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use PhpParser\Node\Stmt\Function_;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use voku\SimplePhpParser\Parsers\Helper\Utils;

class PHPFunction extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var PHPParameter[]
     */
    public $parameters = [];

    /**
     * @var string|null
     */
    public $returnType;

    /**
     * @var string|null
     */
    public $returnTypeFromPhpDoc;

    /**
     * @var string|null
     */
    public $returnTypeFromPhpDocSimple;

    /**
     * @var string|null
     */
    public $returnTypeFromPhpDocPslam;

    /**
     * @var string|null
     */
    public $returnTypeFromPhpDocMaybeWithComment;

    /**
     * @var string
     */
    public $summary = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @param Function_ $node
     * @param null      $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        /** @noinspection NotOptimalIfConditionsInspection */
        if (\function_exists($this->name)) {
            $reflectionFunction = ReflectionFunction::createFromName($this->name);
            $this->readObjectFromBetterReflection($reflectionFunction);
        }

        if (!$this->returnType && $node->returnType) {
            if (\method_exists($node->returnType, 'toString')) {
                $this->returnType = $node->returnType->toString();
            } elseif (\property_exists($node->returnType, 'name')) {
                /** @psalm-suppress UndefinedPropertyFetch - FP? */
                $this->returnType = $node->returnType->name;
            }

            if ($node->returnType instanceof \PhpParser\Node\NullableType) {
                if ($this->returnType && $this->returnType !== 'null') {
                    $this->returnType = 'null|' . $this->returnType;
                } else {
                    $this->returnType = 'null|mixed';
                }
            }
        }

        $docComment = $node->getDocComment();
        if ($docComment) {
            try {
                $phpDoc = Utils::createDocBlockInstance()->create($docComment->getText());
                $this->summary = $phpDoc->getSummary();
                $this->description = (string) $phpDoc->getDescription();
            } catch (\Exception $e) {
                $tmpErrorMessage = sprintf(
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
                $this->parseError[] = sprintf(
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
            $this->readPhpDoc($docComment->getText());
        }

        return $this;
    }

    /**
     * @param ReflectionFunction $function
     *
     * @return $this
     */
    public function readObjectFromBetterReflection($function): self
    {
        $this->name = $function->getName();

        if (!$this->line) {
            $this->line = $function->getStartLine();
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
                if ($this->returnType && $this->returnType !== 'null') {
                    $this->returnType = 'null|' . $this->returnType;
                } else {
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
                $returnTypeTmp = $function->getDocBlockReturnTypes();
                if ($returnTypeTmp) {
                    $this->returnTypeFromPhpDoc = Utils::parseDocTypeObject($returnTypeTmp);
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        foreach ($function->getParameters() as $parameter) {
            $param = (new PHPParameter($this->parserContainer))->readObjectFromBetterReflection($parameter);
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
        if ($this->returnTypeFromPhpDocPslam) {
            return $this->returnTypeFromPhpDocPslam;
        }

        if ($this->returnType) {
            return $this->returnType;
        }

        if ($this->returnTypeFromPhpDocSimple) {
            return $this->returnTypeFromPhpDocSimple;
        }

        return null;
    }

    protected function readPhpDoc(string $docComment): void
    {
        if ($docComment === '') {
            return;
        }

        try {
            $regexIntValues = '/@.*?return\s+(?<intValues>\d[\|\d]*)(?<comment>.*)/ui';
            if (\preg_match($regexIntValues, $docComment, $matchesIntValues)) {
                $this->returnTypeFromPhpDocMaybeWithComment = 'int' . (\trim($matchesIntValues['comment']) ? ' ' . \trim($matchesIntValues['comment']) : '');
                $this->returnTypeFromPhpDoc = 'int';
                $this->returnTypeFromPhpDocSimple = 'int';
                $this->returnTypeFromPhpDocPslam = $matchesIntValues['intValues'];

                return;
            }

            $regexAnd = '/@.*?return\s+(?<type>(?<type1>[\S]+)&(?<type2>[\S]+))(?<comment>.*)/ui';
            if (\preg_match($regexAnd, $docComment, $matchesAndValues)) {
                $this->returnTypeFromPhpDocMaybeWithComment = $matchesAndValues['type'] . (\trim($matchesAndValues['comment']) ? ' ' . \trim($matchesAndValues['comment']) : '');
                $this->returnTypeFromPhpDoc = $matchesAndValues['type1'] . '|' . $matchesAndValues['type2'];
                $this->returnTypeFromPhpDocSimple = $matchesAndValues['type1'] . '|' . $matchesAndValues['type2'];
                $this->returnTypeFromPhpDocPslam = $matchesAndValues['type'];

                return;
            }

            $phpDoc = Utils::createDocBlockInstance()->create($docComment);

            $parsedReturnTag = $phpDoc->getTagsByName('return');

            if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Return_) {
                /** @var Return_ $parsedReturnTagReturn */
                $parsedReturnTagReturn = $parsedReturnTag[0];

                $this->returnTypeFromPhpDocMaybeWithComment = \trim((string) $parsedReturnTagReturn);

                $type = $parsedReturnTagReturn->getType();

                $this->returnTypeFromPhpDoc = Utils::normalizePhpType(\ltrim((string)$type, '\\'));

                $typeTmp = Utils::parseDocTypeObject($type);
                if ($typeTmp !== '') {
                    $this->returnTypeFromPhpDocSimple = $typeTmp;
                }

                if ($this->returnTypeFromPhpDoc) {
                    $this->returnTypeFromPhpDocPslam = Utils::modernPhpdoc($this->returnTypeFromPhpDoc);
                }
            }

            /** @noinspection AdditionOperationOnArraysInspection */
            $parsedReturnTag = $phpDoc->getTagsByName('psalm-return')
                               + $phpDoc->getTagsByName('phpstan-return');

            if (!empty($parsedReturnTag) && $parsedReturnTag[0] instanceof Generic) {
                $parsedReturnTagReturn = (string)$parsedReturnTag[0];

                $this->returnTypeFromPhpDocPslam = Utils::modernPhpdoc($parsedReturnTagReturn);
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = sprintf(
                '%s:%s | %s',
                $this->name,
                $this->line ?? '?',
                \print_r($e->getMessage(), true)
            );
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }
}
