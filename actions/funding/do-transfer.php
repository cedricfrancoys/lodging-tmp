<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Transfers a funding from one booking to another booking from the same customer.",
    'help'          => "After the operation. Involved bookings statuses are updated. This action is intended to be exclusively called on `lodging\sale\booking\Funding` entities.",
    'params'        => [
        'id' =>  [
            'description'    => 'Identifier of the targeted funding.',
            'type'           => 'many2one',
            'foreign_object' => 'lodging\sale\booking\Funding',
            'required'       => true
        ],
        'booking_id' =>  [
            'description'    => 'Target booking.',
            'type'           => 'many2one',
            'foreign_object' => 'lodging\sale\booking\Booking',
            'domain'         => ['customer_id', '=', 'object.booking_id.customer_id'],
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

$funding = Funding::id($params['id'])->read(['id', 'type', 'booking_id'])->first();

if(!$funding) {
    throw new Exception('unknown_funding', EQ_ERROR_INVALID_PARAM);
}

// fundings related to invoices cannot be transferred
if($funding['type'] == 'invoice') {
    throw new Exception('invalid_funding_type', EQ_ERROR_INVALID_PARAM);
}

// retrieve the target booking
$booking = Booking::id($params['booking_id'])->read(['id'])->first();

if(!$booking) {
    throw new Exception('unknown_booking', EQ_ERROR_INVALID_PARAM);
}

if($booking['id'] == $funding['booking_id']) {
    throw new Exception('same_src_and_dest', EQ_ERROR_INVALID_PARAM);
}

Funding::id($funding['id'])->update(['booking_id' => $booking['id']]);

// reset computed fields and update status for involved bookings

Booking::ids([$booking['id'], $funding['booking_id']])
    ->update([
        'paid_amount'       => null,
        'payment_status'    => null
    ]);

Booking::updateStatusFromFundings($orm, [$booking['id'], $funding['booking_id']], [], 'en');

$context->httpResponse()
        ->status(204)
        ->send();
