<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;
use lodging\sale\pos\CashdeskSession;
use lodging\sale\pos\Order;

list($params, $providers) = announce([
    'description'   => 'Advanced search for cashdeskSession: returns a collection of cashdeskSession according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into.',
            'type'              => 'string',
            'default'           => 'lodging\sale\pos\cashdeskSession'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit."
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval Upper limit.'
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "The center to which the booking relates to."
        ],
        'user_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\User',
            'description'       => 'User whom performed the log entry.'
        ],
        'order_name' => [
            'type'              => 'string',
            'description'       => 'Number of the order.'
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
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

/*
    Add conditions to the domain to consider advanced parameters
*/
$domain = $params['domain'];

if(isset($params['center_id'])) {
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $domain = Domain::conditionAdd($domain, ['created', '>=', $params['date_from']]);
}

if(isset($params['date_to']) && $params['date_to'] > 0) {
    $domain = Domain::conditionAdd($domain, ['modified', '<=', $params['date_to']]);
}

if(isset($params['user_id'])) {
    $domain = Domain::conditionAdd($domain, ['user_id', '=', $params['user_id']]);
}

if(isset($params['order_name']) && $params['order_name'] > 0) {
    $order_ids = Order::search(['name', '=', $params['order_name']])->ids();
    if(count($order_ids)) {
        $domain = Domain::conditionAdd($domain, ['orders_ids', 'contains', $order_ids]);
    }
}
$result = NULL;

if ($domain != $params['domain'])
{
    $params['domain'] = $domain;
    $result = eQual::run('get', 'model_collect', $params, true);
}

$context->httpResponse()
        ->body($result)
        ->send();