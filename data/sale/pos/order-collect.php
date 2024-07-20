<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;
use lodging\sale\pos\CashdeskSession;

list($params, $providers) = announce([
    'description'   => 'Advanced search for Order: returns a collection of Order according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into.',
            'type'              => 'string',
            'default'           => 'lodging\sale\pos\Order'
        ],
        'name' => [
            'type'              => 'string',
            'description'       => 'Number of the order.'
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
        'funding_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\Funding',
            'description'       => 'The booking funding that relates to the order, if any.',
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

if(isset($params['name']) && $params['name'] > 0) {
    $domain = Domain::conditionAdd($domain, ['name','ilike','%'.$params['name'].'%']);
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $domain = Domain::conditionAdd($domain, ['created', '>=', $params['date_from']]);
}

if(isset($params['date_to']) && $params['date_to'] > 0) {
    $domain = Domain::conditionAdd($domain, ['created', '<=', $params['date_to']]);
}

if(isset($params['center_id'])) {
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}

if(isset($params['user_id'])) {
    $cashdeskSession_ids= CashdeskSession::search(['user_id', '=', $params['user_id']])->ids();
    if(count($cashdeskSession_ids)) {
        $domain = Domain::conditionAdd($domain, ['session_id', 'in', $cashdeskSession_ids]);
    }
}

if(isset($params['funding_id'])) {
    $domain = Domain::conditionAdd($domain, ['funding_id', '=', $params['funding_id']]);
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
