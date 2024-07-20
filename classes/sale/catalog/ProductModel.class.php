<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;


class ProductModel extends \sale\catalog\ProductModel {

    /*
        This class extends the ProductModel with fields specific to property rental.
    */

    public static function getColumns() {

        return [
            'qty_accounting_method' => [
                'type'              => 'string',
                'description'       => 'The way the product quantity has to be computed (per unit [default], per person, or per accommodation [resource]).',
                'selection'         => [
                    'person',           // depends on the number of people
                    'accomodation',     // depends on the number of nights
                    'unit'              // only depends on quantity
                ],
                'default'           => 'unit'
            ],

            'booking_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The kind of booking it is about.",
                'default'           => 1                // default to 'general public'
            ],

            'is_repeatable' => [
                'type'              => 'boolean',
                'description'       => 'Model relates to a consumption that is repeated each day of the sojourn.',
                'default'           => false,
                'visible'           => [ 'has_duration', '=', false ]
            ],

            'is_accomodation' => [
                'type'              => 'boolean',
                'description'       => 'Model relates to a rental unit that is an accommodation.',
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', true] ]
            ],

            'is_rental_unit' => [
                'type'              => 'boolean',
                'description'       => 'Is the product a rental_unit?',
                'default'           => false,
                'onupdate'          => 'onupdateIsRentalUnit',
                'visible'           => [ ['type', '=', 'service'], ['is_meal', '=', false] ]
            ],

            'is_meal' => [
                'type'              => 'boolean',
                'description'       => 'Is the product a meal? (meals might be part of the board / included services of the stay).',
                'default'           => false,
                'visible'           => [ ['type', '=', 'service'], ['is_rental_unit', '=', false] ]
            ],

            'rental_unit_assignement' => [
                'type'              => 'string',
                'description'       => 'The way the product is assigned to a rental unit (a specific unit, a specific category, or based on capacity match).',
                'selection'         => [
                    'unit',             // only one specific rental unit can be assigned to the products
                    'category',         // only rental units of the specified category can be assigned to the products
                    'auto'              // rental unit assignment is based on required qty/capacity (best match first)
                ],
                'default'           => 'category',
                'visible'           => [ ['is_rental_unit', '=', true] ]
            ],

            'has_duration' => [
                'type'              => 'boolean',
                'description'       => 'Does the product have a specific duration.',
                'default'           => false,
                'visible'           => ['type', '=', 'service']
            ],

            'duration' => [
                'type'              => 'integer',
                'description'       => 'Duration of the service (in days), used for planning.',
                'default'           => 1,
                'visible'           => [ ['type', '=', 'service'], ['has_duration', '=', true] ]
            ],

            'capacity' => [
                'type'              => 'integer',
                'description'       => 'Capacity implied by the service (used for filtering rental units).',
                'default'           => 1
            ],

            // a product either refers to a specific rental unit, or to a category of rental units (both allowing to find matching units for a given period and a capacity)
            'rental_unit_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\RentalUnitCategory',
                'description'       => "Rental Unit Category this Product related to, if any.",
                'visible'           => [ ['is_rental_unit', '=', true], ['rental_unit_assignement', '=', 'category'] ]
            ],

            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'description'       => "Specific Rental Unit this Product related to, if any",
                'visible'           => [ ['is_rental_unit', '=', true], ['rental_unit_assignement', '=', 'unit'] ],
                'onupdate'          => 'onupdateRentalUnitId'
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product variants that are related to this model.",
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\sale\catalog\Group',
                'foreign_field'     => 'product_models_ids',
                'rel_table'         => 'sale_catalog_product_rel_productmodel_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'productmodel_id'
            ],

            'allow_price_adaptation' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if price adaptation can be applied on the variants (or children for packs).',
                'default'           => true,
                'visible'           => ['is_pack', '=', true],
                'onupdate'          => 'onupdateAllowPriceAdaptation'
            ]

        ];
    }

    /**
     * Assign the related rental unity capacity as own capacity.
     */
    public static function onupdateRentalUnitId($om, $ids, $values, $lang) {
        $models = $om->read(self::gettype(), $ids, ['rental_unit_id.capacity', 'rental_unit_id.is_accomodation'], $lang);
        foreach($models as $id => $model) {
            $om->update(self::gettype(), $id, ['capacity' => $model['rental_unit_id.capacity'], 'is_accomodation' => $model['rental_unit_id.is_accomodation']]);
        }
    }

    /**
     * Sync model with variants (products) upon change for `allow_price_adaptation`
     */
    public static function onupdateAllowPriceAdaptation($om, $ids, $values, $lang) {
        $models = $om->read(self::getType(), $ids, ['products_ids', 'allow_price_adaptation'], $lang);
        foreach($models as $id => $model) {
            $om->update(Product::gettype(), $model['products_ids'], ['allow_price_adaptation' => $model['allow_price_adaptation']]);
        }
    }

    public static function onupdateIsRentalUnit($om, $ids, $values, $lang) {
        $models = $om->read(self::getType(), $ids, ['is_rental_unit'], $lang);
        foreach($models as $id => $model) {
            if(!$model['is_rental_unit']) {
                $om->update(self::gettype(), $id, ['is_accomodation' => false]);
            }
        }
    }

}