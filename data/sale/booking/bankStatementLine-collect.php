<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Reports: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'lodging\sale\booking\BankStatementLine'
        ],
        'id' => [
            'type'              => 'integer',
            'description'       => 'Identifier of the BankStatementLine.'
        ],
        'account_holder' => [
            'type'              => 'string',
            'description'       => 'Name of the Person whom the payment originates.'
        ],
        'structured_message' => [
            'type'              => 'string',
            'description'       => 'Structured message, if any.'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval.",
            'default'           => null
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'default'           => time()
        ],
        'amount_min' => [
            'type'              => 'integer',
            'description'       => 'Minimal amount expected for the Bank Statement Line.'
        ],
        'amount_max' => [
            'type'              => 'integer',
            'description'       => 'Maximum amount expected for the Bank Statement Line.'
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

if(isset($params['id']) && $params['id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['id', '=', $params['id']]);
}

if(isset($params['account_holder']) && strlen($params['account_holder']) > 0 ) {
    $domain = Domain::conditionAdd($domain, ['account_holder', 'ilike','%'.$params['account_holder'].'%']);
}

if(isset($params['structured_message']) && strlen($params['structured_message']) > 0 ) {
    $domain = Domain::conditionAdd($domain, ['structured_message', 'like','%'.$params['structured_message'].'%']);
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $domain = Domain::conditionAdd($domain, ['date', '>=', $params['date_from']]);
}

if(isset($params['date_to']) && $params['date_to'] > 0) {
    $domain = Domain::conditionAdd($domain, ['date', '<=', $params['date_to']]);
}

if(isset($params['amount_min']) && $params['amount_min'] > 0) {
    $domain = Domain::conditionAdd($domain, ['amount', '>=', $params['amount_min']]);
}

if(isset($params['amount_max']) && $params['amount_max'] > 0) {
    $domain = Domain::conditionAdd($domain, ['amount', '<=', $params['amount_max']]);
}


$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
