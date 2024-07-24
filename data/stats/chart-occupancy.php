<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\realestate\RentalUnit;
use lodging\sale\booking\Consumption;
use lodging\identity\User;


list($params, $providers) = announce([
    'description'   => 'Computes the occupancy rate for a given date range.',
    'extends'       => 'core_model_chart',
    'params'        => [
        /* overloaded params */
        'entity'    => [
            'type'              => 'string',
            'default'           => 'lodging\sale\booking\Consumption'
        ],
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'all_centers' => [
            'type'              => 'boolean',
            'description'       => "Mark the all Center of the sojourn.",
            'default'           =>  false
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required.",
            'visible'           => ['all_centers', '=', false]
        ],
        'range_from' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'range_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],
        'range_interval' => [
            'description'   => 'Time interval for grouping abscissa values.',
            'type'          => 'string',
            'selection'     => [
                'week',
                'month',
                'year'
            ],
            'default'       => 'month'
        ]
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
 * @var \equal\auth\AuthenticationManager $auth
 */
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'] , $providers['auth']];


/*
This controller computes capacity rate of the centers.

Here is the logic;

we slice the requested date range into smaller ranges of range_interval days, then

for each range
    for each day of the range
	    for each center
		    for each accommodation
			    we add up the center capacity for the range
		    for all repairings 'ooo'
			    we decrease the capacity of the center for the range
		    for all consumptions 'book'
			    we add up the capacity of the rental unit to the occupancy of the center for the range

*/

$user_id = $auth->userId();
if($user_id <= 0) {
    throw new Exception('user_unknown', QN_ERROR_NOT_ALLOWED);
}

$results = [];

$centers_ids = [];


if($params['all_centers']) {
    $user = User::id($user_id)->read(['centers_ids'])->first(true);
    if(!$user) {
        throw new Exception('unexpected_error', QN_ERROR_INVALID_USER);
    }
    $centers_ids = (array) $user['centers_ids'];
}
elseif($params['center_id'] && $params['center_id'] > 0) {
    $centers_ids = (array) $params['center_id'];
}

if($centers_ids) {
    $centers = Center::ids($centers_ids)->read(['name', 'rental_units_ids' => ['is_accomodation', 'capacity', 'parent_id']])->get();

    if($centers) {
        // initialize results_map as an empty associative array of intervals map
        $index_map = [];
        $next_date = $params['range_from'];
        while($next_date < $params['range_to']) {
            $index = _occupancies_get_date_index($next_date, $params['range_interval']);
            $prev_date = $next_date;
            $next_date = _occupancies_get_next_date($next_date, $params['range_interval']);
            // compute number of days
            $index_map[$index] = round( ($next_date-$prev_date)/86400 );
        }
    }

    $results_map = [];

    foreach($centers_ids as $center_id) {
        $center = $centers[$center_id];
        $capacity = 0;
        foreach($center['rental_units_ids'] as $rental_unit) {
            // consider only accommodation rooms that have no parent rental unit
            if($rental_unit['is_accomodation'] && !((bool) $rental_unit['parent_id'])) {
                $capacity += $rental_unit['capacity'];
            }
        }
        $results_map[$center_id] = [];
        foreach($index_map as $index => $days) {
            $results_map[$center_id][$index] = [
                'capacity'  => $capacity * $days,
                'occupancy' => 0
            ];
        }
    }

    // create a map for rental_units
    $rental_units = RentalUnit::search([['center_id', 'in', $centers_ids],['is_accomodation', '=', true]])->read(['id', 'name', 'capacity'])->get();


    for($d = $params['range_from']; $d <= $params['range_to']; $d = strtotime('+1 day', $d)) {
        $consumptions = Consumption::search([['center_id', 'in', $centers_ids], ['is_accomodation', '=', true], ['date', '=', $d] ])
            ->read([
                'id',
                'type',
                'qty',
                'rental_unit_id',
                'center_id',
                'schedule_to',
                'booking_id.status'
            ])
            ->get(true);
        $date_index = _occupancies_get_date_index($d, $params['range_interval']);

        foreach($consumptions as $consumption) {

            // do not consider consumptions of quote bookings
            if($consumption['booking_id.status'] == 'quote') {
                continue;
            }
            // do not consider bookings last day (that marks the unit as occupied until checkout time but must not be considered as a 'night')
            if($consumption['schedule_to'] < 86400) {
                continue;
            }

            if(isset($results_map[$consumption['center_id']][$date_index]) && isset($rental_units[$consumption['rental_unit_id']]) ) {
                if($consumption['type'] == 'ooo') {
                    $results_map[$consumption['center_id']][$date_index]['capacity'] -= $rental_units[$consumption['rental_unit_id']]['capacity'];
                }
                elseif($consumption['type'] == 'book') {
                    $results_map[$consumption['center_id']][$date_index]['occupancy'] += $consumption['qty'];
                }
            }

        }
    }

    if($params['mode'] == 'chart') {
        $results['labels'] = array_keys($index_map);
        $results['legends'] = [];
        $results['datasets'] = [];
    }
    foreach($results_map as $center_id => $map) {
        if($params['mode'] == 'chart') {
            $results['legends'][] = $centers[$center_id]['name'];
            $item = [];
        }
        else {
            $item = [
                "#label" => $centers[$center_id]['name']
            ];
        }

        foreach($map as $index => $values) {
            $res = 0;
            if($values['capacity'] > 0) {
                $res = round($values['occupancy'] / $values['capacity'] * 100, 2).'%';
            }
            if($params['mode'] == 'chart') {
                $item[] = $res;
            }
            else {
                $item[$index] = $res;
            }
        }
        if($params['mode'] == 'chart') {
            $results['datasets'][] = $item;
        }
        else {
            $results[] = $item;
        }
    }

    if($params['all_centers']) {
        usort($result, function ($a, $b) {
            return strcmp($a['center'], $b['center']);
        });
    }
}

$context->httpResponse()
        ->body($results)
        ->send();



function _occupancies_get_date_index($date, $interval) {
    switch($interval) {
        case 'week':
            return date('Y-W', $date);
        case 'month':
            return date('Y-m', $date);
        case 'year':
            return date('Y', $date);
    }
}

/**
 * Compute the next date according to the interval type.
 * We add one day so that the diff includes all nights of the period (ex. 1 to 31 = 31 nights)
 * #memo - dates are expressed in seconds (mind leap seconds).
 */
function _occupancies_get_next_date($date, $interval) {
    switch($interval) {
        case 'week':
            $day = date("w", $date);
            // convert to ISO day index (1: Mo, 7: Su)
            $day = ($day == 0)?7:$day;
            return $date + ((8-$day)*86400);
        case 'month':
            return strtotime(date("Y-m-t", $date)) + 86400;
        case 'year':
            return strtotime((date('Y', $date) + 1).'-01-01');
    }
}