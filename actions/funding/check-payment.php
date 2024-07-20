<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "Checks that a given funding has been paid (should be scheduled on due_date).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the funding to check.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $auth, $dispatch) = [ $providers['context'], $providers['auth'], $providers['dispatch'] ];

// switch to root account (access is 'private')
$auth->su();

$funding = Funding::id($params['id'])->read(['id', 'is_paid','due_amount', 'due_date', 'booking_id' => ['id', 'center_office_id']])->first();

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$funding['is_paid'] && $funding['due_amount'] > 0 ) {
    // dispatch a message for notifying users
    $dispatch->dispatch('lodging.booking.payments', 'lodging\sale\booking\Booking', $funding['booking_id']['id'], 'warning', null, [], [], null, $funding['booking_id']['center_office_id']);

    try {
       eQual::run('do', 'lodging_funding_remind-payment', ['id' => $params['id']]);
    }
    catch(Exception $e) {
       // something went wrong : ignore
    }
}

$context->httpResponse()
        ->status(204)
        ->send();