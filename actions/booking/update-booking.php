<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Update a booking when its status is `option` and has reached expiry. This script is meant to be scheduled by `do-option` controller.",
    'params' 		=>	[
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'free_rental_units' =>  [
            'description'   => 'Flag for marking reserved rental units to be release immediately, if any.',
            'type'          => 'boolean',
            'default'       => false
        ]
    ],
    'access' => [
        'visibility'        => 'private'
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
                  ->read(['id', 'name', 'status', 'is_noexpiry'])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'option') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}


if($booking['is_noexpiry']) {
    // do nothing (remain as option) - we shouldn't have reached this code!
}
else {
    // revert to quote
    eQual::run('do', 'lodging_booking_do-quote', [
        'id'                    => $params['id'],
        'free_rental_units'     => $params['free_rental_units']
    ]);
    if($params['free_rental_units']) {
        // send an alert saying that option has expired and reverted to quote
        $dispatch->dispatch('lodging.booking.option.expired', 'lodging\sale\booking\Booking', $params['id'], 'important');
    }
    else {
        // check quote for blocked rental units (might raise alert lodging.booking.quote.blocking)
        eQual::run('do', 'lodging_booking_check-quote', ['id' => $params['id']]);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
