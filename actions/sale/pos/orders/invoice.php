<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\pos\Order;
use lodging\finance\accounting\Invoice;
use lodging\finance\accounting\InvoiceLine;
use lodging\finance\accounting\InvoiceLineGroup;
use lodging\identity\Center;

list($params, $providers) = eQual::announce([
    'description'   => "Generates an invoice with all cashdesk orders made by the given Center for a given month.",
    'params'        => [
        'domain' =>  [
            'description'   => 'Domain to limit the result set (specifying a month is mandatory).',
            'type'          => 'array',
            'default'       => []
        ],
        'params' =>  [
            'description'   => 'Additional params, if any',
            'type'          => 'array',
            'default'       => []
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
    'providers'     => ['context', 'orm', 'adapt']
]);

list($context, $orm, $adapter) = [$providers['context'], $providers['orm'], $providers['adapt']];

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

if(isset($params['params']['all_months'])) {
    $all_months = $adapter->adapt($params['params']['all_months'], 'bool');
    if($all_months) {
        throw new Exception('missing_month', EQ_ERROR_INVALID_PARAM);
    }
}

if(!isset($params['params']['center_id'])) {
    throw new Exception('missing_center', EQ_ERROR_INVALID_PARAM);
}
else {
    $center_id = $adapter->adapt($params['params']['center_id'], 'int');
    if($center_id <= 0) {
        throw new Exception('missing_center', EQ_ERROR_INVALID_PARAM);
    }
}

if(!isset($map_centers_customers[$center_id])) {
    throw new Exception('unsupported_center', EQ_ERROR_INVALID_PARAM);
}

$center = Center::id($center_id)->read(['organisation_id', 'center_office_id'])->first(true);

if(!$center) {
    throw new Exception('missing_center', EQ_ERROR_UNKNOWN_OBJECT);
}

if(!isset($params['params']['date'])) {
    throw new Exception('missing_month', EQ_ERROR_INVALID_PARAM);
}

$date = $adapter->adapt($params['params']['date'], 'date');
if(is_null($date) || $date <= 0) {
    throw new Exception('missing_month', EQ_ERROR_INVALID_PARAM);
}

$first_date = strtotime(date('Y-m-01 00:00:00', $date));
$last_date = strtotime('first day of next month', $first_date);

// search cashdesk orders ("vente comptoir") - not related to a booking
$orders = Order::search([
        ['status', '=', 'paid'],
        ['price', '>', 0],
        ['funding_id', '=', null],
        ['booking_id', '=', null],
        ['invoice_id', '=', null],
        ['center_id', '=', $center_id],
        // #memo - we do not use start date to make sure that any passed order not yet invoiced is included
        ['created', '<', $last_date],
        ['created', '>=', strtotime('2024-04-01 00:00:00')]
    ])
    ->read([
        'id', 'name', 'status', 'created',
        'customer_id',
        'order_lines_ids' => [
            'product_id' => ['id', 'name'],
            'price_id',
            'vat_rate',
            'unit_price',
            'qty',
            'free_qty',
            'discount',
            'price',
            'total'
        ]
    ])
    ->get(true);

// retrieve customer id
$customer_id = $map_centers_customers[$center_id];

// create invoice and invoice lines
$invoice = Invoice::create([
        'date'              => time(),
        'organisation_id'   => $center['organisation_id'],
        'center_office_id'  => $center['center_office_id'],
        'status'            => 'proforma',
        'partner_id'        => $customer_id,
        'has_orders'        => true
    ])
    ->read(['id'])
    ->first(true);

$invoice_line_group = InvoiceLineGroup::create([
        'name'              => 'Ventes comptoir',
        'invoice_id'        => $invoice['id']
    ])
    ->read(['id'])
    ->first(true);

$orders_ids = [];

foreach($orders as $order) {
    // check order consistency
    if($order['status'] != 'paid') {
        continue;
    }
    try {
        $orders_ids[] = $order['id'];
        // create invoice lines
        foreach($order['order_lines_ids'] as $line) {
            // create line in several steps (not to overwrite final values from the line - that might have been manually adapted)
            InvoiceLine::create([
                    'invoice_id'                => $invoice['id'],
                    'invoice_line_group_id'     => $invoice_line_group['id'],
                    'product_id'                => $line['product_id']['id'],
                    'description'               => $line['product_id']['name'],
                    'price_id'                  => $line['price_id']
                ])
                ->update([
                    'vat_rate'                  => $line['vat_rate'],
                    'unit_price'                => $line['unit_price'],
                    'qty'                       => $line['qty'],
                    'free_qty'                  => $line['free_qty'],
                    'discount'                  => $line['discount']
                ])
                ->update([
                    'total'                     => $line['total']
                ])
                ->update([
                    'price'                     => $line['price']
                ]);
        }
        // attach the invoice to the Order, and mark it as having an invoice
        Order::id($order['id'])->update(['invoice_id' => $invoice['id']]);
    }
    catch(Exception $e) {
        // ignore errors (must be resolved manually)
    }
}

// create (exportable) payments for involved orders
// #memo - waiting to be confirmed (the teams to be ready for the accounting)
if($center_id == 27 && $date >= strtotime('2024-04-01 00:00:00')) {
    eQual::run('do', 'lodging_sale_pos_orders_payments', [
            'ids' => $orders_ids
        ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
