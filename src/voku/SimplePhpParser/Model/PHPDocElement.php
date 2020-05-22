<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

use phpDocumentor\Reflection\DocBlock\Tag;
use PhpParser\Node;
use voku\SimplePhpParser\Parsers\Helper\DocFactoryProvider;

trait PHPDocElement
{
    /**
     * @var Tag[]
     */
    public $linkTags = [];

    /**
     * @var Tag[]
     */
    public $seeTags = [];

    /**
     * @var Tag[]
     */
    public $sinceTags = [];

    /**
     * @var Tag[]
     */
    public $deprecatedTags = [];

    /**
     * @var Tag[]
     */
    public $metaTags = [];

    /**
     * @var Tag[]
     */
    public $internalTags = [];

    /**
     * @var Tag[]
     */
    public $removedTags = [];

    /**
     * @var string[]
     */
    public $tagNames = [];

    /**
     * @var bool
     */
    public $hasLinkTag = false;

    /**
     * @var bool
     */
    public $hasSeeTag = false;

    /**
     * @var bool
     */
    public $hasSinceTag = false;

    /**
     * @var bool
     */
    public $hasDeprecatedTag = false;

    /**
     * @var bool
     */
    public $hasMetaTag = false;

    /**
     * @var bool
     */
    public $hasInternalTag = false;

    /**
     * @var bool
     */
    public $hasRemovedTag = false;

    /**
     * @param Node $node
     *
     * @return void
     */
    protected function collectTags(Node $node): void
    {
        $docComment = $node->getDocComment();
        if ($docComment !== null) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create($docComment->getText());

                $tags = $phpDoc->getTags();
                foreach ($tags as $tag) {
                    $this->tagNames[] = $tag->getName();
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
                $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
            }
        }
    }
}
