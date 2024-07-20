<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;

class ExtraService extends \equal\orm\Model {

    public static function getDescription() {
        return "Extra services are used as interface for mapping local product Model with Services from the channel manager.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the extra service.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'extref_inventory_code' => [
                'type'              => 'string',
                'description'       => "External reference of the extra service (from channel manager).",
                'required'          => true
            ],

            'property_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\Property',
                'description'       => "The center to the property refers to.",
                'required'          => true,
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\ProductModel',
                'description'       => "Product Model to use when a room of this type is booked.",
                'required'          => true,
            ],

        ];
    }

    public static function calcName($om, $ids, $lang) {
        $result = [];
        $services = $om->read(self::getType(), $ids, ['product_model_id.name'], $lang);
        if($services > 0) {
            foreach($services as $id => $service) {
                $result[$id] = $service['product_model_id.name'];
            }
        }
        return $result;
    }
}