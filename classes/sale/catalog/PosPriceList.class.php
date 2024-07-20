<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;

class PosPriceList extends \sale\price\PriceList {

    public static function getColumns() {
        return [
            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\catalog\PosPrice',
                'foreign_field'     => 'price_list_id',
                'description'       => "Prices that are related to this list, if any.",
            ]
        ];
    }

}