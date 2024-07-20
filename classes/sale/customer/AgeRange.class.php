<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace lodging\sale\customer;


class AgeRange extends \sale\customer\AgeRange {

    public static function getColumns() {

        return [
            'discounts_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\sale\discount\Discount',
                'foreign_field'     => 'age_ranges_ids',
                'rel_table'         => 'lodging_sale_discount_rel_agerange_discount',
                'rel_foreign_key'   => 'discount_id',
                'rel_local_key'     => 'age_range_id',
                'description'       => 'The conditions that apply to the discount.'
            ]
        ];
    }

}
