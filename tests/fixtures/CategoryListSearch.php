<?php

namespace Plenty\Modules\Webshop\ItemSearch\SearchPresets;

/** Mirrors the two platform factory operations used by the ShopBuilder list. */
class CategoryItems
{
    public $options;
    public $fields;

    public static function getSearchFactory($options)
    {
        $factory = new self();
        $factory->options = $options;
        return $factory;
    }

    public function withResultFields($fields)
    {
        $this->fields = $fields;
        return $this;
    }
}
