<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for the Funding: returns a collection of Reports according to extra paramaters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'lodging\sale\booking\Funding'
        ],
        'due_amount_min' => [
            'type'              => 'integer',
            'description'       => 'Minimal amount expected for the funding.'
        ],
        'due_amount_max' => [
            'type'              => 'integer',
            'description'       => 'Maximum amount expected for funding.'
        ],
        'booking_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\Booking',
            'description'       => 'Booking the invoice relates to.'
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => 'The center to which the funding relates to.',
        ],
        'payment_reference' => [
            'type'              => 'string',
            'description'       => 'Message for identifying the purpose of the transaction.'
        ]
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

if(isset($params['due_amount_min']) && $params['due_amount_min'] > 0) {
    $domain = Domain::conditionAdd($domain, ['due_amount', '>=', $params['due_amount_min']]);
}

if(isset($params['due_amount_max']) && $params['due_amount_max'] > 0) {
    $domain = Domain::conditionAdd($domain, ['due_amount', '<=', $params['due_amount_max']]);
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

if(isset($params['payment_reference']) && strlen($params['payment_reference']) > 0 ) {
    $domain = Domain::conditionAdd($domain, ['payment_reference', 'like', '%'. $params['payment_reference'].'%']);
}

$params['domain'] = $domain;
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
