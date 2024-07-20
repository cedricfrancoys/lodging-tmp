<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\channelmanager\Property;
use core\Task;

list($params, $providers) = eQual::announce([
    'description'   => "This script schedules a series of tasks in order to update the amount of available rooms in Cubilis for all rental units for a given Property, between two dates.",
    'params'        => [
        'property_id'   => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\channelmanager\Property',
            'required'          => true
        ],
        'date_from' => [
            'type'          => 'date',
            'description'   => 'The start date.',
            'required'      => true
        ],
        'date_to' => [
            'type'          => 'date',
            'description'   => 'The end date (last day for which the update must be performed).',
            'required'      => true
        ],
        'roomtype' => [
            'type'          => 'integer',
            'description'   => 'External identifier of the room type (from channel manager).'
        ],
        'delay' => [
            'type'          => 'integer',
            'description'   => 'Delay in second before the scheduling of the resulting update tasks',
            'default'       => 86400
        ]
    ],
    'constants' => ['ROOT_APP_URL'],
    'access' => [
        'groups'            => ['admins'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\cron\Scheduler               $cron
 */
list($context, $cron) = [$providers['context'], $providers['cron']];

// #memo - temporary solution to prevent calls from non-production server
if(constant('ROOT_APP_URL') != 'https://discope.yb.run') {
    throw new Exception('wrong_host', QN_ERROR_INVALID_CONFIG);
}

$property = Property::id($params['property_id'])
    ->read(['room_types_ids' => ['id', 'extref_roomtype_id', 'rental_units_ids', 'is_active']])
    ->first();

if(!$property) {
    throw new Exception('unknown_property', QN_ERROR_UNKNOWN_OBJECT);
}

$i = 1;

// sync one date at a time, and one room type at a time, by requesting a check-contingencies for all rental units linked to it
$start_date = $params['date_from'];
$end_date = $params['date_to'];

for ($current_date = $start_date; $current_date <= $end_date; $current_date = strtotime('+1 day', $current_date)) {
    $j = 1;
    $count_room_types = count($property['room_types_ids']);
    foreach($property['room_types_ids'] as $room_type) {
        // discard room types that do not match the given one, if any
        if(
            (isset($params['roomtype']) && $room_type['extref_roomtype_id'] != $params['roomtype'])
            || !$room_type['is_active']
        ) {
            continue;
        }
        // schedule for the next day, each call deferred with a 1 minute interval
        $cron->schedule(
            "channelmanager.update-cubilis.{$i}.{$j}",
            time() + $params['delay'] + (60*((($i-1)*$count_room_types)+($j-1))),
            'lodging_booking_check-contingencies',
            [
                'date_from'         => date('c', $current_date),
                'date_to'           => date('c', strtotime('+1 day', $current_date)),
                'rental_units_ids'  => $room_type['rental_units_ids']
            ]
        );
        ++$j;
    }
    ++$i;
}

$context->httpResponse()
        ->status(200)
        ->send();
