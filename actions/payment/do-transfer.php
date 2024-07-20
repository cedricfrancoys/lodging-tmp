<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Payment;
use lodging\sale\booking\Funding;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Transfers a payment from one funding to another, on the same booking or another booking from the same customer.",
    'help'          => "After the operation, status of involved fundings and bookings are updated. This action is intended to be exclusively called on `lodging\sale\booking\Payment` entities.",
    'params'        => [
        'id' =>  [
            'description'    => 'Identifier of the targeted funding.',
            'type'           => 'many2one',
            'foreign_object' => 'lodging\sale\booking\Payment',
            'required'       => true
        ],
        'funding_id' =>  [
            'description'    => 'Target funding.',
            'type'           => 'many2one',
            'foreign_object' => 'lodging\sale\booking\Funding',
            'domain'         => ['booking_id', 'in', 'object.booking_id.customer_id.bookings_ids'],
            'required'       => true
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
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

$payment = Payment::id($params['id'])->read(['id', 'amount', 'funding_id', 'booking_id', 'center_office_id'])->first();

if(!$payment) {
    throw new Exception('unknown_payment', EQ_ERROR_INVALID_PARAM);
}

$funding = Funding::id($params['funding_id'])->read(['id', 'booking_id'])->first();

if(!$funding) {
    throw new Exception('unknown_funding', EQ_ERROR_INVALID_PARAM);
}

if($funding['id'] == $payment['funding_id']) {
    throw new Exception('same_src_and_dest', EQ_ERROR_INVALID_PARAM);
}

if($funding['booking_id'] == $payment['booking_id']) {
    Payment::id($payment['id'])->update(['funding_id' => $funding['id']]);
    Funding::ids([$funding['id'], $payment['funding_id']])->update(['paid_amount' => null, 'is_paid' => null]);
}
else {
    $booking_src  = Booking::id($payment['booking_id'])->read(['id', 'name'])->first(true);
    $booking_dest = Booking::id($funding['booking_id'])->read(['id', 'name'])->first(true);

    // create negative financing in the original booking
    $values = [
            'booking_id'            => $payment['booking_id'],
            'center_office_id'      => $payment['center_office_id'],
            'due_amount'            => -$payment['amount'],
            'type'                  => 'installment',
            'order'                 => 1,
            'due_date'              => time(),
            'description'           => 'Transfert vers la réservation '.$booking_dest['name']
        ];
    Funding::create($values)
        ->update(['paid_amount' => -$payment['amount']])
        ->update(['is_paid'     => true])
        ->read(['name']);

    // create funding for an amount equivalent to the initial payment on the destination reservation
    $values = [
            'booking_id'            => $funding['booking_id'],
            'center_office_id'      => $payment['center_office_id'],
            'due_amount'            => $payment['amount'],
            'is_paid'               => true,
            'type'                  => 'installment',
            'order'                 => 1,
            'due_date'              => time(),
            'description'           => 'Transfert depuis la réservation '.$booking_src['name']
        ];
    Funding::create($values)
        ->update(['paid_amount' => $payment['amount']])
        ->update(['is_paid'     => true])
        ->read(['name']);

    // reset computed fields and update status for involved bookings
    Booking::ids([$funding['booking_id'], $payment['booking_id']])
        ->update([
            'paid_amount'       => null,
            'payment_status'    => null
        ]);

    Booking::updateStatusFromFundings($orm, [$funding['booking_id'], $payment['booking_id']], [], 'en');
}

$context->httpResponse()
        ->status(204)
        ->send();
