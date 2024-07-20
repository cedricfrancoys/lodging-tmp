<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;
use equal\orm\Model;

class ProductFavorite extends \sale\catalog\ProductFavorite {

    public static function getColumns() {
        return [

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => "Targeted product.",
                'onupdate'          => 'onupdateProductId',
                'required'          => true
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => "Center Office the favorite belongs to."
            ]

        ];
    }

}