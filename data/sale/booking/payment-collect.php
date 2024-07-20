<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use lodging\identity\Center;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BankStatementLine;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Payments: returns a collection of Payments according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'lodging\sale\booking\Payment'
        ],
        'booking_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\Booking',
            'description'       => 'Booking the invoice relates to.'
        ],
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\CenterOffice',
            'description'       => "The center office to which the payments relates to.",
        ],
        'partner_id'  => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Partner',
        ],
        'amount_min' => [
            'type'              => 'float',
            'description'       => 'Minimal amount expected for the payment.'
        ],
        'amount_max' => [
            'type'              => 'float',
            'description'       => 'Maximum amount expected for the payment.'
        ],
        'payment_origin' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'cashdesk',
                'bank',
                'online'
            ],
            'description'       => "Origin of the received money."
        ],
        'payment_method' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'voucher',
                'cash',
                'bank_card'
            ],
            'description'       => "The method used for payment at the cashdesk."
        ],
        'receipt_date_min' => [
            'type'              => 'date',
            'description'       => "Minimal date of reception of the payment.",
            'default'           => strtotime("-2 Years")
        ],
        'receipt_date_max' => [
            'type'              => 'date',
            'description'       => "Maximum date of reception of the payment.",
            'default'           => strtotime("+2 Years")
        ],
        'booking_date_min' => [
            'type'              => 'date',
            'description'       => "Minimal date of  the booking.",
            'default'           => strtotime("-2 Years")
        ],
        'booking_date_max' => [
            'type'              => 'date',
            'description'       => "Maximum date of reception of the booking.",
            'default'           => strtotime("+2 Years")
        ],
        'bank_account_iban' => [
            'type'          => 'string',
            'usage'         => 'uri/urn:iban',
            'description'   => "Number of the bank account of the Identity, if any."
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);
/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

$domain = $params['domain'];

if(isset($params['booking_id']) && $params['booking_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['booking_id', '=', $params['booking_id']]);
}

if(isset($params['partner_id']) && $params['partner_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['partner_id', '=', $params['partner_id']]);
}

if(isset($params['statement_line_id']) && $params['statement_line_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['statement_line_id', '=', $params['statement_line_id']]);
}

if(isset($params['payment_origin']) && strlen($params['payment_origin']) > 0 && $params['payment_origin']!= 'all') {
    $domain = Domain::conditionAdd($domain, ['payment_origin', '=', $params['payment_origin']]);
}

if(isset($params['payment_method']) && strlen($params['payment_method']) > 0 && $params['payment_method']!= 'all') {
    $domain = Domain::conditionAdd($domain, ['payment_method', '=', $params['payment_method']]);
}

if(isset($params['center_office_id']) && $params['center_office_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['center_office_id', '=', $params['center_office_id']]);
}

if(isset($params['amount_min']) && $params['amount_min'] != 0 ) {
    $domain = Domain::conditionAdd($domain, ['amount', '>=', $params['amount_min']]);
}

if(isset($params['amount_max']) && $params['amount_max'] != 0 ) {
    $domain = Domain::conditionAdd($domain, ['amount', '<=', $params['amount_max']]);
}

if(isset($params['receipt_date_min']) && $params['receipt_date_min'] > 0) {
    $domain = Domain::conditionAdd($domain, ['receipt_date', '>=', $params['receipt_date_min'] ]);
}

if(isset($params['receipt_date_max']) && $params['receipt_date_max'] > 0) {
    $domain = Domain::conditionAdd($domain, ['receipt_date', '<=', $params['receipt_date_max'] ]);
}

if(isset($params['booking_date_min']) && $params['booking_date_min'] > 0) {
    if(isset($params['booking_date_max']) && $params['booking_date_max'] > 0) {
        $bookings_ids = Booking::search([ ['date_from', '>=', $params['booking_date_min']], ['date_to', '<=', $params['booking_date_max']] ])->ids();
    }
    else {
        $bookings_ids = Booking::search(['date_from', '>=', $params['booking_date_min']])->ids();
    }
    if(count($bookings_ids)) {
        $domain = Domain::conditionAdd($domain, ['booking_id', 'in', $bookings_ids]);
    }
}
elseif(isset($params['booking_date_max']) && $params['booking_date_max'] > 0) {
    $bookings_ids = Booking::search(['date_to', '<=', $params['booking_date_max']])->ids();
    if(count($bookings_ids)) {
        $domain = Domain::conditionAdd($domain, ['booking_id', 'in', $bookings_ids]);
    }
}

if(isset($params['bank_account_iban']) && strlen($params['bank_account_iban'])) {
    $lines_ids = BankStatementLine::search(['account_iban', '=', $params['bank_account_iban']])->ids();
    if(count($lines_ids)) {
        $domain = Domain::conditionAdd($domain, ['statement_line_id', 'in', $lines_ids]);
    }
}

$params['domain'] = $domain;
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
