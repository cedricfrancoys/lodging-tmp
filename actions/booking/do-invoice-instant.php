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


list($params, $providers) = announce([
    'description'   => "Generates the proforma for the balance invoice for a booking.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the booking for which the invoice has to be generated.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
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
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];



// read booking object
$booking = Booking::id($params['id'])
                  ->read(['id', 'status', 'nb_pers', 'is_from_channelmanager', 'booking_lines_ids', 'customer_id', 'customer_identity_id'])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'checkedout') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if($booking['is_from_channelmanager'] !== true && $booking['nb_pers'] > 9) {
    throw new Exception("incompatible_booking", QN_ERROR_INVALID_PARAM);
}


// 1) Generate balance invoice (proforma) (raise exception on failure)
eQual::run('do', 'lodging_booking_do-invoice', $params);


// 2) Emit invoice
$invoice = Invoice::search([ ['booking_id', '=', $booking['id']], ['type', '=', 'invoice'], ['status', '=', 'proforma'], ['is_deposit', '=', false] ], [ 'sort' => ['created' => 'desc']])
    ->read(['id'])
    ->first(true);

if(!$invoice) {
    throw new Exception("invoice_not_found", QN_ERROR_MISSING_PARAM);
}

eQual::run('do', 'lodging_invoice_do-emit', $invoice);

$context->httpResponse()
        ->status(204)
        ->send();
