<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Booking;
use lodging\identity\CenterOffice;

list($params, $providers) = announce([
    'description'   => "Checks if the  date of the check-in is the same as the booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against emptyness.',
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
$booking = Booking::id($params['id'])->read(['id', 'status','date_from', 'date_to', 'is_cancelled','center_office_id'])->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$center_office = CenterOffice::id($booking['center_office_id'])->read(['id', 'name'])->first();

if(!$center_office) {
    throw new Exception("unknown_center_office", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];
$httpResponse = $context->httpResponse()->status(200);

// #memo - this check only applies to bookings not yet checked in and not cancelled. For convenience, GG can checkin at any time.
if(($booking['date_from'] > time()) && in_array($booking['status'], ['confirmed', 'validated']) && !$booking['is_cancelled'] && $center_office['id'] != 1  ) {
    $result[] = $booking['id'];
    $dispatch->dispatch('lodging.booking.date.checkin', 'lodging\sale\booking\Booking', $params['id'], 'warning', 'lodging_booking_check-date-checkin', ['id' => $params['id']], [], null, $center_office['id']);
}
else {
    $dispatch->cancel('lodging.booking.date.checkin', 'lodging\sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();