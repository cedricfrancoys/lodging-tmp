<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\Funding;
use lodging\sale\booking\Payment;
use lodging\sale\pos\Order;
use lodging\sale\pos\Operation;

list($params, $providers) = announce([
    'description'   => "This will mark the order as paid, and update fundings and bookings involved in order lines, if any.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the order that has been paid.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user', 'pos.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

// read order object
$order = Order::id($params['id'])
    ->read([
        'id', 'creator', 'created', 'name', 'price', 'status', 'has_invoice', 'invoice_id', 'customer_id', 'session_id',
        'has_funding',
        'funding_id' => [
            'id',
            'type',
            'invoice_id',
            'booking_id' => ['id', 'customer_id']
        ],
        'center_id' => [
            'center_office_id'
        ],
        'order_payments_ids' => [
            'total_due',
            'order_lines_ids' => [
                'product_id',
                'qty',
                'unit_price',
                'vat_rate',
                'discount',
                'free_qty'
            ],
            'order_payment_parts_ids' => [
                'booking_id', 'funding_id', 'amount', 'receipt_date', 'payment_origin', 'payment_method', 'status', 'voucher_ref', 'center_office_id'
            ]
        ]
    ])
    ->first(true);

if(!$order) {
    throw new Exception("unknown_order", QN_ERROR_UNKNOWN_OBJECT);
}

// order already paid
if($order['status'] == 'paid') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

/*
    Handle products (lines) that must be added as extra on a booking
*/

// check that all referenced fundings are (still) present
foreach($order['order_payments_ids'] as $pid => $payment) {
    foreach($payment['order_payment_parts_ids'] as $oid => $part) {
        if($part['funding_id'] > 0) {
            $funding = Funding::id($part['funding_id'])->read(['id'])->first(true);
            if(!$funding) {
                throw new Exception('missing_funding', EQ_ERROR_UNKNOWN_OBJECT);
            }
        }
    }
}

// check cash-in / cash-out
$total_cash_received = 0.0;
$cash_in = 0.0;
$cash_out = 0.0;
foreach($order['order_payments_ids'] as $pid => $payment) {
    foreach($payment['order_payment_parts_ids'] as $oid => $part) {
        $amount = round($part['amount'], 2);
        $total_cash_received += $amount;
        if($part['payment_method'] == 'cash') {
            if($amount > 0) {
                $cash_in += $amount;
            }
            else {
                $cash_out -= $amount;
            }
        }
    }
}
// create cash-in / cash-out operations, if any
if($cash_in > 0.0) {
    Operation::create([
        'amount'        => $cash_in,
        'type'          => 'sale',
        'session_id'    => $order['session_id'],
        'user_id'       => $order['creator']
    ]);
}
// create cash-in / cash-out operations, if any
if($cash_out < 0.0) {
    Operation::create([
        'amount'        => $cash_out,
        'type'          => 'sale',
        'session_id'    => $order['session_id'],
        'user_id'       => $order['creator']
    ]);
}
// create sale cash-out operation (returned change), if any
if($total_cash_received > $order['price']) {
    Operation::create([
        'amount'        => -round($total_cash_received - $order['price'], 2),
        'type'          => 'sale',
        'session_id'    => $order['session_id'],
        'user_id'       => $order['creator']
    ]);
}

// create payment based on selected funding if any, or update Booking related to the paymentPart
// loop through order lines to check for payment method voucher/booking_id if any
foreach($order['order_payments_ids'] as $pid => $payment) {
    if($order['has_funding']) {
        $payment_method = 'bank_card';
        foreach($payment['order_payment_parts_ids'] as $oid => $part) {
            if($part['payment_method'] == 'cash') {
                $payment_method = 'cash';
                break;
            }
        }
        // payment relates to a funding : create a payment attached to that funding
        Payment::create([
                'booking_id'        => $order['funding_id']['booking_id']['id'],
                'partner_id'        => $order['funding_id']['booking_id']['customer_id'],
                'center_office_id'  => $order['center_id']['center_office_id'],
                'amount'            => $payment['total_due'],
                'receipt_date'      => $order['created'],
                'payment_origin'    => 'cashdesk',
                'payment_method'    => $payment_method,
                'status'            => 'paid',
                'order_payment_id'  => $pid
            ])
            ->update([
                'funding_id'        => $order['funding_id']['id']
            ]);
    }
    else {
        // find out if the payment was made through a booking
        $booking_id = 0;
        foreach($payment['order_payment_parts_ids'] as $oid => $part) {
            if($part['payment_method'] == 'booking' && $part['booking_id'] > 0) {
                $booking_id = $part['booking_id'];
                break;
            }
        }
        // add lines as extra consumption on the targeted booking
        if($booking_id) {
            // find any existing 'extra' group id
            $groups_ids = BookingLineGroup::search([['booking_id', '=', $booking_id], ['is_extra', '=', true], ['is_autosale', '=', false]])->ids();
            if($groups_ids > 0 && count($groups_ids)) {
                $group_id = reset(($groups_ids));
            }
            // no 'extra' group: create one
            else {
                $new_group = BookingLineGroup::create(['name' => 'Suppléments', 'booking_id' => $booking_id, 'is_extra' => true])->first(true);
                $group_id = $new_group['id'];
            }
            // create booking lines according to order lines
            foreach($payment['order_lines_ids'] as $lid => $line) {
                // create a new line in the 'extra' group
                $new_line = BookingLine::create([
                        'booking_id'            => $booking_id,
                        'booking_line_group_id' => $group_id
                    ])
                    ->update(['product_id' => $line['product_id']])
                    ->first(true);
                // #memo - at creation booking_line qty is always set accordingly to its parent group nb_pers
                BookingLine::id($new_line['id'])
                    // #memo - setting qty reset prices
                    ->update([
                        'qty'           => $line['qty']
                    ])
                    ->update([
                        'unit_price'    => $line['unit_price'],
                        'vat_rate'      => $line['vat_rate']
                    ]);
            }
            Booking::id($booking_id)->update(['price' => null, 'total' => null]);
        }
    }
}

// mark the order as paid
Order::id($params['id'])->update(['status' => 'paid']);

// customer requested an invoice: generate an invoice for the order (only if payment do not relate to a funding)
if(!$order['has_funding']) {
    if($order['has_invoice']) {
        try {
            // #memo - invoices must relate to a booking (and not to an order)
            // eQual::run('do', 'lodging_order_do-invoice', ['id' => $params['id']]);
        }
        catch(Exception $e) {
            // ignore errors when trying to issue an invoice (some restrictions apply)
            trigger_error("ORM::".$e->getMessage(), QN_REPORT_WARNING);
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
