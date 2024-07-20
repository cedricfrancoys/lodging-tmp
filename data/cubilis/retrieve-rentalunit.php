<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\realestate\RentalUnit;
use lodging\sale\booking\channelmanager\Property;
use lodging\sale\booking\channelmanager\RoomType;
use lodging\sale\booking\Consumption;

list($params, $providers) = announce([
    'description'   => "Resolve a Rental Unit based on a given property and room type. \
                        If the there are one or more candidates, the first available rental unit is returned. \
                        If no rental unit is available, an error is raised.",
    'params'        => [
        'property_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\channelmanager\Property',
            'description'       => 'Identifier of the targeted property.',
            'required'          => true
        ],
        'extref_room_type_id' => [
            'type'              => 'integer',
            'description'       => 'Identifier of the targeted property.',
            'required'          => true
        ],
        'date_from' => [
            'type'              => 'datetime',
            'description'       => 'Date and time of the checkin.',
            'required'          => true
        ],
        'date_to' => [
            'type'              => 'datetime',
            'description'       => 'Date and time of the checkout.',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected',
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

$property = Property::id($params['property_id'])
    ->read(['id', 'extref_property_id', 'center_id', 'center_office_id'])
    ->first(true);

if(!$property) {
    throw new Exception('unknown_property', QN_ERROR_UNKNOWN_OBJECT);
}

$room_type = RoomType::search(['extref_roomtype_id', '=', $params['extref_room_type_id']])
    ->read(['id', 'rental_units_ids'])
    ->first(true);

if(!$room_type) {
    throw new Exception('unknown_room_type', QN_ERROR_UNKNOWN_OBJECT);
}

$existing_consumptions_map = Consumption::getExistingConsumptions($orm, [$property['center_id']], $params['date_from'], $params['date_to']);

$booked_rental_units_ids = [];

foreach($existing_consumptions_map as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions) {
        foreach($consumptions as $consumption) {
            $consumption_from = $consumption['date_from'] + $consumption['schedule_from'];
            $consumption_to = $consumption['date_to'] + $consumption['schedule_to'];
            // #memo - we don't allow instant transition (i.e. checkin time of a booking equals checkout time of a previous booking)
            if(max($params['date_from'], $consumption_from) < min($params['date_to'], $consumption_to)) {
                $booked_rental_units_ids[] = $rental_unit_id;
                continue 3;
            }
        }
    }
}

$rental_units_ids = array_diff($room_type['rental_units_ids'], $booked_rental_units_ids);

if(!count($rental_units_ids)) {
    throw new Exception('no_rentalunit_candidate', QN_ERROR_CONFLICT_OBJECT);
}

$result = RentalUnit::ids($rental_units_ids)->read(['id', 'capacity'])->get(true);

$context->httpResponse()
        ->body($result)
        ->send();
