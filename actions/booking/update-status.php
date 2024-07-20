<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

// announce script and fetch parameters values
list($params, $providers) = announce([
    'description'	=>	"Update a booking status after balance invoice has been emitted.",
    'params' 		=>	[
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
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'orm', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
list($context, $orm, $dispatch) = [ $providers['context'], $providers['orm'], $providers['dispatch'] ];


// read booking object
$booking = Booking::id($params['id'])
                  ->read(['id', 'status', 'is_invoiced'])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// #memo - is_invoiced seems not adding any additional information (read as balance invoice has been emitted, which is the same as having a status from 'invoiced' and beyond)
if(/*!$booking['is_invoiced'] || */!in_array($booking['status'], ['invoiced', 'credit_balance', 'debit_balance'])) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}


Booking::updateStatusFromFundings($orm, (array) $params['id']);


$context->httpResponse()
        ->status(204)
        ->send();
