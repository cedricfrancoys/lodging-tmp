<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Reports: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'lodging\sale\booking\Invoice'
        ],
        'name' => [
            'type'              => 'string',
            'description'       => 'Number of the invoice, according to organization logic (@see config/invoicing).'
        ],
        'booking_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\Booking',
            'description'       => 'Booking the invoice relates to.'
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => 'The center to which the booking relates to.',
        ],
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\CenterOffice',
            'description'       => 'Office the invoice relates to (for center management).'
        ],
        'customer_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\customer\Customer',
            'description'       => 'The customer whom the booking relates to (depends on selected identity).'
        ],
        'partner_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Partner',
            'description'       => 'The counter party organization the invoice relates to.'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit."
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval Upper limit.'
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

if(isset($params['name']) && strlen($params['name']) > 0 ) {
    $domain = Domain::conditionAdd($domain, ['name', 'like','%'.$params['name'].'%']);
}

if(isset($params['booking_id']) && $params['booking_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['booking_id', '=', $params['booking_id']]);
}

if(isset($params['center_id']) && $params['center_id'] > 0) {
    $bookings_ids = [];
    $bookings_ids = Booking::search(['center_id', '=', $params['center_id']])->ids();
    if(count($bookings_ids)) {
        $domain = Domain::conditionAdd($domain, ['booking_id', 'in', $bookings_ids]);
    }
}

if(isset($params['center_office_id']) && $params['center_office_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['center_office_id', '=', $params['center_office_id']]);
}

if(isset($params['customer_id']) && $params['customer_id'] > 0) {
    $bookings_ids = [];
    $bookings_ids = Booking::search(['customer_id', '=', $params['customer_id']])->ids();
    if(count($bookings_ids)) {
        $domain = Domain::conditionAdd($domain, ['booking_id', 'in', $bookings_ids]);
    }
}

if(isset($params['partner_id']) && $params['partner_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['partner_id', '=', $params['partner_id']]);
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $domain = Domain::conditionAdd($domain, ['date', '>=', $params['date_from']]);
}

if(isset($params['date_to']) && $params['date_to'] > 0) {
    $domain = Domain::conditionAdd($domain, ['date', '<=', $params['date_to']]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
