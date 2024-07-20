<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Contract;

list($params, $providers) = announce([
    'description'   => "Unlocks a contract (can be cancelled if booking is reverted to quote).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted contract.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'public',
        'groups'            => ['booking.default.administrator', 'sale.default.administrator'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
list($context) = [$providers['context']];

// read contract object
$contract = Contract::id($params['id'])
                    ->read(['id', 'name', 'status', 'valid_until'])
                    ->first(true);

if(!$contract) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}

if($contract['valid_until'] < time()) {
    // #memo - we allow this in order to relieve the paperwork
    // throw new Exception("outdated_contract", QN_ERROR_NOT_ALLOWED);
}

// Update contract is_locked value
Contract::id($params['id'])->update(['is_locked' => false]);


$context->httpResponse()
        ->status(204)
        ->send();
