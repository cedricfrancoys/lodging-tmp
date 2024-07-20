<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Consumption;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Checks if mandatory customer details are present (name and address).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against emptiness.',
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
            ->read([
                    'id',
                    'name',
                    'is_from_channelmanager',
                    'center_office_id',
                    'customer_identity_id' => ['id', 'display_name', 'address_street', 'address_city', 'address_zip']
                ])
            ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];
$httpResponse = $context->httpResponse()->status(200);

if( !$booking['is_from_channelmanager'] && (!strlen($booking['customer_identity_id']['address_street']) || !strlen($booking['customer_identity_id']['address_city']) || !strlen($booking['customer_identity_id']['address_zip'])) ) {
    $result[] = $booking['id'];
    $links = [];
    $links[] = "[{$booking['customer_identity_id']['display_name']}](/booking/#/identity/{$booking['customer_identity_id']['id']})";
    // by convention we dispatch an alert that relates to the controller itself.
    $dispatch->dispatch('lodging.booking.customer.uncomplete', 'lodging\sale\booking\Booking', $params['id'], 'warning', 'lodging_booking_check-customer', ['id' => $params['id']], $links, null, $booking['center_office_id']);

    $httpResponse->status(qn_error_http(QN_ERROR_MISSING_PARAM));
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('lodging.booking.customer.uncomplete', 'lodging\sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();
