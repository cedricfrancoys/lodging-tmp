<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

use equal\orm\usages\UsagePhone;

list($params, $providers) = announce([
    'description'   => "Check if the reservation has at least 1 contact with a phone number.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against confirm.',
            'type'          => 'integer',
            'required'      => true
        ]
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
                'id', 'center_office_id', 'status', 'contacts_ids' => ['id','phone','mobile']
        ])
        ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$count_phone = 0;
if($booking['contacts_ids']) {
    foreach($booking['contacts_ids'] as $id => $contact) {
        if (($contact['phone'] &&  strlen($contact['phone'])>=7) ) {
            ++$count_phone;
            break;
        }
        if ($contact['mobile'] &&  strlen($contact['mobile'])>=7) {
            ++$count_phone;
            break;
        }
    }
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];
$httpResponse = $context->httpResponse()->status(200);


if($count_phone == 0) {
    $result[] = $booking['id'];

    $dispatch->dispatch('lodging.booking.confirm', 'lodging\sale\booking\Booking', $params['id'], 'warning', 'lodging_booking_check-contacts', ['id' => $params['id']], [], null, $booking['center_office_id']);
}
else {
    $dispatch->cancel('lodging.booking.confirm', 'lodging\sale\booking\Booking', $params['id']);
}


$httpResponse->body($result)
             ->send();


