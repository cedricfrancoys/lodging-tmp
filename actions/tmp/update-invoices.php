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

$map_centeroffices_indexes = [
	1	=> 1,
	2	=> 1,
	3	=> 1,
	4	=> 1,
	5	=> 1,
	6	=> 1,
	7	=> 1,
	8	=> 1
];

$invoices = Invoice::search(['status', '=', 'invoice'], ['sort' => ['date' => 'asc']])->read( ['id', 'date', 'center_office_id' => ['code']] );

foreach($invoices as $invoice) {
    $year = date('y', $invoice['date']);
    $center = $invoice['center_office_id']['code'];
    $index = $map_centeroffices_indexes[$center];
    $number = sprintf("%2d-%02d-%05d", $year, $center, $index);
    Invoice::id($invoice['id'])->update(['name_old' => $number]);
    ++$map_centeroffices_indexes[$center];
}


$context->httpResponse()
        ->status(204)
        ->send();