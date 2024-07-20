<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Arbitrary mark a funding as paid for the booking. Applies only on bookings that are not from channel manager.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted funding.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'confirm' =>  [
            'description'   => 'Manual confirmation.',
            'type'          => 'boolean',
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
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $om
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $om, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

if(!$params['confirm']) {
    throw new Exception('missing_confirmation', EQ_ERROR_MISSING_PARAM);
}

$funding = Funding::id($params['id'])
    ->read([
        'booking_id' =>[
            'has_tour_operator',
            'is_from_channelmanager',
            'center_id' => [
                'id', 'sojourn_type_id' => ['id', 'name']
            ]
        ],
        'type', 'is_paid', 'due_amount', 'paid_amount'
    ])
    ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

if($funding['is_paid']) {
    throw new Exception("funding_already_paid", QN_ERROR_INVALID_PARAM);
}

$delta = abs(round($funding['due_amount'], 2) - round($funding['paid_amount'], 2));
if($delta <= 0) {
    throw new Exception("nothing_to_pay", QN_ERROR_INVALID_PARAM);
}

$booking = $funding['booking_id'];
if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['is_from_channelmanager']) {
    throw new Exception("invalid_booking_type", QN_ERROR_INVALID_PARAM);
}

Funding::id($params['id'])->update(['paid_amount' => $funding['due_amount'],'is_paid' => true]);

Booking::updateStatusFromFundings($om, (array) $booking['id'], [], 'en');

$context->httpResponse()
        ->status(204)
        ->send();
