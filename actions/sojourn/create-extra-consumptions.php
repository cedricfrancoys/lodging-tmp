<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\realestate\RentalUnit;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\Consumption;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;

list($params, $providers) = announce([
    'description'   => "Create consumptions for a given extra services group.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted sojourn (BookingLineGroup).',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

$group = BookingLineGroup::id($params['id'])->read(['id', 'nb_pers', 'is_extra', 'has_consumptions', 'date_from', 'date_to', 'time_from', 'time_to', 'booking_id' => ['center_id']])->first(true);

if(!$group) {
    throw new Exception("unknown_sojourn", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$group['is_extra']) {
    // this controller is mean for extra services groups only
    throw new Exception("incompatible_group", QN_ERROR_INVALID_PARAM);
}

if($group['has_consumptions']) {
    // consumptions cannot be re-created
    throw new Exception("invalid_status", QN_ERROR_INVALID_PARAM);
}

// check assignments consistency

$assignments = SojournProductModelRentalUnitAssignement::search(['booking_line_group_id', '=', $params['id']])->read(['id', 'qty', 'rental_unit_id'])->get(true);
$total_qty = 0;
foreach($assignments as $assignment) {
    $total_qty += $assignment['qty'];
    $rental_unit = RentalUnit::id($assignment['rental_unit_id'])->read(['is_accomodation', 'capacity'])->first(true);
    if(!$rental_unit) {
        throw new Exception('unknown_rental_unit', QN_ERROR_UNKNOWN_OBJECT);
    }
    if($group['nb_pers'] < $assignment['qty']) {
        throw new Exception('quantity_exceeds_group', QN_ERROR_INVALID_PARAM);
    }
    if($rental_unit['capacity'] < $assignment['qty']) {
        throw new Exception('quantity_exceed_accommodation', QN_ERROR_INVALID_PARAM);
    }
}

if(count($assignments) > 0 && $group['nb_pers'] != $total_qty) {
    // assignment count mismatch
    throw new Exception('group_assignment_quantity_mismatch', QN_ERROR_INVALID_PARAM);
}

// check overbooking

// compute timestamps for the sojourn date range
$date_from = $group['date_from'] + $group['time_from'];
$date_to = $group['date_to'] + $group['time_to'];

// retrieve existing consumptions for sojourn date range
$existing_consumptions_map = Consumption::getExistingConsumptions($orm, [$group['booking_id']['center_id']], $date_from, $date_to);

// check assignments consistency : there is no consumption from other bookings that overlap with updated assignments (based on group details)
foreach($params['assignments'] as $assignment) {
    $target_rental_unit_id = $assignment['rental_unit_id'];
    foreach($existing_consumptions_map as $rental_unit_id => $dates) {
        if($rental_unit_id != $target_rental_unit_id) {
            continue;
        }
        foreach($dates as $date_index => $consumptions) {
            foreach($consumptions as $consumption) {
                // ignore consumptions from same booking
                if($consumption['booking_id'] == $group['booking_id']['id']) {
                    continue;
                }
                $consumption_from = $consumption['date_from'] + $consumption['schedule_from'];
                $consumption_to = $consumption['date_to'] + $consumption['schedule_to'];
                // #memo - we don't allow instant transition (i.e. checkin time of a booking equals checkout time of a previous booking)
                if(max($date_from, $consumption_from) < min($date_to, $consumption_to)) {
                    throw new Exception('booked_rental_unit', QN_ERROR_CONFLICT_OBJECT);
                }
            }
        }
    }
}


// generate consumptions for extra sojourn
$orm->call(BookingLineGroup::getType(), 'createConsumptions', $params['id']);

BookingLineGroup::id($params['id'])->update(['has_consumptions' => true]);

// rental units were assigned: check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
$map_rental_units_ids = [];

$group = BookingLineGroup::id($params['id'])->read(['date_from', 'date_to', 'consumptions_ids' => ['is_accomodation', 'rental_unit_id']])->first(true);

foreach($group['consumptions_ids'] as $consumption) {
    if($consumption['is_accomodation']) {
        $map_rental_units_ids[$consumption['rental_unit_id']] = true;
    }
}

if(count($map_rental_units_ids)) {
    $cron->schedule(
            "channelmanager.check-contingencies.{$params['id']}",
            time(),
            'lodging_booking_check-contingencies',
            [
                'date_from'         => date('c', $group['date_from']),
                'date_to'           => date('c', $group['date_to']),
                'rental_units_ids'  => array_keys($map_rental_units_ids)
            ]
        );
}

$context->httpResponse()
        ->status(204)
        ->send();
