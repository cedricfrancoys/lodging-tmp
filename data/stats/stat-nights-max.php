<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\realestate\RentalUnit;
use lodging\identity\Center;
use lodging\identity\User;
use lodging\sale\booking\Consumption;

list($params, $providers) = announce([
    'description'   => 'Returns the theoretical capacity (in nights) for a given period, according to actual state of the accommodations.',
    'params'        => [
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
        'total' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
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
 * @var \equal\data\DataAdapter     $adapter
 * @var \equal\auth\AuthenticationManager $auth
 */
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'] , $providers['auth']];

$result = [];

if(isset($params['center_id']) || $params['all_centers']) {

    $map_centers = [];

    if ($params['all_centers']){

        $user_id = $auth->userId();
        if($user_id <= 0) {
            throw new Exception('user_unknown', QN_ERROR_NOT_ALLOWED);
        }

        $user = User::id($user_id)->read(['centers_ids'])->first(true);
        if(!$user) {
            throw new Exception('unexpected_error', QN_ERROR_INVALID_USER);
        }
        $centers_ids = (array) $user['centers_ids'];
    }

    if($params['center_id'] && !$params['all_centers']) {
        $centers_ids = (array) $params['center_id'];
    }

    if($centers_ids){
        $centers = Center::ids($centers_ids)->read(['name', 'rental_units_ids' => ['is_accomodation', 'capacity', 'parent_id']])->get();
    }

    if ($centers){
        $rental_units = RentalUnit::search([['center_id', 'in', $centers_ids],['is_accomodation', '=', true], ['has_parent', '=', false]])->read(['id', 'capacity', 'center_id'])->get();
    }

    // find number of days available during given date range (= last-first - nb_closed)
    $max_nb_days = ceil(($params['date_to'] - $params['date_from']) / 86400);

    foreach($rental_units as $id => $rental_unit) {
        if(!isset($map_centers[$rental_unit['center_id']])) {
            $map_centers[$rental_unit['center_id']] = 0;
        }
        // discount the number of days during period for which the rental unit is not available
        $consumptions_ids = Consumption::search([
                ['date', '>=', $params['date_from']],
                ['date', '<', $params['date_to']],
                ['type', '=', 'ooo'],
                ['rental_unit_id', '=', $id]
            ])->ids();
        $map_centers[$rental_unit['center_id']] += ($max_nb_days - count($consumptions_ids)) * $rental_unit['capacity'];
    }

    
    foreach($map_centers as $center_id => $total) {
        if(!isset($centers[$center_id])) {
            continue;
        }
        $result[] = [
            'center'    => $centers[$center_id]['name'],
            'total'     => $total
        ];
    }

    if($params['all_centers']) {
        usort($result, function ($a, $b) {
            return strcmp($a['center'], $b['center']);
        });
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
