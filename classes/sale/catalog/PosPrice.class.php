<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;


class PosPrice extends \sale\price\Price {
    public static function getColumns() {
        return [

            // #memo - we let the user deal with the accounting rule (VAT rate might vary)

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\PosProduct',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'onupdate'          => 'sale\price\Price::onupdateProductId'
            ],

            'price_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\PosPriceList',
                'description'       => "The Price List the price belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade',
                'onupdate'          => 'sale\price\Price::onupdatePriceListId'
            ],


        ];
    }

}