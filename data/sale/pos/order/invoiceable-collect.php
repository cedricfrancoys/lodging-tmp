<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;

list($params, $providers) = announce([
    'description'   => 'Advanced search for Order: returns a collection of Order according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity'    =>  [
            'description'       => 'Full name (including namespace) of the class to look into.',
            'type'              => 'string',
            'default'           => 'lodging\sale\pos\Order'
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "The center to which the booking relates to."
        ],
        'all_months' => [
            'type'              => 'boolean',
            'description'       => "The Orders every month.",
            'default'           => true,
        ],
        'date'      => [
            'type'              => 'date',
            'description'       => "Date of the creation.",
            'usage'             => 'date/month',
            'default'           => strtotime('Today')
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
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

$domain = $params['domain'];

if(isset($params['center_id']) && $params['center_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}

if(!$params['all_months'] && isset($params['date']) && $params['date'] > 0) {
    $first_date = strtotime(date('Y-m-01 00:00:00', $params['date']));
    $last_date = strtotime('first day of next month', $first_date);
    $domain = Domain::conditionAdd($domain, ['created', '>=', $first_date]);
    $domain = Domain::conditionAdd($domain, ['created', '<', $last_date]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
