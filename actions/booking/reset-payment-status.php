<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Update the payment status of all non-balanced bookings. This controller is meant to be run by CRON on a daily basis.",
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context    $context
 */
list($context) = [$providers['context']];

/*
    Update booking status for all bookings that are not balanced yet.
*/
Booking::search([['state', '=', 'instance'], ['status', '<>', 'balanced']])->update(['payment_status' => null]);

$context->httpResponse()
        ->status(204)
        ->send();