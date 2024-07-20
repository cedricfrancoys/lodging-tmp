<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\setting\Setting;
use core\Lang;
use lodging\sale\booking\Invoice;
use lodging\sale\booking\InvoiceLine;
use lodging\sale\booking\InvoiceLineGroup;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Funding;
use lodging\sale\catalog\Product;

list($params, $providers) = eQual::announce([
    'description'   => "Generate a proforma balance invoice for a given booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the invoice has to be generated.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'partner_id' =>  [
            'description'   => 'Identifier of the partner to which the invoice must be addressed, if not set defaults to customer_id.',
            'type'          => 'integer'
        ]
    ],
    'constants'             => ['DEFAULT_LANG'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];


// #memo - final invoice is released after checkout (unless payment plan holds a balance invoice)


// search for an existing balance invoice for this booking (there should be none)
$invoice = Invoice::search([['booking_id', '=', $params['id']], ['type', '=', 'invoice'], ['status', '=', 'invoice'], ['is_deposit', '=', false]])->read(['id'])->first();

if($invoice) {
    throw new Exception("invoice_already_exists", QN_ERROR_NOT_ALLOWED);
}

// read booking object
$booking = Booking::id($params['id'])
    ->read([
        'status',
        'type',
        'date_from',
        'date_to',
        'price',
        'center_office_id' => ['id', 'organisation_id'],
        'customer_id' => ['id', 'rate_class_id', 'lang_id' => ['code']],
        'booking_lines_ids',
        'booking_lines_groups_ids' => [
            'name',
            'date_from',
            'date_to',
            'has_pack',
            'is_locked',
            'pack_id' => ['id', 'name'],
            'price_id',
            'vat_rate',
            'unit_price',
            'qty',
            'nb_nights',
            'nb_pers',
            'booking_lines_ids' => [
                'product_id',
                'description',
                'price_id',
                'unit_price',
                'vat_rate',
                'qty',
                'free_qty',
                'discount',
                'price',
                'total'
            ]
        ]
    ])
    ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($booking['status'], ['confirmed', 'checkedout'])) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if(count($booking['booking_lines_ids']) <= 0) {
    throw new Exception("empty_booking", QN_ERROR_INVALID_PARAM);
}

/*
    Check consistency
*/

$errors = [];

// check customer details completeness
$data = eQual::run('do', 'lodging_booking_check-customer', ['id' => $booking['id']]);
if(is_array($data) && count($data)) {
    // response array is not empty: missing customer details
    $errors[] = 'uncomplete_customer';
}

// raise an exception with first error (alerts should have been issued in the check controllers)
foreach($errors as $error) {
    throw new Exception($error, QN_ERROR_INVALID_PARAM);
}


// remove pre-existing 'proforma' invoices, if any
// #memo - this will trigger a reset of the invoice_id of any funding attached to the proforma
$proforma = Invoice::search([['booking_id', '=', $params['id']], ['type', '=', 'invoice'], ['is_deposit', '=', false], ['status', '=', 'proforma']])->read(['id', 'fundings_ids'])->first(true);

if($proforma) {
    Invoice::id($proforma['id'])->delete(true);
    // detach fundings from deleted invoice
    Funding::ids($proforma['fundings_ids'])->update(['invoice_id' => null]);
}


/**
 * Generate the invoice
 */

// #todo - use settings for selecting the suitable payment terms

// remember all booking lines involved
$booking_lines_ids = [];

// create invoice and invoice lines
$invoice = Invoice::create([
        'date'              => time(),
        'organisation_id'   => $booking['center_office_id']['organisation_id'],
        'booking_id'        => $params['id'],
        'center_office_id'  => $booking['center_office_id']['id'],
        'status'            => 'proforma',
        // allow to invoice to a "payer" partner distinct from customer
        'partner_id'        => (isset($params['partner_id']) && $params['partner_id'] > 0)?$params['partner_id']:$booking['customer_id']['id']
    ])
    ->read(['id', 'partner_id'])
    ->first();

