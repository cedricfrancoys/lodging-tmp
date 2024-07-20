<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "Removes a refund funding.",
    'help'          => "Refund funding are expected to be created manually and are therefore allowed for removal.",
    'params'        => [
        'id' =>  [
            'description'    => 'Identifier of the targeted funding.',
            'type'           => 'many2one',
            'foreign_object' => 'lodging\sale\booking\Funding',
            'required'       => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user', 'sale.default.administrator']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

$funding = Funding::id($params['id'])->read(['id', 'type', 'due_amount'])->first();

if(!$funding) {
    throw new Exception('unknown_funding', EQ_ERROR_INVALID_PARAM);
}

// fundings related to invoices cannot be transferred
if($funding['type'] == 'invoice') {
    throw new Exception('invalid_funding_type', EQ_ERROR_INVALID_PARAM);
}

if($funding['due_amount'] >= 0) {
    throw new Exception('non_refund_funding', EQ_ERROR_INVALID_PARAM);
}

Funding::id($params['id'])->delete(true);


$context->httpResponse()
        ->status(204)
        ->send();
