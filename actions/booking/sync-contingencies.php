<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\channelmanager\Property;

list($params, $providers) = eQual::announce([
    'description'   => "Re-sync Cubilis availability calendar based on Discope, for a given property and month.",
    'params'        => [
        'date' => [
            'type'              => 'date',
            'description'       => 'Date for generating a 1 month interval of the resync.',
            'description'       => "The resulting date is first day the month corresponding to the provided date.",
            'required'          => true
        ],
        'property_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\channelmanager\Property',
            'description'       => 'Property for which resync is requested.',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
list($context) = [ $providers['context'] ];

$property = Property::id($params['property_id'])->read(['is_active', 'room_types_ids' => ['rental_units_ids']])->first(true);

if(!$property) {
    throw new Exception('unknown_property', QN_ERROR_INVALID_PARAM);
}

if(!$property['is_active']) {
    throw new Exception('inactive_property', QN_ERROR_INVALID_PARAM);
}

$map_rental_units_ids = [];

foreach($property['room_types_ids'] as $room_type) {
    foreach($room_type['rental_units_ids'] as $rental_unit_id) {
        $map_rental_units_ids[$rental_unit_id] = true;
    }
}

$result = eQual::run('do', 'lodging_booking_check-contingencies', [
        'date_from'         => strtotime(date("Y-m-01", $params['date'])),
        'date_to'           => strtotime(date("Y-m-t", $params['date'])) + 86400,
        'rental_units_ids'  => array_keys($map_rental_units_ids)
    ]);

$context->httpResponse()
    ->body($result)
    ->send();
