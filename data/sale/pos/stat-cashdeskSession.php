<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\identity\User;
use lodging\sale\pos\CashdeskSession;

list($params, $providers) = announce([
    'description'   => 'Provides closed cashdesk sessions statistics.',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => 'Output: Center of the sojourn / Input: The centers for which the stats are required.'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => 'Last date of the time interval.',
            'default'           => strtotime('-1 Week')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'First date of the time interval.',
            'default'           => strtotime('today')
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'date_closing' => [
            'type'              => 'date',
            'description'       => 'Date of the closing.'
        ],
        'total_opening' => [
            'type'              => 'float',
            'description'       => "Amount of money in the cashdesk at the opening.",
        ],
        'total_closing' => [
            'type'              => 'float',
            'description'       => "Amount of money in the cashdesk at the closing.",
        ],
        'total_cash' => [
            'type'              => 'float',
            'description'       => 'Amount of money in the cashdesk by cash.'
        ],
        'total_bank_card' => [
            'type'              => 'float',
            'description'       => 'Amount of money in the cashdesk by bank card.'
        ],
        'total_voucher' => [
            'type'              => 'float',
            'description'       => 'Amount of money in the cashdesk by voucher.'
        ],
        'total_received' => [
            'type'              => 'float',
            'description'       => 'Amount of money received in the cashdesk.'
        ],
        'total_operations_in' => [
            'type'              => 'float',
            'description'       => 'Amount of money operations in at  the cashdesk.'
        ],
        'total_operations_out' => [
            'type'              => 'float',
            'description'       => 'Amount of money operations out in the cashdesk.'
        ],
        'total_operations' => [
            'type'              => 'float',
            'description'       => 'Amount of money operations in the cashdesk.'
        ],
        'total_expected' => [
            'type'              => 'float',
            'description'       => 'Amount of money expected in the cashdesk.'
        ],
        'total_remaining' => [
            'type'              => 'float',
            'description'       => 'Amount of money remaining in the cashdesk.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                   $context
 * @var \equal\auth\AuthenticationManager    $auth
 */
list($context, $auth) = [ $providers['context'], $providers['auth'] ];

$map_centers = [];

$center_domain = [];
if(!empty($params['center_id'])) {
    $center_domain = ['id', 'in', $params['center_id']];
}
else {
    $auth_user = User::id($auth->userId())->read(['centers_ids'])->first();
    $center_domain = ['id', 'in', $auth_user['centers_ids']];
}
$centers = Center::search($center_domain)->read(['id', 'name'])->get();

$cashdesksessions = CashdeskSession::search(
    [
        ['center_id', 'in', array_column($centers, 'id')],
        ['status', '=', 'closed'],
        ['created', '>=', $params['date_from']],
        ['date_closing', '<', $params['date_to'] + 60 * 60 * 24],
    ],
    ['sort' => ['date_closing' => 'asc']]
)
    ->read([
        'id',
        'date_closing',
        'amount_closing',
        'amount_opening',
        'center_id',
        'orders_ids' => ['price', 'order_payment_parts_ids' => ['payment_method', 'amount']],
        'operations_ids' => ['id', 'type', 'amount']
    ])
    ->get();

foreach($cashdesksessions as $id => $cashdesksession) {

    $date_index = date('Y-m-d', $cashdesksession['date_closing']);

    if(!isset($map_centers[$cashdesksession['center_id']])) {
        $map_centers[$cashdesksession['center_id']] = [];
    }

    if(!isset($map_centers[$cashdesksession['center_id']][$date_index])) {
        $map_centers[$cashdesksession['center_id']][$date_index] = [];
    }

    $total_opening = $cashdesksession['amount_opening'];

    $total_cash = 0.0;
    $total_bank_card = 0.0;
    $total_voucher = 0.0;

    foreach($cashdesksession['orders_ids']  as $order) {
        $order_totals = ['cash' => 0, 'bank_card' => 0, 'voucher' => 0];
        foreach($order['order_payment_parts_ids'] as $order_payment_part) {

            if ($order_payment_part['payment_method'] == 'booking'){
                continue;
            }

            $order_totals[$order_payment_part['payment_method']] += $order_payment_part['amount'];
        }

        $total_cash += $order['price'] - $order_totals['bank_card'] - $order_totals['voucher'];
        $total_bank_card += $order_totals['bank_card'];
        $total_voucher += $order_totals['voucher'];
    }

    $total_operations_in = 0.0;
    $total_operations_out = 0.0;
    $total_operations = 0.0;

    foreach($cashdesksession['operations_ids'] as $operation) {

        if($operation['type'] !== 'move') {
            continue;
        }

        if ($operation['amount'] > 0) {
            $total_operations_in += $operation['amount'];
        }
        else {
            $total_operations_out -= $operation['amount'];
        }

        $total_operations += $operation['amount'];
    }

    $map_centers[$cashdesksession['center_id']][$date_index][] = [
        'date_closing'               => $cashdesksession['date_closing'],
        'total_opening'              => $total_opening,
        'total_closing'              => $cashdesksession['amount_closing'],
        'total_cash'                 => $total_cash,
        'total_bank_card'            => $total_bank_card,
        'total_voucher'              => $total_voucher,
        'total_operations_in'        => $total_operations_in,
        'total_operations_out'       => $total_operations_out,
        'total_operations'           => $total_operations,
    ];
}


$result = [];

foreach($map_centers as $center_id => $dates) {
    foreach($dates as $date_index => $items) {
        foreach($items as $item) {
            $total_received = $item['total_cash'] + $item['total_operations'];
            $total_expected = $item['total_opening'] + $total_received;
            $total_remaining = $item['total_closing'] - $total_expected;
            $result[] = [
                'date_closing'           => date('c', $item['date_closing']),
                'center'                 => $centers[$center_id]['name'],
                'total_opening'          => round($item['total_opening'], 2),
                'total_closing'          => round($item['total_closing'], 2),
                'total_cash'             => round($item['total_cash'], 2),
                'total_bank_card'        => round($item['total_bank_card'], 2),
                'total_voucher'          => round($item['total_voucher'], 2),
                'total_operations_in'    => round($item['total_operations_in'], 2),
                'total_operations_out'   => round(-$item['total_operations_out'], 2),
                'total_operations'       => round($item['total_operations'], 2),
                'total_received'         => round($total_received, 2),
                'total_expected'         => round($total_expected, 2),
                'total_remaining'        => round($total_remaining, 2),
            ];
        }
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
