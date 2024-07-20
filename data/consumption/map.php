<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Consumption;


list($params, $providers) = announce([
    'description'   => "Retrieve the consumptions attached to rental units of specified centers and return an associative array mapping rental units and ate indexes with related consumptions (this controller is used for the planning).",
    'params'        => [
        'centers_ids' =>  [
            'description'   => 'Identifiers of the centers for which the consumptions are requested.',
            'type'          => 'array',
            'required'      => true
        ],
        'date_from' => [
            'description'   => 'Start of time-range for the lookup.',
            'type'          => 'date',
            'required'      => true
        ],
        'date_to' => [
            'description'   => 'End of time-range for the lookup.',
            'type'          => 'date',
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user', 'booking.infra.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'adapt']
]);


list($context, $orm, $auth, $adapter) = [$providers['context'], $providers['orm'], $providers['auth'], $providers['adapt']];

// get associative array mapping rental units and dates with consumptions
$result = Consumption::getExistingConsumptions(
        $orm,
        $params['centers_ids'],
        $params['date_from'],
        $params['date_to']
    );

$consumptions_ids = [];
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions) {
        foreach($consumptions as $consumption) {
            $consumptions_ids[] = $consumption['id'];
        }
    }
}

// read additional fields for the view
$consumptions = Consumption::ids($consumptions_ids)
    ->read([
        'date',
        'schedule_from',
        'schedule_to',
        'is_rental_unit',
        'qty',
        'type',
        'customer_id'       => ['id', 'name'],
        'rental_unit_id'    => ['id', 'name'],
        'booking_id'        => ['id', 'name', 'status', 'description', 'payment_status'],
        'repairing_id'      => ['id', 'name', 'description']
    ])
    ->adapt('txt')
    ->get();

// enrich and adapt result
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions_list) {
        foreach($consumptions_list as $c_index => $consumption) {
            // retrieve consumption's data and adapt dates and times
            $odata = $consumptions[$consumption['id']];
            $result[$rental_unit_id][$date_index][$c_index] = array_merge($consumption, $odata, [
                'date_from'     => $adapter->adapt($consumption['date_from'], 'date', 'txt', 'php'),
                'date_to'       => $adapter->adapt($consumption['date_to'], 'date', 'txt', 'php'),
                'schedule_from' => $adapter->adapt($consumption['schedule_from'], 'time', 'txt', 'php'),
                'schedule_to'   => $adapter->adapt($consumption['schedule_to'], 'time', 'txt', 'php')
            ]);
        }
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
