<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use lodging\sale\booking\Invoice;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "Emit a new invoice from an existing proforma and update related booking, if necessary.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the invoice to emit.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['finance.default.user', 'booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'auth']
]);
/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $om
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $om, $cron, $auth) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['auth']];

$invoice = Invoice::id($params['id'])
    ->read(['id', 'state', 'deleted', 'date', 'status', 'type', 'is_deposit', 'price', 'booking_id', 'has_orders', 'invoice_lines_ids'])
    ->first(true);

if(!$invoice) {
    throw new Exception("unknown_invoice", QN_ERROR_UNKNOWN_OBJECT);
}

if($invoice['deleted'] || $invoice['state'] != 'instance' || $invoice['status'] != 'proforma') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if(count($invoice['invoice_lines_ids']) <= 0) {
    throw new Exception("empty_invoice", QN_ERROR_INVALID_PARAM);
}

$year = date('Y', $invoice['date']);

$fiscal_year = Setting::get_value('finance', 'invoice', 'fiscal_year');

if(!$fiscal_year) {
    throw new Exception('missing_fiscal_year', EQ_ERROR_INVALID_CONFIG);
}

if(intval($year) != intval($fiscal_year)) {
    throw new Exception('fiscal_year_mismatch', EQ_ERROR_CONFLICT_OBJECT);
}

if($invoice['has_orders']) {
    // emit the invoice
    Invoice::id($params['id'])
        // #memo - changing status will trigger an invoice number assignation
        ->update(['status' => 'invoice'])
        // force recomputing the is_paid status
        ->update(['is_paid' => true]);
}
elseif(!is_null($invoice['booking_id'])) {
    // check booking status
    $booking = Booking::id($invoice['booking_id'])
        ->read([
                'id',
                'name',
                'status',
                'price',
                'type',
                'is_deposit',
                'reversed_invoice_id',
                'invoices_ids' => [
                    'id', 'date', 'type', 'status', 'price'
                ]
            ])
        ->first(true);

    if(!$booking) {
        throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
    }

    foreach($booking['invoices_ids'] as $id => $booking_invoice) {
        if($booking_invoice['id'] != $invoice['id'] && $booking_invoice['status'] == 'proforma' && $booking_invoice['type'] == $invoice['type'] && $booking_invoice['date'] <= $invoice['date']) {
            throw new Exception("existing_previous_invoice", QN_ERROR_INVALID_PARAM);
        }
    }

    // prevent emitting a balance invoice if there is still a proforma credit note
    if(!$invoice['is_deposit'] && $invoice['type'] == 'invoice') {
        $credit_note = Invoice::search([
                ['booking_id', '=', $invoice['booking_id']],
                ['type', '=', 'credit_note'],
                ['status', '=', 'proforma']
            ])
            ->read(['id'])
            ->first(true);

        if($credit_note) {
            throw new Exception("pending_credit_note", QN_ERROR_INVALID_PARAM);
        }
    }

    // #memo - a booking might have several invoices (several deposit invoices, but only one balance invoice / credit note can be emitted at any time)
    if(!$invoice['is_deposit'] && $invoice['type'] == 'invoice' && $booking['status'] != 'invoiced') {
        throw new Exception("incompatible_booking_status", QN_ERROR_INVALID_PARAM);
    }

    // #memo - we must allow the creation of balanced invoice of any amount (null, positive, negative)

    // if we're emitting a balance invoice and a credit note had been created before
    // the credit note must be marked as paid
    // and its funding (if any) must be removed or, if partially paid, adjusted to paid amount
    if($invoice['type'] == 'invoice' && !$invoice['is_deposit']) {
        $credit_notes = Invoice::search([
                ['booking_id', '=', $invoice['booking_id']],
                ['type', '=', 'credit_note'],
            ])
            ->read(['id', 'funding_id'])
            ->get(true);

        foreach($credit_notes as $credit_note) {
            $funding = Funding::id($credit_note['funding_id'])->read(['id', 'due_amount', 'paid_amount'])->first(true);
            if(round($funding['paid_amount'], 2) == 0) {
                Funding::id($credit_note['funding_id'])->delete(true);
                Invoice::id($credit_note['id'])->update(['is_paid' => true]);
            }
            else {
                Funding::id($credit_note['funding_id'])
                    ->update(['due_amount'=> $funding['paid_amount']])
                    ->update(['is_paid' => true]);
            }
        }
    }

    // check total of emitted non-cancelled invoices
    $sum_invoices = ($invoice['status'] == 'invoice' && $invoice['type'] == 'invoice')?$invoice['price']:0.0;
    foreach($booking['invoices_ids'] as $oid => $odata) {
        if($odata['status'] == 'invoice' && $type == 'invoice') {
            $sum_invoices += ($odata['type'] == 'invoice')? $odata['price'] : -($odata['price']);
        }
    }

    if(round($sum_invoices, 2) > round($booking['price'], 2)) {
        throw new Exception("exceeding_booking_price", QN_ERROR_INVALID_PARAM);
    }

    // emit the invoice
    Invoice::id($params['id'])
        // #memo - changing status will trigger an invoice number assignation
        ->update(['status' => 'invoice'])
        // force recomputing the is_paid status
        ->update(['is_paid' => null]);

    // assign invoice to a new funding, delete non-invoiced non-paid funding, and attach non-invoiced partially paid fundings to emitted invoice
    eQual::run('do', 'lodging_invoice_do-funding', ['id' => $params['id']]);

    if($invoice['type'] == 'invoice' && !$invoice['is_deposit']) {
        // mark the booking as invoiced, whatever its status (will trigger updating booking lines as is_invoiced)
        Booking::id($invoice['booking_id'])->update(['is_invoiced' => true]);
    }
    elseif($invoice['type'] == 'credit_note' && !$invoice['is_deposit']) {
        // mark the booking as not invoiced (will trigger updating booking lines as is_invoiced)
        Booking::id($invoice['booking_id'])->update(['is_invoiced' => false]);
    }

    Booking::updateStatusFromFundings($om, (array) $invoice['booking_id'], [], 'en');
}
else {
    // no order_id and no booking_id : error
    throw new Exception('invalid_invoice', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
        ->status(205)
        ->send();
