<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\php\Context;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description' => 'PoS search for Bookings, based on a given clue. Returns a collection of Booking candidates based on booking number of customer name.',
    'params'      => [
        'clue' => [
            'description' => 'The direction  (i.e. \'asc\' or \'desc\').',
            'type'        => 'string',
            'default'     => 'desc'
        ],
        'domain' => [
            'description'   => 'Criterias that results have to match (serie of conjunctions)',
            'type'          => 'array',
            'default'       => []
        ],
        'limit' => [
            'description'   => 'The maximum number of results.',
            'type'          => 'integer',
            'min'           => 1,
            'max'           => 500,
            'default'       => 25
        ]
    ],
    'access' => [
        'groups' => ['booking.default.user']
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var Context $context
 */
$context = $providers['context'];


$domain = $params['domain'];

if(strlen($params['clue']) > 0 && is_numeric($params['clue'])) {
    $domain[] = ['name', 'ilike', '%'.$params['clue'].'%'];
}

$result = [];

$bookings = Booking::search($domain, ['sort' => [ 'name' => 'asc']])
    ->read(['id', 'name', 'customer_id' => 'name'])
    ->adapt('json')
    ->get(true);

// additional filter on customer name, if necessary
if(strlen($params['clue']) > 0 && !is_numeric($params['clue'])) {
    foreach($bookings as $booking) {
        if(stripos($booking['customer_id']['name'], $params['clue']) !== false) {
            $result[] = $booking;
        }
    }
}
else {
    $result = $bookings;
}

$context->httpResponse()
        ->body($result)
        ->send();
