<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Imports or reimports contacts of the customer to the booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
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
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 */
list($context, $orm) = [$providers['context'], $providers['orm']];


// read booking object
$booking = Booking::id($params['id'])->read(['id'])->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// re-create contacts
$orm->call(Booking::getType(), 'createContacts', $params['id']);


$context->httpResponse()
        ->status(204)
        ->send();