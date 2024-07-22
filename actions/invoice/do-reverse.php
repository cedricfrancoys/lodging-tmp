<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;
use lodging\sale\booking\Invoice;
use lodging\sale\booking\InvoiceLine;
use lodging\sale\booking\InvoiceLineGroup;
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Reverse an invoice by creating a credit note (only invoices -not credit notes- can be reversed).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the invoice to reverse.',
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
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

// emit the invoice : changing status will trigger an invoice number assignation
$invoice = Invoice::id($params['id'])
    ->read([
        'status',
        'type',
        'booking_id',
        'funding_id',
        'center_office_id',
        'organisation_id',
        'partner_id',
        'is_paid',
        'is_deposit',
        'invoice_line_groups_ids' => [
            'invoice_lines_ids' => [
                'product_id',
                'price_id',
                'vat_rate',
                'unit_price',
                'qty',
                'free_qty',
                'discount',
                'total',
                'price',
                'downpayment_invoice_id'
            ]
        ]
    ])
    ->first(true);

if(!$invoice) {
    throw new Exception("unknown_invoice", QN_ERROR_UNKNOWN_OBJECT);
}

// credit notes cannot be reversed
if($invoice['type'] != 'invoice') {
    throw new Exception("incompatible_type", QN_ERROR_UNKNOWN_OBJECT);
}

// only non-draft and non-cancelled invoices can be cancelled/reversed
if($invoice['status'] != 'invoice') {
    throw new Exception("incompatible_status", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    1) create an identical proforma invoice, but of type 'credit_note'
*/

// create credit note
$reversed_invoice = Invoice::create([
        'type'                  => 'credit_note',
        'status'                => 'proforma',
        'date'                  => time(),
        'booking_id'            => $invoice['booking_id'],
        'center_office_id'      => $invoice['center_office_id'],
        'organisation_id'       => $invoice['organisation_id'],
        'partner_id'            => $invoice['partner_id'],
        'is_deposit'            => $invoice['is_deposit'],
        'reversed_invoice_id'   => $invoice['id']
    ])
    ->first(true);

// create groups and lines
foreach($invoice['invoice_line_groups_ids'] as $gid => $group) {

    $reversed_group = InvoiceLineGroup::create([
            'name'              => $group['name'],
            'invoice_id'        => $reversed_invoice['id']
        ])
        ->first(true);

    // create group
    foreach($group['invoice_lines_ids'] as $lid => $line) {
        InvoiceLine::create([
                'invoice_id'                => $reversed_invoice['id'],
                'invoice_line_group_id'     => $reversed_group['id'],
                'product_id'                => $line['product_id'],
                'price_id'                  => $line['price_id'],
                'qty'                       => $line['qty'],
                'free_qty'                  => $line['free_qty'],
                'discount'                  => $line['discount'],
                'downpayment_invoice_id'    => $line['downpayment_invoice_id']
            ])
            // #memo - computed fields might be arbitrary (can be manually assigned, e.g. downpayment)
            ->update([
                'vat_rate'                  => $line['vat_rate'],
                'unit_price'                => $line['unit_price'],
                'total'                     => $line['total'],
                'price'                     => $line['price']
            ]);
    }
}

/*
    2) update credit note's status to 'invoice'
*/

if(!$invoice['is_paid']) {
    // if invoice hasn't been paid, mark credit note as paid (nothing to do)
    Invoice::id($reversed_invoice['id'])->update(['is_paid' => true]);
    // mark original invoice as paid (nothing to pay anymore)
    Invoice::id($params['id'])->update(['is_paid' => true]);
}
else {
    // nothing to do here: accounting service will have to do the reimbursement
}

// link original invoice to credit note and mark it as cancelled (reversed)
Invoice::id($params['id'])
    ->update(['funding_id' => null, 'reversed_invoice_id' => $reversed_invoice['id']])
    ->update(['status' => 'cancelled']);

// remove funding associated with the invoice, if any and unpaid (will be allowed since the invoice is cancelled)
if(!is_null($invoice['funding_id'])) {
    $funding = Funding::id($invoice['funding_id'])->read(['paid_amount', 'is_paid'])->first(true);
    if($funding['paid_amount'] == 0 && !$funding['is_paid']) {
        Funding::id($invoice['funding_id'])->delete(true);
    }
    else {
        Funding::id($invoice['funding_id'])->update(['type' => 'installment']);
    }
}

// mark the booking as not invoiced (will trigger updating booking lines as is_invoiced)
// #memo - this is also done when emitting credit_note, but since cancelling an invoice is not reversible, we do it now so that the status of the booking is not prematurely updated (to debit_balance or credit_balance)

// if the invoice being cancelled is a balance invoice, update related booking to 'checkedout' status
if(!$invoice['is_deposit'] && isset($invoice['booking_id']) && $invoice['booking_id'] > 0) {
    Booking::id($invoice['booking_id'])
        ->update(['status' => 'checkedout'])
        ->update(['is_invoiced' => false]);
}

// #memo - we avoid emitting invoices because automatically because it prevents choosing the date for newer invoices

$context->httpResponse()
        ->status(204)
        ->send();
