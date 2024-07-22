<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;


list($params, $providers) = eQual::announce([
    'description'   => "Checks consistency of hosts composition (age ranges) for a given booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the composition must be checked.',
            'type'          => 'integer',
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

// ensure booking object exists and is readable
$booking = Booking::id($params['id'])
    ->read([
        'id',
        'center_office_id',
        'booking_lines_groups_ids' => [
                'has_pack',
                'is_locked',
                'price_id',
                'booking_lines_ids' => ['price_id']
            ]
        ]
    )
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$mismatch = false;

foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['has_pack'] && $group['is_locked'] ) {
        if(is_null($group['price_id']) || $group['price_id'] <= 0) {
            $mismatch = true;
            break;
        }
    }
    else {
        foreach($group['booking_lines_ids'] as $line) {
            if(is_null($line['price_id']) || $line['price_id'] <= 0) {
                $mismatch = true;
                break;
            }
        }
    }
}

/*
    This controller is a check: an empty response means that no alert was raised
*/

$result = [];
$httpResponse = $context->httpResponse()->status(200);

// compare with the number of lines of compositions we got so far
if($mismatch) {
    $result[] = $params['id'];
    // by convention we dispatch an alert that relates to the controller itself.
    $dispatch->dispatch('lodging.booking.prices_assignment', 'lodging\sale\booking\Booking', $params['id'], 'important', 'lodging_booking_check-prices-assignments', ['id' => $params['id']], [], null, $booking['center_office_id']);
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('lodging.booking.prices_assignment', 'lodging\sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();
