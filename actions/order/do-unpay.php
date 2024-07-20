<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\pos\Operation;
use lodging\sale\pos\Order;

list($params, $providers) = announce([
    'description'   => 'This will mark the order as in payment.',
    'params'        => [
        'id' =>  [
            'description' => 'Identifier of the order that has been unpaid.',
            'type'        => 'integer',
            'min'         => 1,
            'required'    => true
        ]
    ],
    'access'        => [
        'groups' => ['booking.default.user', 'pos.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$order = Order::id($params['id'])
    ->read([
        'creator',
        'session_id',
        'status',
        'has_funding',
        'invoice_id' => ['status'],
        'order_payments_ids' => [
            'order_payment_parts_ids' => ['payment_method', 'amount']
        ]
    ])
    ->first();

if(!$order) {
    throw new Exception('unknown_order', QN_ERROR_UNKNOWN_OBJECT);
}

if($order['status'] !== 'paid') {
    throw new Exception('incompatible_status', QN_ERROR_INVALID_PARAM);
}

if(isset($order['invoice_id']['status']) && $order['invoice_id']['status'] === 'invoice') {
    throw new Exception('is_invoiced', QN_ERROR_NOT_ALLOWED);
}

if($order['has_funding']) {
    throw new Exception('has_funding', QN_ERROR_NOT_ALLOWED);
}

if(count(array_filter(
            $order['order_payments_ids'][0]['order_payment_parts_ids'] ?? [],
            function($part) { return $part['payment_method'] === 'booking'; }
        ))
    ) {
    throw new Exception('payment_by_booking', QN_ERROR_NOT_ALLOWED);
}

foreach($order['order_payments_ids'] as $pid => $payment) {

    $cash_in = 0;
    $cash_out = 0;

    foreach($payment['order_payment_parts_ids'] as $oid => $part) {
        if($part['payment_method'] == 'cash') {
            $amount = round($part['amount'], 2);
            if($amount > 0) {
                $cash_in += $amount;
            }
            else {
                $cash_out -= $amount;
            }
        }
    }

    if($cash_in > 0) {
        $operation = Operation::search([
            ['amount', '=', $cash_in],
            ['type', '=', 'sale'],
            ['session_id', '=', $order['session_id']],
            ['user_id', '=', $order['creator']]
        ])
            ->read(['id'])
            ->first();

        Operation::id($operation['id'])->delete(true);
    }

    if($cash_out < 0) {
        $operation = Operation::search([
            ['amount', '=', $cash_out],
            ['type', '=', 'sale'],
            ['session_id', '=', $order['session_id']],
            ['user_id', '=', $order['creator']]
        ])
            ->read(['id'])
            ->first();

        Operation::id($operation['id'])->delete(true);
    }
}

Order::id($params['id'])->update(['status' => 'payment']);

$context->httpResponse()
        ->status(204)
        ->send();
