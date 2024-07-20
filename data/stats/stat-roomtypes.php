<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\channelmanager\Property;
use lodging\sale\booking\Consumption;

list($params, $providers) = eQual::announce([
    'description'   => 'Provides data for viewing resulting availability of Room Types in a given date range for a given Property.',
    'params'        => [
        /* input parameters: required for fetching data */
        'property_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\channelmanager\Property',
            'description'       => "The Property for which the stats are required."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],

        /* parameters used as properties of virtual entity */
        'property' => [
            'type'              => 'string',
            'description'       => 'Name of the property.'
        ],
        'date' => [
            'type'              => 'date',
            'description'       => 'Day for tuple room_type-availability.',
        ],
        'room_type' => [
            'type'              => 'string',
            'description'       => 'Name of the room_type.'
        ],
        'availability' => [
            'type'              => 'integer',
            'description'       => 'Availability of the room type at the date.'
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'adapt' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\data\DataAdapter     $adapter
 */
list($context, $orm, $adapter) = [ $providers['context'], $providers['orm'], $providers['adapt'] ];

$result = [];

$property = Property::id($params['property_id'])->read(['id', 'name', 'room_types_ids' => ['id', 'name', 'center_id', 'extref_roomtype_id', 'rental_units_ids']])->first(true);

if(!$property) {
    throw new Exception('unknown_property', QN_ERROR_UNKNOWN_OBJECT);
}

foreach($property['room_types_ids'] as $room_type) {
    // prefill with max value (total)
    $total = count($room_type['rental_units_ids']);
    $map_dates_availability = [];
    for($d = $params['date_from']; $d < $params['date_to']; $d = strtotime('+1 day', $d)) {
        $date_index = substr(date('c', $d), 0, 10);
        $map_dates_availability[$date_index] = $total;
    }

    // #memo - this logic is duplicated in `check-contingencies`

    // #memo - this returns the compacted version of the consumptions (with virtual consumptions that span over several days) holding a date_from and date_to computed fields
    $map_existing_consumptions = Consumption::getExistingConsumptions($orm, [$room_type['center_id']], $params['date_from'], $params['date_to']);

    foreach($map_existing_consumptions as $rental_unit_id => $dates) {
        // we consider consumptions relating to rental unit, whatever the type of the consumption
        if(in_array($rental_unit_id, $room_type['rental_units_ids'])) {
            foreach($dates as $index => $consumptions) {
                foreach($consumptions as $consumption) {
                    for($d = $consumption['date_from']+$consumption['schedule_from']; $d < $consumption['date_to']+$consumption['schedule_to']; $d = strtotime('+1 day', $d)) {
                        $date_index = substr(date('c', $d), 0, 10);
                        if(isset($map_dates_availability[$date_index]) && $map_dates_availability[$date_index] > 0) {
                            --$map_dates_availability[$date_index];
                            // #memo #todo - this might require a review in case of change in the logic of repairings (the same processing occurs in the planning.calendar.component of the Booking App)
                            if($consumption['type'] == 'ooo' && $d == $consumption['date_from']+$consumption['schedule_from']) {
                                $prev_date_index = substr(date('c', strtotime('-1 day', $consumption['date_from']+$consumption['schedule_from'])), 0, 10);
                                if(isset($map_dates_availability[$prev_date_index]) && $map_dates_availability[$prev_date_index] > 0) {
                                    --$map_dates_availability[$prev_date_index];
                                }
                            }
                        }
                        else {
                            // unexpected situation (date_index is not part of the range) : ignore
                        }
                    }
                }
            }
        }
    }

    foreach($map_dates_availability as $date_index => $count_available) {
        $result[] = [
            'property'      => $property['name'],
            'date'          => $date_index,
            'room_type'     => $room_type['extref_roomtype_id'].' - '.$room_type['name'],
            'availability'  => $count_available
        ];
    }
}


$context->httpResponse()
        ->body($result)
        ->send();
