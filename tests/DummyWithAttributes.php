<?php

declare(strict_types=1);

namespace voku\tests;

/**
 * Test fixture for PHP 8.0+ attributes on classes, methods, properties, parameters, and constants.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DummyAttribute
{
    public function __construct(
        public string $name = '',
        public int $priority = 0
    ) {
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class DummyMethodAttribute
{
    public function __construct(
        public string $route = ''
    ) {
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DummyPropertyAttribute
{
    public function __construct(
        public bool $required = false
    ) {
    }
}

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class DummyParameterAttribute
{
    public function __construct(
        public string $type = ''
    ) {
    }
}

#[DummyAttribute(name: 'TestClass', priority: 1)]
class DummyWithAttributes
{
    #[DummyPropertyAttribute(required: true)]
    public string $name = '';

    #[DummyPropertyAttribute]
    public int $age = 0;

    public const MY_CONST = 42;

    #[DummyMethodAttribute(route: '/api/test')]
    public function apiMethod(
        #[DummyParameterAttribute(type: 'query')] string $param1,
        int $param2 = 0
    ): string {
        return '';
    }

    public function plainMethod(): void
    {
    }
}
