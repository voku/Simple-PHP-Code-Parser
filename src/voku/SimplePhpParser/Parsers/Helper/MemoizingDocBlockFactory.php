<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Parsers\Helper;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Location;
use phpDocumentor\Reflection\Types\Context;

/**
 * Memoizes docblock parsing.
 *
 * The models re-create the same docblock many times (once per element and again for every
 * type-resolution pass), so a typical file asks for the same text several times over.
 * Parsing is the expensive step of model extraction. Return a clone of the cached
 * DocBlock because callers can remove tags from it; the cached template must stay intact.
 *
 * The cache is bounded so a long-running process (a file watcher re-parsing after each
 * edit) cannot grow without limit; it is simply reset when full.
 */
final class MemoizingDocBlockFactory implements DocBlockFactoryInterface
{
    private const MAX_ENTRIES = 2048;

    /** @var array<string, DocBlock> */
    private array $cache = [];

    public function __construct(private readonly DocBlockFactoryInterface $inner)
    {
    }

    /**
     * @param array<string, class-string<\phpDocumentor\Reflection\DocBlock\Tag>> $additionalTags
     */
    public static function createInstance(array $additionalTags = []): DocBlockFactoryInterface
    {
        return new self(DocBlockFactory::createInstance($additionalTags));
    }

    public function create($docblock, ?Context $context = null, ?Location $location = null): DocBlock
    {
        if (!\is_string($docblock) || $location !== null) {
            return $this->inner->create($docblock, $context, $location);
        }

        $key = $docblock;
        if ($context !== null) {
            $key = $context->getNamespace() . "\0" . \serialize($context->getNamespaceAliases()) . "\0" . $docblock;
        }

        if (isset($this->cache[$key])) {
            return clone $this->cache[$key];
        }

        $result = $this->inner->create($docblock, $context);

        if (\count($this->cache) >= self::MAX_ENTRIES) {
            $this->cache = [];
        }

        $this->cache[$key] = $result;

        return clone $result;
    }
}
