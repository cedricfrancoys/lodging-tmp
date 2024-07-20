<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\finance\accounting\Invoice;

list($params, $providers) = announce([
    'description'   => "Updates invoices to new sequence numbers.",
    'params'        => [
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['finance.default.user', 'sale.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm) = [$providers['context'], $providers['orm']];

$invoices = Invoice::search(['status', '=', 'invoice'])->read( ['id', 'number', 'name_old'] );

foreach($invoices as $invoice) {
    $name = $invoice['number'];
    $name_old = $invoice['name_old'];
    Invoice::id($invoice['id'])->update(['number' => $name_old, 'name_old' => $name]);
}


$context->httpResponse()
        ->status(204)
        ->send();