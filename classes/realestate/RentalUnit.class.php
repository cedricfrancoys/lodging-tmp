<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\realestate;


class RentalUnit extends \realestate\RentalUnit {

    public static function getDescription() {
        return "A rental unit is a resource that can be rented to a customer.";
    }

    public static function getColumns() {
        return [

            /*
            // center categories are just a hint at the center level, but are not applicable on rental units (rental units can be either GA or GG)
            'center_category_id' => [
                'type'              => 'many2one',
                'description'       => "Center category which current unit belongs to, if any.",
                'foreign_object'    => 'lodging\identity\CenterCategory'
            ],
            */

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => 'The center to which belongs the rental unit.'
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'description'       => "Rental Unit which current unit belongs to, if any.",
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'onupdate'          => 'onupdateParentId',
                'domain'            => ['center_id', '=', 'object.center_id']
            ],

            'children_ids' => [
                'type'              => 'one2many',
                'description'       => "The list of rental units the current unit can be divided into, if any (i.e. a dorm might be rent as individual beds).",
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'foreign_field'     => 'parent_id',
                'domain'            => ['center_id', '=', 'object.center_id']
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\SojournType',
                'description'       => 'Default sojourn type of the rental unit.',
                'default'           => 1,
                'visible'           => ['is_accomodation', '=', true]
            ],

            'composition_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\CompositionItem',
                'foreign_field'     => 'rental_unit_id',
                'description'       => "The composition items that relate to the rental unit."
            ],

            'color' => [
                'type'              => 'string',
                'usage'             => 'color',
                // #todo - will no longer be necessary when usage 'color' will be supported
                'selection' => [
                    'lavender',
                    'antiquewhite',
                    'moccasin',
                    'lightpink',
                    'lightgreen',
                    'paleturquoise'
                ],
                'description'       => 'Arbitrary color to use for the rental unit when rendering the calendar.'
            ],

            'room_types_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\RoomType',
                'foreign_field'     => 'rental_units_ids',
                'rel_table'         => 'lodging_rental_unit_rel_room_type',
                'rel_foreign_key'   => 'room_type_id',
                'rel_local_key'     => 'rental_unit_id',
                'description'       => 'Room Type (from channel manager) the rental unit refers to.',
                'help'              => 'If this field is set, it means that the rental unit can be rented on OTA via the channel manager. So, in case of a local booking it must trigger an update of the availabilities.'
            ]

        ];
    }

    public static function getConsumptions($om, $rental_unit_id, $date_from, $date_to) {
        $result = [];

        // #memo - a consumption always spans on a single day
        $consumptions_ids = $om->search(\lodging\sale\booking\Consumption::getType(), [
                ['date', '>=', $date_from],
                ['date', '<=', $date_to],
                ['rental_unit_id', '=', $rental_unit_id]
            ], ['date' => 'asc']);

        if($consumptions_ids > 0 && count($consumptions_ids)) {
            $consumptions = $om->read(\lodging\sale\booking\Consumption::getType(), $consumptions_ids, [
                    'id',
                    'date',
                    'rental_unit_id',
                    'schedule_from',
                    'schedule_to'
                ]);

            foreach($consumptions as $id => $consumption) {
                $consumption_from = $consumption['date_from'] + $consumption['schedule_from'];
                $consumption_to = $consumption['date_to'] + $consumption['schedule_to'];
                // keep all consumptions for which intersection is not empty
                if(max($date_from, $consumption_from) < min($date_to, $consumption_to)) {
                    $result[$id] = $consumption;
                }
            }
        }

        return $result;
    }

    public static function canupdate($om, $ids, $values, $lang='en') {

        foreach($ids as $id) {
            if(isset($values['parent_id'])) {
                $descendants_ids = [];
                $rental_units_ids = [$id];
                for($i = 0; $i < 2; ++$i) {
                    $units = $om->read(self::getType(), $rental_units_ids, ['children_ids']);
                    if($units > 0) {
                        $rental_units_ids = [];
                        foreach($units as $id => $unit) {
                            if(count($unit['children_ids'])) {
                                foreach($unit['children_ids'] as $uid) {
                                    $rental_units_ids[] = $uid;
                                    $descendants_ids[] = $uid;
                                }
                            }
                        }
                    }
                }
                if(in_array($values['parent_id'], $descendants_ids)) {
                    return ['parent_id' => ['child_cannot_be_parent' => 'Selected parent cannot be amongst rental unit children.']];
                }
            }
            if(isset($values['children_ids'])) {
                $ancestors_ids = [];
                $parent_unit_id = $id;
                for($i = 0; $i < 2; ++$i) {
                    $units = $om->read(self::getType(), $parent_unit_id, ['parent_id']);
                    if($units > 0) {
                        foreach($units as $id => $unit) {
                            if(isset($unit['parent_id']) && $unit['parent_id'] > 0) {
                                $parent_unit_id = $unit['parent_id'];
                                $ancestors_ids[] = $unit['parent_id'];
                            }
                        }
                    }
                }
                foreach($values['children_ids'] as $assignment) {
                    if($assignment > 0) {
                        if(in_array($assignment, $ancestors_ids)) {
                            return ['children_ids' => ['parent_cannot_be_child' => "Selected children cannot be amongst rental unit parents ({$assignment})."]];
                        }
                    }
                }
            }
        }
        return [];
    }

}
