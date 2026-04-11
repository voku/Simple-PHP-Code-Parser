<?php

declare(strict_types=1);

namespace voku\tests;

use Attribute;
use DateTimeImmutable;

#[Attribute(Attribute::TARGET_CLASS)]
class DummyCombinedClassAttribute
{
    public function __construct(
        public string $label = ''
    ) {
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class DummyCombinedPropertyAttribute
{
    public function __construct(
        public string $source = ''
    ) {
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
class DummyCombinedMethodAttribute
{
    public function __construct(
        public string $label = ''
    ) {
    }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class DummyCombinedParameterAttribute
{
    public function __construct(
        public string $label = ''
    ) {
    }
}

final class DummyCombinedDependency
{
}

#[DummyCombinedClassAttribute(label: 'combined')]
final class DummyCombinedSources
{
    /**
     * @var class-string<DummyCombinedDependency>
     */
    #[DummyCombinedPropertyAttribute(source: 'reflection')]
    public string $dependencyClass = DummyCombinedDependency::class;

    /**
     * Build a payload snapshot.
     *
     * Collects native types, advanced phpDoc types and reflection metadata together.
     *
     * @param array{status: string, retries: int|float} $payload
     * @param callable(string): string $formatter
     *
     * @return array{status: string, retries: int|float}
     */
    #[DummyCombinedMethodAttribute(label: 'method')]
    public function buildSnapshot(
        #[DummyCombinedParameterAttribute(label: 'payload')] array $payload,
        #[DummyCombinedParameterAttribute(label: 'formatter')] callable $formatter,
        bool $withMeta = true
    ): array {
        return [
            'status' => $formatter($payload['status']),
            'retries' => $payload['retries'],
        ];
    }

    public function freeze(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at;
    }
}
