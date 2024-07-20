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
    'description'   => "Create a manual payment to complete the payments of a funding and mark it as paid.",
    'help'          => "This action is intended for payment with bank card only. Manual payments can be undone while the booking is not fully balanced (and invoiced).",
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
            ->read(['booking_id' => ['id', 'customer_id'], 'invoice_id', 'center_office_id', 'is_paid', 'paid_amount', 'due_amount'])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

if($funding['is_paid']) {
    throw new Exception("funding_already_paid", QN_ERROR_INVALID_PARAM);
}

$sign = ($funding['due_amount'] >= 0)? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", QN_ERROR_INVALID_PARAM);
}

Payment::create([
        'booking_id'        => $funding['booking_id']['id'],
        'partner_id'        => $funding['booking_id']['customer_id'],
        'center_office_id'  => $funding['center_office_id'],
        'is_manual'         => true,
        'amount'            => $sign * $remaining_amount,
        'payment_origin'    => 'cashdesk',
        'payment_method'    => 'bank_card'
    ])
    ->update([
        'funding_id'        => $funding['id']
    ]);

// #memo - this is done in onupdateFundingId handler
// Funding::id($params['id'])->update(['is_paid' => true]);
// Booking::updateStatusFromFundings($om, (array) $funding['booking_id']['id'], [], 'en');

$context->httpResponse()
        ->status(205)
        ->send();
