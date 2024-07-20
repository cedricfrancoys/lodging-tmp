<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use equal\php\Context;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description' => 'Advanced search for Bookings. Returns a collection of Booking with unpaid fundings, most recent first by default.',
    'extends'     => 'core_model_collect',
    'params'      => [
        'entity' =>  [
            'type'        => 'string',
            'description' => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'default'     => Booking::getType()
        ],
        'order' => [
            'description' => 'Column(s) to use for sorting results.',
            'type'        => 'string',
            'default'     => 'created'
        ],
        'sort' => [
            'description' => 'The direction  (i.e. \'asc\' or \'desc\').',
            'type'        => 'string',
            'default'     => 'desc'
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

if($params['entity'] !== Booking::getType()) {
    throw new Exception('invalid_entity', QN_ERROR_INVALID_PARAM);
}

$map_unpaid_booking_ids = [];

$domain = Domain::conditionAdd($params['domain'], ['status', 'not in', ['quote', 'option', 'balanced']]);
$bookings_ids = Booking::search($domain, ['sort' => [$params['order'] => $params['sort']]])->ids();

$fundings = Funding::search(['booking_id', 'in', $bookings_ids])
    ->read(['booking_id', 'due_amount', 'paid_amount'])
    ->get(true);

// #memo - we ignore bookings without fundings
foreach($fundings as $funding) {
    if(abs($funding['paid_amount']) < abs($funding['due_amount'])) {
        $map_unpaid_booking_ids[$funding['booking_id']] = true;
    }
}

$result = [];

if(count($map_unpaid_booking_ids)) {
    $params['domain'] = ['id', 'in', array_keys($map_unpaid_booking_ids)];
    $result = eQual::run('get', 'model_collect', $params, true);
}

$context->httpResponse()
        ->body($result)
        ->send();
