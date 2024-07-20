<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\discount;

class DiscountList extends \sale\discount\DiscountList {

    public static function getColumns() {

        return [
            'discounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\discount\Discount',
                'foreign_field'     => 'discount_list_id',
                'description'       => 'The discounts that are part of the list.'
            ]
        ];
    }

}
