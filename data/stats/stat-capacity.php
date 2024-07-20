<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\realestate\RentalUnit;
use lodging\identity\Center;

list($params, $providers) = announce([
    'description'   => 'Provides data about current Centers capacities (according to configuration).',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required."
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'capacity' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'adapt' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\data\DataAdapter     $adapter
 */
list($context, $orm, $adapter) = [ $providers['context'], $providers['orm'], $providers['adapt'] ];


$map_centers = [];

if(isset($params['center_id'])) {
    $centers_ids = (array) $params['center_id'];
}
else {
    $centers_ids = Center::search()->ids();
}

$centers = Center::ids($centers_ids)->read(['id', 'name'])->get();

$rental_units = RentalUnit::search([['is_accomodation', '=', true], ['has_parent', '=', false], ['can_rent', '=', true]])->read(['id', 'capacity', 'center_id'])->get();

foreach($rental_units as $oid => $rental_unit) {
    if(!isset($map_centers[$rental_unit['center_id']])) {
        $map_centers[$rental_unit['center_id']] = 0;
    }
    $map_centers[$rental_unit['center_id']] += $rental_unit['capacity'];
}

$result = [];

foreach($map_centers as $center_id => $total) {
    if(!isset($centers[$center_id])) {
        continue;
    }
    $result[] = [
        'center'    => $centers[$center_id]['name'],
        'capacity'  => $total
    ];
}

usort($result, function ($a, $b) {
    return strcmp($a['center'], $b['center']);
});

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();