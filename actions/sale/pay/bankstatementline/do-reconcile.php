<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\BankStatementLine;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Attempt to reconcile a BankStatementLine using its communication (VCS or free-text message).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the BankStatementLine to reconcile.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['finance.default.user', 'sale.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $dispatch) = [$providers['context'], $providers['orm'], $providers['dispatch']];

$line = BankStatementLine::id($params['id'])->read(['id', 'structured_message', 'message'])->first(true);

$booking_name = substr($line['structured_message'], 4, 6);
$booking_extref_id = preg_replace('/[^0-9]/', '', $line['message']);

$booking_before = Booking::search([[['name', '=', $booking_name]], [['extref_reservation_id', '=', $booking_extref_id]]])
    ->read(['id', 'status', 'center_office_id'])
    ->first(true);

$orm->call(BankStatementLine::getType(), 'reconcile', (array) $params['id']);

if($booking_before) {
    $booking_after = Booking::id($booking_before['id'])
        ->read(['status'])
        ->first(true);

    if($booking_before['status'] != $booking_after['status']) {
        $dispatch->dispatch('lodging.booking.payment.overpaid', 'lodging\sale\booking\Booking', $booking_before['id'], 'important', null, [], [], null, $booking_before['center_office_id']);
    }
    else{
        $dispatch->cancel('lodging.booking.payment.overpaid', 'lodging\sale\booking\Booking', $booking_before['id']);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
