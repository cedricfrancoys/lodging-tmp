<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Consumption;
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Checks if there are any rental units of the given booking blocked by other bookings.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against overbooking.',
            'type'          => 'integer',
            'required'      => true
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [ $providers['context'], $providers['orm'], $providers['auth'], $providers['dispatch']];

// ensure booking object exists and is readable
$booking = Booking::id($params['id'])->read(['id', 'name', 'center_office_id'])->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    Get in-memory consumptions resulting from the booking lines, in order to check booking consumptions against existing consumptions.
*/

// map of consumptions by rental_unit (ordered on date)
$consumptions_map = [];

// get in-memory consumptions that are implied by the booking
$consumptions = $orm->call(Booking::getType(), 'getResultingConsumptions', $params['id']);

// filter to keep only accommodations
$consumptions = array_filter($consumptions, function($a) {
    return $a['is_rental_unit'];
});

// sort ascending on date
usort($consumptions, function($a, $b) {
    return ($a['date'] < $b['date'])?-1:1;
});

foreach($consumptions as $consumption) {
    $rental_unit_id = $consumption['rental_unit_id'];
    $booking_line_group_id = $consumption['booking_line_group_id'];

    if($rental_unit_id <= 0) {
        continue;
    }

    if(!isset($consumptions_map[$booking_line_group_id])) {
        $consumptions_map[$booking_line_group_id] = [];
    }

    if(!isset($consumptions_map[$booking_line_group_id][$rental_unit_id])) {
        $consumptions_map[$booking_line_group_id][$rental_unit_id] = [];
    }

    $consumptions_map[$booking_line_group_id][$rental_unit_id][] = $consumption;
}


/*
    Detect collisions and keep involved bookings ids.
*/

// map of colliding bookings
$colliding_bookings_map = [];

foreach($consumptions_map as $booking_line_group_id => $rental_units) {

    foreach($rental_units as $rental_unit_id => $consumptions) {

        // get the date of the first consumption => date_from ; and the date of last consumption => date_to
        $len = count($consumptions);
        $first = $consumptions[0];
        $last = $consumptions[$len-1];
        $date_from = $first['date'];
        $date_to = $last['date'];

        // look for other consumptions (not in booking_line_group_id) assigned to the same rental_unit with date >= date_from AND date <= date_to
        $colliding_ids = Consumption::search([
                ['booking_line_group_id', '<>', $booking_line_group_id],
                ['rental_unit_id', '=', $rental_unit_id],
                ['type', '<>', 'part'],
                ['date', '>=', $date_from],
                ['date', '<=', $date_to]
            ])
            ->ids();

        if($colliding_ids && count($colliding_ids)) {
            // filter resulting collisions
            $colliding_consumptions = Consumption::ids($colliding_ids)->read(['booking_id', 'date', 'schedule_from', 'schedule_to'])->get();

            foreach($colliding_consumptions as $cid => $collision) {
                if($collision['date'] == $first['date']) {
                    if($collision['schedule_to'] < $first['schedule_from']) {
                        unset($colliding_consumptions[$cid]);
                        continue;
                    }
                }
                if($collision['date'] == $last['date']) {
                    if($collision['schedule_from'] > $last['schedule_to']) {
                        unset($colliding_consumptions[$cid]);
                    }
                }
            }

            // collision array is not empty : overbooking or assignment to an OOO rental unit
            if(count($colliding_consumptions)) {

                foreach($colliding_consumptions as $cid => $consumption) {
                    $colliding_bookings_map[$consumption['booking_id']] = true;
                }
            }

        }

    }
}

$colliding_bookings_ids = array_keys($colliding_bookings_map);

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];
$httpResponse = $context->httpResponse()->status(200);

$is_colliding_bookings = (bool) count($colliding_bookings_ids);

// ignore self-collision (come and go between quote and option)
if(count($colliding_bookings_ids) == 1 && $colliding_bookings_ids[0] == $params['id']) {
    $is_colliding_bookings = false;
}

if($is_colliding_bookings) {
    $bookings = Booking::ids($colliding_bookings_ids)->read(['id', 'name'])->get(true);

    $links = [];

    foreach($bookings as $booking) {
        $links[] = "[{$booking['name']}](/booking/#/booking/{$booking['id']})";
        $result[] = $booking['id'];
    }

    // by convention we dispatch an alert that relates to the controller itself.
    $dispatch->dispatch('lodging.booking.overbooking', 'lodging\sale\booking\Booking', $params['id'], 'important', 'lodging_booking_check-overbooking', ['id' => $params['id']], $links, null, $booking['center_office_id']);

    $httpResponse->status(qn_error_http(QN_ERROR_CONFLICT_OBJECT));
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('lodging.booking.overbooking', 'lodging\sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();