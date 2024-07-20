<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Consumption;


list($params, $providers) = announce([
    'description'   => "This controller is meant for the public calendar (allowing visitors to see Centers availability). We expect only centers with a single rental unit (group lodges).",
    'params'        => [
        'centers_ids' =>  [
            'description'   => 'Identifiers of the centers for which the consumptions are requested.',
            'type'          => 'array',
            'required'      => true
        ],
        'rental_unit_id' =>  [
            'description'   => 'Identifiers of the rental unit for which the consumptions are requested.',
            'type'          => 'integer',
            'default'      => 0
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'adapt']
]);


list($context, $orm, $adapter) = [$providers['context'], $providers['orm'], $providers['adapt']];

$date_from = time();
$date_to = strtotime('+2 years');

// get associative array mapping rental units and dates with consumptions
$result = Consumption::getExistingConsumptions(
        $orm,
        $params['centers_ids'],
        $date_from,
        $date_to
    );


$output = [];
// enrich and adapt result
foreach($result as $rental_unit_id => $dates) {
    foreach($dates as $date_index => $consumptions) {
        // we deal with rental_unit as a binary status (free/busy), so we only consider the first consumption
        $consumption = reset($consumptions);
        if($params['rental_unit_id'] > 0) {
            if($consumption['rental_unit_id'] != $params['rental_unit_id']) {
                continue;
            }
        }
        $output[] = [
            'date_from'         => $adapter->adapt($consumption['date_from'], 'date', 'txt', 'php'),
            'date_to'           => $adapter->adapt($consumption['date_to'], 'date', 'txt', 'php'),
            'type_consumption'  => $adapter->adapt($consumption['type'], 'string', 'txt', 'php'),
        ];
    }
}
$context->httpResponse()
        ->body($output)
        ->send();
