<?php

declare(strict_types=1);

namespace voku\SimplePhpParser\Model;

interface PhpProblemType
{
    const FUNCTION_ACCESS = 7;

    const FUNCTION_IS_DEPRECATED = 4;

    const FUNCTION_IS_FINAL = 5;

    const FUNCTION_IS_STATIC = 6;

    const FUNCTION_PARAMETER_MISMATCH = 1;

    const IS_MISSED = 0;

    const PARAMETER_REFERENCE = 10;

    const PARAMETER_TYPE_MISMATCH = 9;

    const PARAMETER_VARARG = 11;

    const WRONG_CONSTANT_VALUE = 3;

    const WRONG_INTERFACE = 8;

    const WRONG_PARENT = 2;
}
