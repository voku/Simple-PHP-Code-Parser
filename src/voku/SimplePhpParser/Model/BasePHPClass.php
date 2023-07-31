<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

abstract class BasePHPClass extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var array<string, PHPMethod>
     */
    public array $methods = [];

    /**
     * @var array<string, PHPProperty>
     */
    public array $properties = [];

    /**
     * @var array<string, PHPConst>
     */
    public array $constants = [];

    public ?bool $is_final = null;

    public ?bool $is_abstract = null;

    public ?bool $is_readonly = null;

    public ?bool $is_anonymous = null;

    public ?bool $is_cloneable = null;

    public ?bool $is_instantiable = null;

    public ?bool $is_iterable = null;
}
