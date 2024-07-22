<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;
use lodging\sale\booking\Invoice;
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Generate the funding for the (non-proforma) given invoice. And, in case of balance invoice, attaches non-invoiced (partially) paid fundings to it.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the invoice for which to create the funding(s).',
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
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm, $cron, $auth) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['auth']];

$invoice = Invoice::id($params['id'])
    ->read(['id', 'status', 'type', 'is_deposit', 'booking_id', 'funding_id', 'center_office_id', 'reversed_invoice_id', 'price', 'balance', 'due_date'])
    ->first(true);

if($invoice['status'] != 'invoice') {
    // only emitted invoices can have fundings
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

// if invoice do not yet relate to a funding it is a final/balance invoice (otherwise it is an installment invoice)
if(is_null($invoice['funding_id'])) {

    if($invoice['type'] == 'invoice') {
        if($invoice['is_deposit']) {
            /*
                deposit invoice (there might be several by booking)
            */

            // create a new funding relating to the invoice
            $funding = [
                'description'           => 'Facture d\'acompte',
                'booking_id'            => $invoice['booking_id'],
                'invoice_id'            => $invoice['id'],
                'center_office_id'      => $invoice['center_office_id'],
                'due_amount'            => round($invoice['price'], 2),
                'is_paid'               => false,
                'type'                  => 'invoice',
                'order'                 => 9,
                'issue_date'            => time(),
                'due_date'              => $invoice['due_date']
            ];
            // attach the invoice to the new funding
            $new_funding_id = reset(Funding::create($funding)->ids());
            Invoice::id($params['id'])->update(['funding_id' => $new_funding_id]);
        }
        else {
            // balance invoice : check for partially paid installments
            $booking = Booking::id($invoice['booking_id'])->read([
                'fundings_ids' => [
                        'is_paid', 'invoice_id', 'due_amount', 'paid_amount', 'amount_share'
                    ]
                ])
                ->first(true);

            // #memo - invoice balance can be negative, so can the amount of the funding
            $invoice_price = round($invoice['balance'], 2);

            // #memo - there is no funding for nul invoices
            if($invoice_price != 0.00) {
                // create a new funding relating to the invoice
                $funding = [
                    'description'           => 'Facture de solde',
                    'booking_id'            => $invoice['booking_id'],
                    'invoice_id'            => $invoice['id'],
                    'center_office_id'      => $invoice['center_office_id'],
                    'due_amount'            => $invoice_price,
                    'is_paid'               => false,
                    'type'                  => 'invoice',
                    'order'                 => 9,
                    'issue_date'            => time(),
                    'due_date'              => $invoice['due_date']
                ];

                $new_funding = Funding::create($funding)->read(['id', 'name'])->first(true);
                // attach the invoice to the new funding
                Invoice::id($params['id'])->update(['funding_id' => $new_funding['id']]);
            }
        }
    }
    elseif($invoice['type'] == 'credit_note') {
        // #memo - there is only one active credit note : we need to reverse all paid amounts from fundings that are not related to a deposit invoice (not only the one linked to the reversed invoice)
        $booking = Booking::id($invoice['booking_id'])
            ->read([
                'fundings_ids' => [
                        'is_paid',
                        'due_amount',
                        'paid_amount',
                        'amount_share',
                        'invoice_id' => ['is_deposit']
                    ]
                ])
            ->first(true);

        /*
            we must create a funding that matches the amount already paid for the booking (except for the deposit invoices)
            if there is none, then we mark the credit note as paid
        */
        $paid_amount = array_reduce($booking['fundings_ids'], function($c, $funding) {
                $result = $c;
                if(!isset($funding['invoice_id']['is_deposit']) || !$funding['invoice_id']['is_deposit']) {
                    $result += $funding['paid_amount'];
                }
                return $result;
            }, 0);

        if($paid_amount > 0) {
            // create a new funding relating to the invoice
            $funding = [
                'description'           => 'Note de crédit',
                'booking_id'            => $invoice['booking_id'],
                'invoice_id'            => $invoice['id'],
                'center_office_id'      => $invoice['center_office_id'],
                'due_amount'            => round(-$paid_amount, 2),
                'is_paid'               => false,
                'type'                  => 'invoice',
                'order'                 => 9,
                'issue_date'            => time(),
                'due_date'              => $invoice['due_date']
            ];
            // attach the invoice to the new funding
            $new_funding_id = reset(Funding::create($funding)->ids());
            Invoice::id($params['id'])->update(['funding_id' => $new_funding_id]);
        }
        else {
            Invoice::id($params['id'])->update(['is_paid' => true]);
        }
    }
}

/*
// #memo - no changes in payments: shouldn't be necessary
// reset related booking paid amount
Booking::id($invoice['booking_id'])->update(['paid_amount' => null]);
Booking::updateStatusFromFundings($orm, (array) $invoice['booking_id'], [], 'en');
*/

$context->httpResponse()
        ->status(204)
        ->send();
