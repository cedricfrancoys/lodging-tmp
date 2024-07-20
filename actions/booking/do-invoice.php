<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\Funding;
use lodging\sale\booking\Invoice;
use identity\Partner;

list($params, $providers) = eQual::announce([
    'description'   => "Generates the proforma for the balance invoice for a booking.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the booking for which the invoice has to be generated.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
        ],
        'partner_id' =>  [
            'description'       => 'Partner to who address the invoice, if distinct from customer.',
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\customer\Customer',
            'domain'            => [['relationship', '=', 'customer'], ['is_active', '=', true]]
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
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
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];



// read booking object
$booking = Booking::id($params['id'])
                  ->read(['id', 'status', 'booking_lines_ids', 'customer_id','customer_identity_id'])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'checkedout') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if(isset($params['partner_id']) && $params['partner_id'] > 0 ){

    $partner = Partner::id($params['partner_id'])
                ->read(['id', 'name', 'partner_identity_id'])
                ->first(true);

    if(!$partner) {
        throw new Exception("unknown_partner", QN_ERROR_UNKNOWN_OBJECT);
    }

    if(!$partner['partner_identity_id']) {
        throw new Exception("invalid_partner", QN_ERROR_UNKNOWN_OBJECT);
    }

    if($partner['id'] == $booking['customer_id'] ) {
        unset($params['partner_id']);
    }

    if($partner['partner_identity_id'] == $booking['customer_identity_id'] ) {
        unset($params['partner_id']);
    }
}

$deposit_invoices = Invoice::search([['booking_id', '=', $booking['id']], ['is_deposit', '=', true]])
                  ->read(['status'])
                  ->get(true);

foreach($deposit_invoices as $deposit_invoice) {
    if($deposit_invoice['status'] == 'proforma') {
        throw new Exception("non_emitted_deposit_invoice", QN_ERROR_INVALID_PARAM);
    }
}

// all fundings are renewed / updated : remove any pending alert related to due payments
$dispatch->cancel('lodging.booking.payments', 'lodging\sale\booking\Booking', $booking['id']);

/*
    Remove any non-paid and non-invoice remaining funding
*/
// #memo - fundings can be manually marked as paid without being actually linked to payments (transition)
$fundings = Funding::search([ ['paid_amount', '=', 0], ['is_paid', '=', false], ['type', '=', 'installment'], ['booking_id', '=', $params['id']], ['invoice_id', '=', null] ])
    ->read(['id', 'payments_ids'])
    ->get(true);

// before deleting fundings, we make sure they do not relate to a received payment (shouldn't occur - covers the case where paid_amount is not properly set)
foreach($fundings as $funding) {
    if(!$funding['payments_ids'] || count($funding['payments_ids']) == 0) {
        // #memo - an addition check is made in underlying candelete method
        Funding::id($funding['id'])->delete(true);
        // remove any existing CRON tasks for checking funding overdue
        $cron->cancel("booking.funding.overdue.{$funding['id']}");
        // #memo - there are no alerts specific to fundings (only bookings)
    }
}

/*
    Generate invoice
*/

// generate balance invoice (proforma) (raise exception on failure)
eQual::run('do', 'lodging_invoice_generate', $params);

// update booking status
Booking::id($params['id'])->update(['status' => 'invoiced']);

$context->httpResponse()
        ->status(204)
        ->send();
