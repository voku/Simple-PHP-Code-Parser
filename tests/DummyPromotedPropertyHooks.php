<?php

declare(strict_types=1);

namespace voku\tests;

use Attribute;

/**
 * Test fixture for promoted properties with PHP 8.4 property hooks and asymmetric visibility.
 *
 * This file is parsed from disk, not autoloaded, because PHP < 8.4 cannot compile it.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class DummyPromotedPropertyAttribute
{
    public function __construct(
        public string $name = ''
    ) {
    }
}

class DummyPromotedPropertyHooks
{
    public function __construct(
        #[DummyPromotedPropertyAttribute(name: 'name')]
        final public private(set) string $name {
            get => $this->name;
            set(string $value) {
                $this->name = trim($value);
            }
        },
        public protected(set) int $age = 0,
        #[DummyPromotedPropertyAttribute(name: 'id')]
        public readonly ?string $id = null,
    ) {
    }
}
