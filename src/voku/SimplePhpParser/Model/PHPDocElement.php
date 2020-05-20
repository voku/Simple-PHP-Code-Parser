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
    public $links = [];

    /**
     * @var Tag[]
     */
    public $see = [];

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
    public $removedTags = [];

    /**
     * @var string[]
     */
    public $tagNames = [];

    /**
     * @var bool
     */
    public $hasInternalMetaTag = false;

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

                $this->links = $phpDoc->getTagsByName('link');
                $this->see = $phpDoc->getTagsByName('see');
                $this->sinceTags = $phpDoc->getTagsByName('since');
                $this->deprecatedTags = $phpDoc->getTagsByName('deprecated');
                $this->removedTags = $phpDoc->getTagsByName('removed');
                $this->hasInternalMetaTag = $phpDoc->hasTag('meta');
            } catch (\Exception $e) {
                $this->parseError .= ($this->line ?? '') . ':' . ($this->pos ?? '') . ' | ' . \print_r($e->getMessage(), true);
            }
        }
    }
}
