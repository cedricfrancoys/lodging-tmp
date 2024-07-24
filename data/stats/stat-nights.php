<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\catalog\Product;
use lodging\identity\User;

list($params, $providers) = eQual::announce([
    'description'   => 'Provides the count of all nights for a given period.',
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
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'nb_nights' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'adapt', 'auth' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\data\DataAdapter     $adapter
 * @var \equal\auth\AuthenticationManager $auth
 */
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'] , $providers['auth']];

// #memo - we consider all bookings for which at least one sojourn has an intersection with the given period
if($params['center_id'] || $params['all_centers']) {
    $domain = [
            ['date_from', '<=', $params['date_to']],
            ['date_to', '>=', $params['date_from']],
            ['state', 'in', ['instance', 'archive']],
            ['is_cancelled', '=', false],
            ['status', 'not in', ['quote','option']]
        ];
}

if($params['all_centers']) {
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
elseif($params['center_id'] && $params['center_id'] > 0) {
    $domain[] = [ 'center_id', '=', $params['center_id'] ];
}


// #memo - we use Booking rather than Consumption objects since the qty given in consumption can be
// arbitrary and might not match the real number of hosted persons.

$bookings = [];

if($domain){
    $bookings = Booking::search($domain, ['sort'  => ['date_from' => 'asc']])
        ->read([
            'id',
            'center_id' => ['id', 'name']
        ])
        ->get(true);
}

$map_centers_nights = [];

foreach($bookings as $booking) {
    $center_name = $booking['center_id']['name'];
    if(!isset($map_centers_nights[$center_name])) {
        $map_centers_nights[$center_name] = 0;
    }

    $sojourns = BookingLineGroup::search([
            ['booking_id', '=', $booking['id']],
            ['is_sojourn', '=', true]
        ])
        ->read([
            'id',
            'nb_pers',
            'nb_nights',
            'date_from',
            'date_to'
        ])
        ->get(true);

    foreach($sojourns as $sojourn) {

        // adapt nb_nights to consider only the nights in the date range
        $nb_nights = stat_nights_intersection_days($params['date_from'], $params['date_to'], $sojourn['date_from'], $sojourn['date_to']-86400);

        // retrieve all lines relating to an accommodation
        $lines = BookingLine::search([
                ['booking_line_group_id', '=', $sojourn['id']],
                ['is_accomodation', '=', true]
            ])
            ->read([
                'id',
                'qty',
                'price',
                'qty_accounting_method',
                'product_id'
            ])
            ->get(true);

        $sojourn_nb_pers_nights = 0;
        foreach($lines as $line) {
            if($line['price'] < 0 || $line['qty'] < 0) {
                continue;
            }
            // adapt quantity based on accounted nights
            $qty = ($line['qty'] / $sojourn['nb_nights']) * $nb_nights;

            // #memo - qty is impacted by nb_pers and nb_nights but might not be equal to nb_nights x nb_pers
            if($line['qty_accounting_method'] == 'person') {
                $sojourn_nb_pers_nights += $qty;
            }
            // by accommodation
            else {
                $product = Product::id($line['product_id'])->read(['sku', 'product_model_id' => ['id', 'capacity']])->first(true);
                $capacity = $product['product_model_id']['capacity'];

                // #memo - special case for OTA : "NUIT_OTA" is accounted by accommodation (capacity of 1), but the actual accommodation capacity is unknown.
                if($capacity < $sojourn['nb_pers'] && $product['sku'] != 'NUIT_OTA') {
                    // $line['qty'] should be nb_nights * ceil(nb_pers/capacity)
                    $sojourn_nb_pers_nights += $qty * $capacity;
                }
                else {
                    // $line['qty'] should be the number of nights
                    $sojourn_nb_pers_nights += $qty * $sojourn['nb_pers'];
                }
            }
        }
        $map_centers_nights[$center_name] += $sojourn_nb_pers_nights;
    }
}

// linearize the result (there might be several lines for a same center)
$result = [];
foreach($map_centers_nights as $center_name => $qty) {
    $result[] = [
        'center'    => $center_name,
        'nb_nights' => $qty
    ];
}

if($params['all_centers']) {
    usort($result, function ($a, $b) {
        return strcmp($a['center'], $b['center']);
    });
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();


function stat_nights_intersection_days($start1, $end1, $start2, $end2) {
    $start = max($start1, $start2);
    $end = min($end1, $end2);

    if ($start > $end) {
        return 0;
    }
    else {
        return floor(($end - $start) / (60 * 60 * 24)) + 1;
    }
}