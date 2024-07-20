<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;

class Group extends \sale\catalog\Group {
    public static function getColumns() {

        return [

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "Center targeted by the group.",
                'required'          => true
            ],

            'products_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'foreign_field'     => 'groups_ids',
                'rel_table'         => 'sale_catalog_product_rel_product_group',
                'rel_foreign_key'   => 'product_id',
                'rel_local_key'     => 'group_id'
            ],

            'product_models_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\sale\catalog\ProductModel',
                'foreign_field'     => 'groups_ids',
                'rel_table'         => 'sale_catalog_product_rel_productmodel_group',
                'rel_foreign_key'   => 'productmodel_id',
                'rel_local_key'     => 'group_id'
            ]

        ];
    }
}