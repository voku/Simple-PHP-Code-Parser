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
    public $linkTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public $seeTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public $sinceTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public $deprecatedTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public $metaTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public $internalTags = [];

    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public $removedTags = [];

    /**
     * @var string[]
     *
     * @phpstan-var array<string, string>
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
     * @param \PhpParser\Node $node
     *
     * @return void
     */
    protected function collectTags(Node $node): void
    {
        $docComment = $node->getDocComment();
        if ($docComment) {
            try {
                $phpDoc = DocFactoryProvider::getDocFactory()->create($docComment->getText());

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
