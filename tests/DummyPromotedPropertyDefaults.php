<?php

declare(strict_types=1);

namespace voku\tests;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class DummyPromotedDefaultAttribute
{
    public function __construct(
        public string $name = ''
    ) {
    }
}

class DummyPromotedPropertyDefaults
{
    public function __construct(
        #[DummyPromotedDefaultAttribute(name: 'age')]
        public int $age = 0,
        #[DummyPromotedDefaultAttribute(name: 'id')]
        public readonly ?string $id = null,
    ) {
    }
}
