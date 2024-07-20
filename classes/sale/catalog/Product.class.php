<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;


class Product extends \sale\catalog\Product {
    public static function getColumns() {

        return [
            'code_legacy' => [
                'type'              => 'string',
                'description'       => "Old code of the product."
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\ProductModel',
                'description'       => "Product Model of this variant.",
                'required'          => true,
                'onupdate'          => 'sale\catalog\Product::onupdateProductModelId'
            ],

            'pack_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\catalog\PackLine',
                'foreign_field'     => 'parent_product_id',
                'description'       => "Products that are bundled in the pack.",
                'ondetach'          => 'delete'
            ],

            // #todo - deprecate
            'ref_pack_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\catalog\PackLine',
                'foreign_field'     => 'child_product_id',
                'description'       => "Pack lines that relate to the product."
            ],

            'label' => [
                'type'              => 'string',
                'description'       => 'Human readable mnemo for identifying the product. Allows duplicates.',
                'required'          => true,
                'onupdate'          => 'onupdateLabel'
            ],

            'sku' => [
                'type'              => 'string',
                'description'       => "Stock Keeping Unit code for internal reference. Must be unique.",
                'required'          => true,
                'unique'            => true,
                'onupdate'          => 'onupdateSku'
            ],

            'allow_price_adaptation' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if price adaptation can be applied on the variants (or children for packs).',
                'default'           => true,
                'visible'           => ['is_pack', '=', true],
            ],

            'age_range_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\AgeRange',
                'onupdate'          => 'onupdateAgeRangeId',
                'description'       => 'Customers age range the product is intended for.',
                'visible'           => [ ['has_age_range', '=', true] ]
            ]

        ];
    }

    public static function onupdateLabel($om, $ids, $values, $lang) {
        $om->update(self::getType(), $ids, ['name' => null], $lang);
    }

    public static function onupdateSku($om, $ids, $values, $lang) {
        $products = $om->read(self::getType(), $ids, ['prices_ids']);
        if($products > 0 && count($products)) {
            $prices_ids = [];
            foreach($products as $product) {
                $prices_ids = array_merge($prices_ids, $product['prices_ids']);
            }
            $om->update('sale\price\Price', $prices_ids, ['name' => null], $lang);
        }
        $om->update(self::getType(), $ids, ['name' => null], $lang);
    }

    public static function onupdateAgeRangeId($om, $ids, $values, $lang) {
        $products = $om->read(self::getType(), $ids, ['age_range_id']);
        if($products > 0 && count($products)) {
            foreach($products as $id => $product) {
                $om->update(self::getType(), $id, ['has_age_range' => boolval($product['age_range_id'])], $lang);
            }
        }
    }
}