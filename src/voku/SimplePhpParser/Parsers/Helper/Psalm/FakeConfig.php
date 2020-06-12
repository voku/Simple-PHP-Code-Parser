<?php declare(strict_types=1);
namespace voku\SimplePhpParser\Parsers\Helper\Psalm;

use Psalm\Config;

class FakeConfig extends Config
{
    /**
     * @psalm-suppress PossiblyNullPropertyAssignmentValue because cache_directory isn't strictly nullable
     */
    public function __construct()
    {
        parent::__construct();

        $this->throw_exception = true;
        $this->use_docblock_types = true;
        $this->level = 1;
        $this->cache_directory = '';

        $this->base_dir = __DIR__;

        $this->collectPredefinedConstants();
        $this->collectPredefinedFunctions();
    }

    /**
     * @param string $fq_classlike_name
     *
     * @return false
     *
     * @psalm-suppress LessSpecificImplementedReturnType
     */
    public function getComposerFilePathForClassLike($fq_classlike_name)
    {
        return false;
    }

    /**
     * @return array
     *
     * @psalm-suppress LessSpecificImplementedReturnType
     */
    public function getProjectDirectories()
    {
        return [];
    }
}
