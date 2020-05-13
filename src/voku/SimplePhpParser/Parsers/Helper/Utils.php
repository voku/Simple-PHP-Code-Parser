<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

final class Utils
{
    /**
     * @param array $arr
     * @param bool  $group
     *
     * @return array
     */
    public static function flattenArray(array $arr, bool $group): array
    {
        return \iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($arr)), $group);
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
     *
     * @return array{parsedParamTagStr: string, variableName: null|string}
     */
    public static function splitTypeAndVariable(
        \phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
    ): array {
        $parsedParamTagStr = $parsedParamTag . '';
        $variableName = null;

        if (\strpos($parsedParamTagStr, '$') !== false) {
            $variableName = \mb_substr($parsedParamTagStr, (int) \mb_strpos($parsedParamTagStr, '$'));
            $parsedParamTagStr = \str_replace(
                $variableName,
                '',
                $parsedParamTagStr
            );
        }

        // clean-up
        if ($variableName) {
            $variableName = \str_replace('$', '', $variableName);
        }

        $parsedParamTagStr = \trim($parsedParamTagStr);

        return [
            'parsedParamTagStr' => $parsedParamTagStr,
            'variableName'      => $variableName,
        ];
    }

    /**
     * @param \phpDocumentor\Reflection\Type|null $type
     *
     * @return string|string[]
     */
    public static function parseDocTypeObject($type)
    {
        if ($type instanceof \phpDocumentor\Reflection\Types\Object_) {
            $tmpObject = (string) $type->getFqsen();
            if ($tmpObject) {
                return $tmpObject;
            }

            return 'object';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Compound) {
            $types = [];
            foreach ($type as $subType) {
                $types[] = self::parseDocTypeObject($subType);
            }

            return $types;
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Array_) {
            $valueTypeTmp = $type->getValueType() . '';
            if ($valueTypeTmp !== 'mixed') {
                if (\strpos($valueTypeTmp, '|') !== false) {
                    $valueTypeTmpExploded = \explode('|', $valueTypeTmp);
                    $valueTypeTmp = '';
                    foreach ($valueTypeTmpExploded as $valueTypeTmpExplodedInner) {
                        $valueTypeTmp .= $valueTypeTmpExplodedInner . '[]|';
                    }

                    return \rtrim($valueTypeTmp, '|');
                }

                return $valueTypeTmp . '[]';
            }

            return 'array';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Null_) {
            return 'null';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Mixed_) {
            return 'mixed';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Scalar) {
            return 'string|int|float|bool';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\BooleanTrue) {
            return 'true';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\BooleanFalse) {
            return 'false';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Boolean) {
            return 'bool';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Callable_) {
            return 'callable';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Float_) {
            return 'float';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\String_) {
            return 'string';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Integer) {
            return 'int';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Void_) {
            return 'void';
        }

        if ($type instanceof \phpDocumentor\Reflection\Types\Resource_) {
            return 'resource';
        }

        return $type . '';

        // throw new \Exception('Unhandled PhpDoc type: ' . get_class($type));
    }

    /**
     * Factory method for easy instantiation.
     *
     * @param string[] $additionalTags
     *
     * @psalm-param array<string, class-string<\phpDocumentor\Reflection\DocBlock\Tag>> $additionalTags
     *
     * @return \phpDocumentor\Reflection\DocBlockFactory
     */
    public static function createDocBlockInstance(array $additionalTags = []): \phpDocumentor\Reflection\DocBlockFactory
    {
        $fqsenResolver = new \phpDocumentor\Reflection\FqsenResolver();
        $tagFactory = new \phpDocumentor\Reflection\DocBlock\StandardTagFactory($fqsenResolver);
        $descriptionFactory = new \phpDocumentor\Reflection\DocBlock\DescriptionFactory($tagFactory);
        $typeResolver = new \phpDocumentor\Reflection\TypeResolver($fqsenResolver);

        $typeResolver->addKeyword('array[]', \phpDocumentor\Reflection\Types\ArrayArray::class);
        $typeResolver->addKeyword('float[]', \phpDocumentor\Reflection\Types\ArrayFloat::class);
        $typeResolver->addKeyword('int[]', \phpDocumentor\Reflection\Types\ArrayInt::class);
        $typeResolver->addKeyword('string[]', \phpDocumentor\Reflection\Types\ArrayString::class);
        $typeResolver->addKeyword('false', \phpDocumentor\Reflection\Types\BooleanFalse::class);
        $typeResolver->addKeyword('true', \phpDocumentor\Reflection\Types\BooleanTrue::class);

        $tagFactory->addService($descriptionFactory);
        $tagFactory->addService($typeResolver);

        $docBlockFactory = new \phpDocumentor\Reflection\DocBlockFactory($descriptionFactory, $tagFactory);
        foreach ($additionalTags as $tagName => $tagHandler) {
            $docBlockFactory->registerTagHandler($tagName, $tagHandler);
        }

        return $docBlockFactory;
    }
}
