<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\alert\Message;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'	=>	"Batch for updating alerts related to bookings.",
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'dispatch']
]);

/**
 * @var equal\php\Context           $context
 * @var equal\dispatch\Dispatcher   $dispatcher
 */
list($context, $dispatcher) = [$providers['context'], $providers['dispatch']];

$result = [
    'logs' => []
];

$messages = Message::search(['severity', '=', 'important'])->read(['id', 'object_class', 'object_id'])->get(true);

$map_bookings_ids = [];

// step-1 : recheck all current alerts (attempts to dismiss)
foreach($messages as $message) {
    if($message['object_class'] == 'lodging\sale\booking\Booking') {
        $map_bookings_ids[$message['object_id']] = true;
    }
}

// step-2 : cancel all checks for Booking that have been set as 'balanced' or 'quote'
if(count($map_bookings_ids)) {
    $bookings = Booking::ids(array_keys($map_bookings_ids))->read(['id', 'status'])->get(true);
    $bookings_ids = [];
    foreach($bookings as $booking) {
        if(in_array($booking['status'], ['balanced', 'quote'])) {
            $bookings_ids[] = $booking['id'];
        }
    }
    try {
        // prevent overflow
        if(count($bookings_ids) > 200) {
            $bookings_ids = array_slice($bookings_ids, 0, 200);
        }
        $result['logs'][] = 'Call `lodging_booking_bulk-update-checks` on: '.implode(',', $bookings_ids);
        eQual::run('do', 'lodging_booking_bulk-update-checks', ['ids' => $bookings_ids]);
    }
    catch(Exception) {
        // ignore errors
    }
}

$context->httpResponse()
        ->body(['result' => $result])
        ->send();
