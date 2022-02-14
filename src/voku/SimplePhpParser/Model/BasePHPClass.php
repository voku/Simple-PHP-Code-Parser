<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

abstract class BasePHPClass extends BasePHPElement
{
    use PHPDocElement;

    /**
     * @var array<string, PHPMethod>
     */
    public $methods = [];

    /**
     * @var array<string, PHPProperty>
     */
    public $properties = [];

    /**
     * @var array<string, PHPConst>
     */
    public $constants = [];

    /**
     * @var null|bool
     */
    public $is_final;

    /**
     * @var null|bool
     */
    public $is_abstract;

    /**
     * @var null|bool
     */
    public $is_anonymous;

    /**
     * @var null|bool
     */
    public $is_cloneable;

    /**
     * @var null|bool
     */
    public $is_instantiable;

    /**
     * @var null|bool
     */
    public $is_iterable;
}
