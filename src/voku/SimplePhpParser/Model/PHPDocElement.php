<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use PhpParser\Node;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;

trait PHPDocElement
{
    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $linkTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $seeTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $sinceTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $deprecatedTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $metaTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $internalTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public array $removedTags = [];

    /**
     * @var string[]
     *
     * @phpstan-var array<string, string>
     */
    public array $tagNames = [];

    public bool $hasLinkTag = false;

    public bool $hasSeeTag = false;

    public bool $hasSinceTag = false;

    public bool $hasDeprecatedTag = false;

    public bool $hasMetaTag = false;

    /**
     * @var bool
     */
    public bool $hasInternalTag = false;

    public bool $hasRemovedTag = false;

    protected function collectTags(Node $node): void
    {
        $docComment = $node->getDocComment();
        if ($docComment) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create((string)$docComment->getText());

                $tags = $phpDoc->getTags();
                foreach ($tags as $tag) {
                    $this->tagNames[$tag->getName()] = $tag->render();
                }

                $this->linkTags = $phpDoc->getTagsByName('link');
                $this->seeTags = $phpDoc->getTagsByName('see');
                $this->sinceTags = $phpDoc->getTagsByName('since');
                $this->deprecatedTags = $phpDoc->getTagsByName('deprecated');
                $this->metaTags = $phpDoc->getTagsByName('meta');
                $this->internalTags = $phpDoc->getTagsByName('internal');
                $this->removedTags = $phpDoc->getTagsByName('removed');

                $this->hasLinkTag = $phpDoc->hasTag('link');
                $this->hasSeeTag = $phpDoc->hasTag('see');
                $this->hasSinceTag = $phpDoc->hasTag('since');
                $this->hasDeprecatedTag = $phpDoc->hasTag('deprecated');
                $this->hasMetaTag = $phpDoc->hasTag('meta');
                $this->hasInternalTag = $phpDoc->hasTag('internal');
                $this->hasRemovedTag = $phpDoc->hasTag('removed');
            } catch (\Exception $e) {
                $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
                $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
            }
        }
    }
}
