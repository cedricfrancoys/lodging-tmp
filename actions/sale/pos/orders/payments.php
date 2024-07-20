<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\pos\Order;
use lodging\sale\booking\Payment;

list($params, $providers) = eQual::announce([
    'description'   => "Generates a list of payments relating to the given orders.",
    'help'          => "This script is meant to be called after invoice creation, so that orders can no longer be updated (in case of error while encoding in the cashdesk).",
    'params'        => [
        'ids' =>  [
            'description'   => 'List of Order identifiers.',
            'type'          => 'array',
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['pos.default.user', 'pos.default.administrator', 'admins'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$result = [
    'created'   => 0,
    'ignored'   => 0,
    'logs'      => []
];

// map customer to dedicated "vente comptoir"
$map_centers_customers = [
    24 => 9,    // EUPEN
    25 => 11,   // VSG
    26 => 8,    // HSL
    27 => 13,   // OVIFAT
    28 => 12,   // LLN
    29 => 6,    // ROCHEFORT
    30 => 7,    // WANNE
    32 => 14    // HVG
];


$orders = Order::ids($params['ids'])
    ->read([
        'id', 'name', 'status', 'created',
        'center_id' => ['id', 'center_office_id'],
        'invoice_id',
        'funding_id',
        'booking_id',
        'customer_id',
        'is_exported',
        'order_payments_ids' => [
            'id',
            'total_due',
            'payments_ids',
            'order_payment_parts_ids' => [ 'payment_method' ]
        ]
    ])
    ->get(true);

foreach($orders as $order) {
    // consider only paid orders
    if($order['status'] != 'paid') {
        ++$result['ignored'];
        $result['logs'][] = "INFO- ignoring non-paid order [{$order['id']}]";
        continue;
    }
    // ignore order not relating to a cashdesk sale ("vente comptoir")
    if($order['funding_id'] || $order['booking_id']) {
        ++$result['ignored'];
        $result['logs'][] = "INFO- ignoring booking order [{$order['id']}]";
        continue;
    }
    // ignore non-invoiced orders
    if(!$order['invoice_id']) {
        ++$result['ignored'];
        $result['logs'][] = "INFO- ignoring non-invoiced order [{$order['id']}]";
        continue;
    }
    if($order['is_exported']) {
        ++$result['ignored'];
        $result['logs'][] = "INFO- ignoring already exported order [{$order['id']}]";
        continue;
    }
    try {
        // retrieve customer id
        $customer_id = $map_centers_customers[$order['center_id']['id']];

        // create payment(s)
        foreach($order['order_payments_ids'] as $order_payment) {

            if($order_payment['payments_ids'] && count($order_payment['payments_ids'])) {
                // order payment has already generated a payment
                continue;
            }
            // find out resulting payment method
            $payment_method = 'bank_card';
            foreach($order_payment['order_payment_parts_ids'] as $part) {
                if($part['payment_method'] == 'cash') {
                    $payment_method = 'cash';
                    break;
                }
            }
            // payment relates to a funding : create a payment attached to that funding
            $payment = Payment::create([
                    'partner_id'        => $customer_id,
                    'center_office_id'  => $order['center_id']['center_office_id'],
                    'amount'            => $order_payment['total_due'],
                    'receipt_date'      => $order['created'],
                    'payment_origin'    => 'cashdesk',
                    'payment_method'    => $payment_method,
                    'status'            => 'paid',
                    'order_payment_id'  => $order_payment['id']
                ])
                ->read(['id'])
                ->first(true);

            ++$result['created'];
            $result['logs'][] = "OK  - created payment [{$payment['id']}] for order [{$order['id']}]";
        }

        // mark order as no longer requiring payment export
        Order::id($order['id'])->update(['is_exported' => true]);
    }
    catch(Exception $e) {
        // ignore errors (must be resolved manually)
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
