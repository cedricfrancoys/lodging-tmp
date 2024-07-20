<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => "This archives the reservation, if the reservation is in quote  or checkout, with a date in the past and no money received",
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
$booking = Booking::id($params['id'])
                    ->read(['id', 'name','state', 'status','is_cancelled' , 'created', 'date_from', 'date_to', 'center_office_id', 'booking_lines_ids', 'price', 'is_cancelled', 'paid_amount'])
                    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$quote_delay = Setting::get_value('lodging', 'main', 'quote.validity_delay');

$booking_delay = Setting::get_value('lodging', 'main', 'booking.archive_delay');

if($booking['state'] == 'archive') {
    throw new Exception("invalid_states_booking", QN_ERROR_INVALID_PARAM);
}

if($booking['status'] == 'quote') {
    if($booking['created'] >= strtotime("-$quote_delay days") || round($booking['paid_amount'], 2) != 0.00) {
        throw new Exception("invalid_quote_booking", QN_ERROR_INVALID_PARAM);
    }
    Booking::id($booking['id'])->update(['state' => 'archive']);
}
elseif($booking['status'] == 'checkedout') {

    if(!$booking['is_cancelled']) {
        throw new Exception("non_cancelled_booking", QN_ERROR_INVALID_PARAM);
    }
    if($booking['date_to'] >=  strtotime("-$booking_delay days") || round($booking['price'],2) != 0.00){
        throw new Exception("invalid_checkout_booking", QN_ERROR_INVALID_PARAM);
    }
    Booking::id($booking['id'])->update(['state' => 'archive']);
}
else {
    throw new Exception("invalid_status_booking", QN_ERROR_INVALID_PARAM);
}

$context->httpResponse()
        ->status(204)
        ->send();