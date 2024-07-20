<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class CompositionItem extends \sale\booking\CompositionItem {

    public static function getColumns() {
        return [
            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'description'       => "The rental unit the person is assigned to.",
                'required'          => true,
                'domain'            => ['id', 'in', 'object.rental_units_ids']
            ],

            'composition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Composition',
                'description'       => "The composition the item refers to.",
                'onupdate'          => 'onupdateCompositionId',
                'ondelete'          => 'cascade',        // delete item when parent composition is deleted
                'required'          => true
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'The booking the composition relates to.'
            ],

            // for filtering rental_unit_id field in forms
            /*
            // #memo - this seems incorrect and generates an error when printing the listing
            'rental_units_ids' => [
                'type'              => 'computed',
                'result_type'       => 'one2many',
                'function'          => 'calcRentalUnitsIds',
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'description'       => "The rental units attached to the current booking."
            ]
            */

            // #memo - values provided by OTA might not be valid values

            'email' => [
                'type'              => 'string',
                'description'       => "Email address of the contact."
            ],

            'phone' => [
                'type'              => 'string',
                'description'       => "Phone number of the contact."
            ]

        ];
    }

    public static function calcRentalUnitsIds($om, $oids, $lang) {
        $result = [];
        $items = $om->read(__CLASS__, $oids, ['composition_id.booking_id']);

        foreach($items as $oid => $odata) {

            $rental_units_ids = [];
            $assignments_ids = $om->search(\lodging\sale\booking\SojournProductModelRentalUnitAssignement::getType(), ['booking_id', '=', $odata['composition_id.booking_id']]);

            if($assignments_ids > 0 && count($assignments_ids)) {
                $assignments = $om->read(\lodging\sale\booking\SojournProductModelRentalUnitAssignement::getType(), $assignments_ids, ['rental_unit_id']);
                $rental_units_ids = array_filter(array_map(function($a) { return $a['rental_unit_id']; }, array_values($assignments)), function($a) {return $a > 0;});
            }

            $result[$oid] = $rental_units_ids;
        }
        return $result;
    }

}