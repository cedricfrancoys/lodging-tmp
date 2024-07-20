<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;
use lodging\realestate\RentalUnit;
use lodging\sale\booking\Booking;
use lodging\identity\User;

list($params, $providers) = announce([
    'description'   => 'Advanced search for Consumptions of the clean rooms: returns a collection of Consumptions according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into.',
            'type'              => 'string',
            'default'           => 'lodging\sale\booking\Consumption'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => strtotime('Today')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval Upper limit.',
            'default'           => strtotime('+7 days midnight')
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "The center to which the booking relates to."
        ],
        'cleanup_type' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'daily',
                'full'
            ],
            'default'           => 'all'
        ],
        'is_not_option' => [
            'type'              => 'boolean',
            'description'       => 'Discard quote and option bookings.',
            'default'           =>  true
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'auth' ]
]);

/**
 * @var \equal\php\Context                   $context
 * @var \equal\orm\ObjectManager             $orm
 * @var \equal\auth\AuthenticationManager    $auth
 */
list($context, $orm, $auth) = [ $providers['context'], $providers['orm'], $providers['auth'] ];

$domain = $params['domain'];


if(isset($params['center_id']) && $params['center_id'] > 0) {
    // add constraint on center_id
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}
else {
    // if no center is provided, fallback to current users'
    $user = User::id($auth->userId())->read(['centers_ids'])->first(true);
    if(count($user['centers_ids']) == 1) {
        $domain = Domain::conditionAdd($domain, ['center_id', '=', reset($user['centers_ids'])]);
    }
    else {
        $domain = Domain::conditionAdd($domain, ['center_id', '=', 0]);
    }
}


if(isset($params['date_from'])) {
    $domain = Domain::conditionAdd($domain, ['date', '>=', $params['date_from']]);
}

if(isset($params['date_to'])) {
    $domain = Domain::conditionAdd($domain, ['date', '<=', $params['date_to']]);
}

if(isset($params['cleanup_type']) && strlen($params['cleanup_type']) > 0 && $params['cleanup_type']!= 'all') {
    $domain = Domain::conditionAdd($domain, ['cleanup_type', '=', $params['cleanup_type']]);
}

if(isset($params['is_not_option']) && $params['is_not_option']) {
    $bookings_ids = [];
    $bookings_ids = Booking::search(['status', 'not in', ['quote','option']], ['is_cancelled', '=', false])->ids();
    $domain = Domain::conditionAdd($domain, ['booking_id', 'in', $bookings_ids]);
}

$params['domain'] = $domain;

if(empty($params['fields'])) {
    $params['fields'] = ['rental_unit_id.name'];
}

$result = eQual::run('get', 'model_collect', $params, true);

if(in_array('rental_unit_id.name', $params['fields'])) {

    $rental_unit_ids = array_map(function($a) {return $a['rental_unit_id']['id'];}, $result);

    $rental_units = RentalUnit::ids($rental_unit_ids)->read(['name'])->get();
    foreach($result as $index => $consumption) {
        $result[$index]['rental_unit_name'] = $rental_units[$consumption['rental_unit_id']['id']]['name'];
    }

    usort($result, function ($a, $b) {
        return strcmp($a['rental_unit_name'], $b['rental_unit_name']);
    });

}

$context->httpResponse()
        ->body($result)
        ->send();
