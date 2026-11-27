<?php
// Fixture stock provider for real Twig and browser renders.
class FixtureWishStock
{
    public $groups = [];
    public $blocked = [];

    public function forPicker(array $ids): array
    {
        $this->groups[] = $ids;
        return array_fill_keys($this->blocked, false) + array_fill_keys($ids, true);
    }
}

function fixtureWishStock($class)
{
    if ($class !== \GuardiansBasic\Services\WishListAvailability::class) {
        throw new RuntimeException('Unexpected fixture service: ' . $class);
    }
    return $GLOBALS['fixtureWishStock'] ??= new FixtureWishStock();
}
