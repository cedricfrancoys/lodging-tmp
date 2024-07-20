<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Payment;

list($params, $providers) = eQual::announce([
    'description'   => "Remove the manual payment attached to the funding, if any, and unmark funding as paid.",
    'help'          => "Manual payments can be undone while the booking is not fully balanced (and invoiced).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted funding.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user', 'sale.default.administrator']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $om
 */
list($context, $om) = [ $providers['context'], $providers['orm'] ];

$funding = Funding::id($params['id'])
            ->read(['booking_id' => ['id', 'status', 'customer_id'], 'invoice_id', 'center_office_id', 'is_paid', 'paid_amount', 'due_amount'])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$funding['is_paid']) {
    throw new Exception("funding_not_paid", QN_ERROR_INVALID_PARAM);
}

if($funding['booking_id']['status'] == 'balanced') {
    throw new Exception("booking_balanced", QN_ERROR_INVALID_PARAM);
}

// remove manuel payments, if any
$payments = Payment::search([
        ['funding_id', '=', $funding['id']],
        ['is_manual', '=', true]
    ])
    ->delete(true);

Funding::id($params['id'])
    ->update(['paid_amount' => null])
    ->update(['is_paid' => null]);

Booking::updateStatusFromFundings($om, (array) $funding['booking_id']['id'], [], 'en');

$context->httpResponse()
        ->status(205)
        ->send();
