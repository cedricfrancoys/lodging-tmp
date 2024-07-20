<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\sale\booking\Booking;
use sale\customer\AgeRange;
use lodging\sale\booking\Consumption;

list($params, $providers) = announce([
    'description'   => 'Provides data about current Centers capacities (according to configuration).',
    'params'        => [
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required."
        ],
        'date_from' => [
            'type'          => 'date',
            'description'   => "Last date of the time interval.",
            'default'       => strtotime("-1 Week")
        ],
        'date_to' => [
            'type'          => 'date',
            'description'   => "First date of the time interval.",
            'default'       => strtotime("+1 Week")
        ],
        'is_not_option' => [
            'type'              => 'boolean',
            'description'       => 'Discard quote and option bookings.',
            'default'           =>  true
        ],

        /* parameters used as properties of virtual entity */
        'date' => [
            'type'              => 'date',
            'description'       => 'Date of the consumption.'
        ],
        'age_range_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\AgeRange',
            'description'       => 'Customers age range the product is intended for.'
        ],
        'total' => [
            'type'              => 'integer',
            'description'       => "Total of the consumption.",
        ],
        'total_breakfast' => [
            'type'              => 'integer',
            'description'       => "Total of the consumption for the breakfast.",
        ],
        'total_lunch' => [
            'type'              => 'integer',
            'description'       => "Total of the consumption for the lunch.",
        ],
        'total_diner' => [
            'type'              => 'integer',
            'description'       => "Total of the consumption for the diners.",
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


$center = Center::id($params['center_id'])->read(['id', 'name'])->first(true);

$consumptions = Consumption::search([
            ['center_id', '=', $center['id']],
            ['is_meal', '=', true],
            ['disclaimed', '=', false],
            ['date', '>=', $params['date_from']],
            ['date', '<=', $params['date_to']]
    ])
    ->read([
        'id',
        'date',
        'time_slot_id',
        'schedule_from',
        'schedule_to',
        'booking_id',
        'age_range_id',
        'product_model_id' => ['id','name'],
        'qty'
    ])
    ->get();


$bookings_ids = Booking::search()->ids();
$bookings = Booking::ids($bookings_ids)->read(['id','status','is_cancelled'])->get();

$map_bookings  = [];
foreach($bookings as $booking_id => $booking) {
    if($params['is_not_option'] && in_array($booking['status'], ['quote', 'option'])) {
		continue;
	}
    if($booking['is_cancelled']) {
        continue;
    }
	$map_bookings[$booking_id] = true;
}

$ages_ranges = AgeRange::search()->read(['id', 'name'])->get();

$map_consumptions = [];
foreach($consumptions as $id => $consumption) {

    if(!isset($map_bookings[$consumption['booking_id']])){
        continue;
    }

    $date_index = date('Y-m-d', $consumption['date']);

    if(!isset($map_consumptions[$date_index])) {
        $map_consumptions[$date_index] = [];
    }
    $age_index = $consumption['age_range_id'];

    if(!isset($map_consumptions[$date_index][$age_index])) {
        $map_consumptions[$date_index][$age_index] = [];
    }

    $total_breakfast = 0;
    $total_lunch = 0;
    $total_diner = 0;

    if(isset($consumption['time_slot_id'])) {
        switch($consumption['time_slot_id']) {
            case 1:
                $total_breakfast += $consumption['qty'];
                break;
            case 2:
                $total_lunch += $consumption['qty'];
                break;
            case 4:
                $total_diner += $consumption['qty'];
                break;
        }
    }
    else {
        if(stripos($consumption['product_model_id']['name'], 'matin') !== false) {
            $total_breakfast += $consumption['qty'];
        }
        elseif(stripos($consumption['product_model_id']['name'], 'midi') !== false) {
            $total_lunch += $consumption['qty'];
        }
        elseif(stripos($consumption['product_model_id']['name'], 'soir') !== false) {
            $total_diner += $consumption['qty'];
        }
    }

    $map_consumptions[$date_index][$age_index][] = [
        'date'                       => $consumption['date'],
        'age_range_id'               => $consumption['age_range_id'],
        'total_breakfast'            => $total_breakfast,
        'total_lunch'                => $total_lunch,
        'total_diner'                => $total_diner,
    ];

}

$result = [];

foreach($map_consumptions as $date => $ages) {
    foreach($ages as $age_index => $items) {
        $total_breakfast  = 0 ;
        $total_lunch  = 0 ;
        $total_diner  = 0 ;
        $total = 0;
        foreach($items as $item) {
            $total_breakfast += $item['total_breakfast'];
            $total_lunch += $item['total_lunch'];
            $total_diner += $item['total_diner'];
            $total = $total_breakfast + $total_lunch + $total_diner;
        }
        $result[] = [
            'date'                   => $date,
            'age_range_id'           => isset($ages_ranges[$age_index])? $ages_ranges[$age_index]: $ages_ranges[1],
            'total_breakfast'        => $total_breakfast,
            'total_lunch'            => $total_lunch,
            'total_diner'            => $total_diner,
            'total'                  => $total
        ];
    }
}

usort($result, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