// append invoice lines based on booking lines
foreach($booking['booking_lines_groups_ids'] as $group_id => $group) {
    $group_label = $group['name'].' : ';

    if($group['date_from'] == $group['date_to']) {
        $group_label .= date('d/m/y', $group['date_from']);
    }
    else {
        $group_label .= date('d/m/y', $group['date_from']).' - '.date('d/m/y', $group['date_to']);
    }

    $group_label .= ' - '.$group['nb_pers'].'p.';

    $invoice_line_group = InvoiceLineGroup::create([
            'name'              => $group_label,
            'invoice_id'        => $invoice['id']
        ])
        ->read(['id'])
        ->first();

    if($group['has_pack'] && $group['is_locked'] ) {
        // invoice group with a single line

        // create a line based on the booking Line Group
        InvoiceLine::create([
                'invoice_id'                => $invoice['id'],
                'invoice_line_group_id'     => $invoice_line_group['id'],
                'product_id'                => $group['pack_id']['id'],
                'price_id'                  => $group['price_id']
            ])
            ->update([
                'vat_rate'                  => $group['vat_rate'],
                'unit_price'                => $group['unit_price'],
                'qty'                       => $group['qty']
            ])
            ->update([
                'total'                     => $group['total']
            ])
            ->update([
                'price'                     => $group['price']
            ]);

    }
    else {
        // create as many lines as the group booking_lines
        foreach($group['booking_lines_ids'] as $lid => $line) {
            $booking_lines_ids[] = $lid;

            // create line in several steps (not to overwrite final values from the line - that might have been manually adapted)
            InvoiceLine::create([
                    'invoice_id'                => $invoice['id'],
                    'invoice_line_group_id'     => $invoice_line_group['id'],
                    'product_id'                => $line['product_id'],
                    'description'               => $line['description'],
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

    }

}

$customer_lang = constant('DEFAULT_LANG');
if(isset($booking['customer_id']['lang_id']['code'])) {
    $customer_lang = $booking['customer_id']['lang_id']['code'];
}

/**
 * Add lines relating to fundings, if any (paid installments and invoice downpayments)
 */

// find all fundings of given booking
$fundings = Funding::search(['booking_id', '=', $params['id']])
    ->read(['type', 'due_amount', 'is_paid', 'paid_amount', 'invoice_id', 'payments_ids'])
    ->get();

if($fundings) {

    // retrieve downpayment product
    $downpayment_product_id = 0;

    $downpayment_sku = Setting::get_value('sale', 'invoice', 'downpayment.sku.'.$booking['center_office_id']['organisation_id']);
    if($downpayment_sku) {
        $product = Product::search(['sku', '=', $downpayment_sku])->read(['id'])->first();
        if($product) {
            $downpayment_product_id = $product['id'];
        }
    }

    $i_lines_ids = [];

    $invoice_label = Lang::get_term('sale', 'invoice', 'invoice', $customer_lang);
    $installment_label = Lang::get_term('sale', 'installment', 'downpayment', $customer_lang);

    foreach($fundings as $fid => $funding) {

        if($funding['type'] == 'invoice') {
            $funding_invoice = Invoice::id($funding['invoice_id'])
                ->read([
                        'id', 'created', 'name', 'status', 'partner_id', 'type', 'is_deposit', 'price',
                        'invoice_lines_ids' => ['vat_rate', 'product_id', 'qty', 'price', 'unit_price']
                    ])
                ->first();

            if(!$funding_invoice) {
                // #memo - in the situation of a funding converted to an invoice, the generated invoice might have remained as proforma: in such case the invoice has just been deleted
                // set funding type to installment (to be handled as regular funding - see below)
                $funding['type'] = 'installment';
                Funding::id($fid)->update(['type' => 'installment', 'invoice_id' => null]);
            }
            // consider only invoices created from funding
            else {
                if($funding_invoice['type'] == 'invoice' && $funding_invoice['is_deposit']) {
                    // payer and customer must be the same for the considered invoices
                    // #memo - this test is independent from the customer of the booking
                    if($funding_invoice['partner_id'] == $invoice['partner_id']) {
                        // #memo - there should be only one line
                        foreach($funding_invoice['invoice_lines_ids'] as $lid => $line) {
                            if($line['price'] == 0.0) {
                                // ignore lines with nul amount
                                continue;
                            }
                            $i_line = [
                                'invoice_id'                => $invoice['id'],
                                'name'                      => $installment_label.' '.$funding_invoice['name'],
                                // product should be the downpayment product
                                'product_id'                => $line['product_id'],
                                // vat_rate depends on the organization : VAT is due with arbitrary amount (default VAT rate applied)
                                'vat_rate'                  => $line['vat_rate'],
                                // #memo - by convention, price is always a positive value (so that price, credit and debit remain positive at all time)
                                'unit_price'                => $line['unit_price'],
                                // and quantity is set as negative value when something is deducted
                                'qty'                       => -$line['qty'],
                                // mark the line as issued from an invoice
                                'downpayment_invoice_id'    => $funding['invoice_id'],
                                // #memo - we don't assign a price_id : downpayments will be identified as such and use a specific accounting rule
                            ];
                            $new_line = InvoiceLine::create($i_line)
                                ->read(['id'])
                                ->first();
                            $i_lines_ids[] = $new_line['id'];
                        }
                    }
                    // payer and customer are distinct
                    else {
                        // consider the invoice as a paid downpayment
                        $i_line = [
                            'invoice_id'                => $invoice['id'],
                            'description'               => ucfirst($installment_label).' '.date('Y-m', $funding_invoice['created']),
                            'product_id'                => $downpayment_product_id,
                            'vat_rate'                  => 0.0,
                            // #memo - by convention, price is always a positive value (so that price, credit and debit remain positive at all time)
                            'unit_price'                => $funding_invoice['price'],
                            // and quantity is set as negative value when something is deducted
                            'qty'                       => -1
                            // #memo - we don't assign a price : downpayments will be identified as such and use a specific accounting rule
                        ];
                        $new_line = InvoiceLine::create($i_line)->read(['id'])->first();
                        $i_lines_ids[] = $new_line['id'];
                    }
                }
                // #memo - we're re-emitting a balance invoice : remove fundings from previous credit note
                elseif($funding_invoice['type'] == 'credit_note' /*&& $funding_invoice['status'] != 'invoice'*/) {
                    if($funding['paid_amount'] == 0 && !$funding['is_paid'] && count($funding['payments_ids']) == 0) {
                        // remove non-invoiced non-paid fundings for previous credit note
                        Funding::id($fid)->delete(true);
                    }
                }
            }
        }
        // if funding has already been attached to a non-cancelled invoice, ignore it (cannot be updated nor re-assigned)
        elseif($funding['invoice_id'] > 0) {
            // #memo - invoice might have been 'soft'-deleted: make sure it still exists
            $related_invoice = Invoice::id($funding['invoice_id'])->read(['id', 'status'])->first();
            if($related_invoice && $related_invoice['status'] == 'invoice') {
                continue;
            }
        }

        if($funding['type'] == 'installment') {
            // #memo - fundings can be manually marked as paid without being actually linked to payments (transition)
            if($funding['paid_amount'] == 0 && !$funding['is_paid'] && count($funding['payments_ids']) == 0 && is_null($funding['invoice_id'])) {
                // remove non-invoiced non-paid fundings
                Funding::id($fid)->delete(true);
            }
            else {
                Funding::id($fid)->update(['invoice_id'  => $invoice['id']]);

                // partially paid fundings are kept and attached to the invoice on which they are accounted
                if(abs($funding['paid_amount']) < abs($funding['due_amount'])) {
                    if($funding['paid_amount'] == 0) {
                        Funding::id($fid)->delete(true);
                    }
                    else {
                        Funding::id($fid)
                            // #memo - we have to do this in several steps, because once the funding is marked as is_paid, the funding can no longer be modified (except for the invoice_id)
                            ->update(['due_amount'  => round($funding['paid_amount'], 2)])
                            ->update(['is_paid'     => null]);
                    }
                }

                // #memo - if paid_amount is greater than due_amount, the invoice might become negative (fundings are reimbursed by the Accounting department given instruction from Booking departement)
                $i_line = [
                    'invoice_id'                => $invoice['id'],
                    'description'               => ucfirst($installment_label).' '.date('Y-m'),
                    'product_id'                => $downpayment_product_id,
                    'vat_rate'                  => 0.0,
                    // #memo - by convention, price is always a positive value (so that price, credit and debit remain positive at all time)
                    // #memo - only paid amount matters: it can be equal to due_amount, lower or higher
                    'unit_price'                => $funding['paid_amount'],
                    // and quantity is set as negative value when something is deduced
                    'qty'                       => -1
                    // #memo - we don't assign a price : downpayments will be identified as such and use a specific accounting rule
                ];

                // #memo - do not add non-invoiced fundings to balance invoice
                /*
                $new_line = InvoiceLine::create($i_line)->read(['id'])->first();
                $i_lines_ids[] = $new_line['id'];
                */
            }
        }
    }

    // get the group name according to requested lang
    $group_label = ucfirst(Lang::get_term('sale', 'downpayments', 'downpayments', $customer_lang));

    InvoiceLineGroup::create([
        'name'              => $group_label,
        'invoice_id'        => $invoice['id'],
        'invoice_lines_ids' => $i_lines_ids
    ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
