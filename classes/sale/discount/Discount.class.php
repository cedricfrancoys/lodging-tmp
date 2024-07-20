<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\discount;


class Discount extends \sale\discount\Discount {

    public static function getColumns() {

        return [

            'has_age_ranges' => [
                'type'              => 'boolean',
                'default'           => false
            ],

            'age_ranges_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\sale\customer\AgeRange',
                'foreign_field'     => 'discounts_ids',
                'rel_table'         => 'lodging_sale_discount_rel_agerange_discount',
                'rel_foreign_key'   => 'age_range_id',
                'rel_local_key'     => 'discount_id',
                'visible'           => ['has_age_ranges', '=', true],
                'description'       => 'The conditions that apply to the discount.'
            ],

            'value_max' => [
                'type'              => 'string',
                'selection'         => [
                    'nb_pers',
                    'nb_adults',
                    'nb_children'
                ],
                'visible'           => ['type', '=', 'freebie'],
                'description'       => 'The maximum amount of freebies that can be granted.',
                'help'              => 'This is a reference to maximum freebies that can be granted, according to current sojourn (Booking Line Group). This can only be applied for freebie discounts.',
                'default'           => 'nb_pers'
            ],

            'conditions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\discount\Condition',
                'foreign_field'     => 'discount_id',
                'description'       => 'The conditions that apply to the discount.'
            ]

        ];
    }

}