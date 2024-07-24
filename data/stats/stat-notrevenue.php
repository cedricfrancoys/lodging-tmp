<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\stats\StatSection;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\identity\User;
list($params, $providers) = announce([
    'description'   => 'Lists all contracts and their related details for a given period.',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required.",
            'visible'           => ['all_centers', '=', false]
        ],
        'all_centers' => [
            'type'              => 'boolean',
            'default'           =>  false,
            'description'       => "Mark the all Center of the sojourn."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m"), 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m")+1, 0)
        ],
        /* parameters used as properties of virtual entity */

        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'aamm' => [
            'type'              => 'string',
            'description'       => 'Year and month.'
        ],
        'bookings' => [
            'type'              => 'float',
            'description'       => 'Name of the center.'
        ],
        'nights' => [
            'type'              => 'float',
            'description'       => 'Total revenue from nights.'
        ],
        'animations' => [
            'type'              => 'float',
            'description'       => 'Total revenue from animations.'
        ],
        'meals' => [
            'type'              => 'float',
            'description'       => 'Total revenue from meals.'
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'adapt','auth' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\data\DataAdapter     $adapter
 * @var \equal\auth\AuthenticationManager $auth
 */
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'] , $providers['auth']];

// #memo - we consider all bookings for which at least one sojourn starts during the given period
if ($params['center_id'] || $params['all_centers']){
    $domain = [
        ['date_from', '>=', $params['date_from'] ],
        ['date_from', '<=', $params['date_to'] ],
        ['state', 'in', ['instance', 'archive']],
        ['status', 'in', ['validated', 'checkedin', 'checkedout']]
    ];
}

if ($params['all_centers']){

    $user_id = $auth->userId();
    if($user_id <= 0) {
        throw new Exception('user_unknown', QN_ERROR_NOT_ALLOWED);
    }

    $user = User::id($user_id)->read(['centers_ids'])->first(true);

    if(!$user) {
        throw new Exception('unexpected_error', QN_ERROR_INVALID_USER);
    }

    $domain[] = ['center_id', 'in', $user['centers_ids'] ];
}

if($params['center_id'] && $params['center_id'] > 0) {
    $domain[] = [ 'center_id', '=', $params['center_id'] ];
}

$bookings = [];

if($domain){
    $bookings = Booking::search($domain, ['sort'  => ['date_from' => 'asc']])
        ->read([
                'id',
                'created',
                'name',
                'date_from',
                'date_to',
                'total',
                'price',
                'center_id'  => ['id', 'name', 'center_office_id']
            ])
        ->get(true);
}

$result = [];

// create map for statistic sections
$stats = StatSection::search(['code', 'in', ['GITE', 'SEJ', 'RST', 'ANIM']])->read(['id', 'code'])->get(true);
// map stats mapping stats id with their code
$map_stats = [];
foreach($stats as $stat) {
    $map_stats[$stat['id']] = $stat['code'];
}


// associative array mapping centers with each date index
$map_center_values = [];

foreach($bookings as $booking) {

    $lines = BookingLine::search([
            ['booking_id', '=', $booking['id']]
        ])
        ->read([
            'id',
            'total',
            'price',
            'product_model_id' => ['id', 'stat_section_id']
        ])
        ->get(true);

    $date_index = date('Ym', $booking['date_from']);
    if(!isset($map_center_values[$booking['center_id']['name']])) {
        $map_center_values[$booking['center_id']['name']] = [];
    }

    if(!isset($map_center_values[$booking['center_id']['name']][$date_index])) {
        $map_center_values[$booking['center_id']['name']][$date_index] = [
            'center'            => $booking['center_id']['name'],
            'aamm'              => date('Y/m', $booking['date_from']),
            'bookings'          => 0,
            'nights'            => 0,
            'animations'        => 0,
            'meals'             => 0
        ];
    }

    $map_center_values[$booking['center_id']['name']][$date_index]['bookings'] += $booking['total'];

    foreach($lines as $line) {
        $stat_id = $line['product_model_id']['stat_section_id'];
        if(!isset($map_stats[$stat_id])) {
            continue;
        }
        $code = $map_stats[$stat_id];
        switch($code) {
            case 'GITE':
            case 'SEJ':
                $map_center_values[$booking['center_id']['name']][$date_index]['nights'] += $line['total'];
                break;
            case 'RST':
                $map_center_values[$booking['center_id']['name']][$date_index]['meals'] += $line['total'];
                break;
            case 'ANIM':
                $map_center_values[$booking['center_id']['name']][$date_index]['animations'] += $line['total'];
                break;
        }
    }
}

// build final result
$result = [];
foreach($map_center_values as $center => $dates) {
    foreach($dates as $date_index => $item) {
        $result[] = $item;
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
