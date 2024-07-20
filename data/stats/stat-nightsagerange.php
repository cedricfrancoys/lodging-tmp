<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use sale\customer\AgeRange;
use sale\customer\RateClass;
use sale\customer\CustomerNature;

list($params, $providers) = announce([
    'description'   => '',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required."
        ],

        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\CenterOffice',
            'description'       => 'Office the invoice relates to (for center management).'
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
        'age_range_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\AgeRange',
            'description'       => 'Specific age range to limit the result to.'
        ],

        'rate_class_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\RateClass',
            'description'       => 'Rate class that applies to the customer.'
        ],


        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],

        'rate_class' => [
            'type'              => 'string',
            'description'       => 'Name of the rate class.'
        ],

        'customer_nature' => [
            'type'              => 'string',
            'description'       => 'Name of the customer nature.'
        ],

        'age_range' => [
            'type'              => 'string',
            'description'       => 'Code of the age range.'
        ],
        'nb_pers' => [
            'type'              => 'integer',
            'description'       => 'Number of hosted persons.'
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
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'] ,$providers['auth'] ];

$domain = [];

$map_age_ranges = AgeRange::search()->read(['id', 'name'])->get();

$map_rate_class = RateClass::search()->read(['id', 'name', 'description'])->get();
$map_customer_nature = CustomerNature::search()->read(['id', 'code', 'description'])->get();

// #memo - we consider all bookings for which at least one sojourn starts during the given period
if ($params['center_id'] || $params['center_office_id']){
    $domain = [
        ['date_from', '>=', $params['date_from'] ],
        ['date_from', '<=', $params['date_to'] ],
        ['state', 'in', ['instance', 'archive']],
        ['is_cancelled', '=', false],
        ['status', 'not in', ['quote','option']]
    ];

    if($params['center_id'] && $params['center_id'] > 0) {
        $domain[] = [ 'center_id', '=', $params['center_id'] ];
    }

    if($params['center_office_id'] && $params['center_office_id'] > 0) {
        $domain[] = [ 'center_office_id', '=', $params['center_office_id'] ];
    }
}

if($domain){
    $bookings = Booking::search($domain)
    ->read([
        'id',
        'booking_lines_groups_ids',
        'center_id'                 => ['id', 'name'],
        'customer_id'               => ['customer_nature_id', 'rate_class_id']
    ])
    ->get(true);
}



if($params['rate_class_id'] && $params['rate_class_id'] > 0) {
    $bookings = array_filter($bookings, function ($booking) use ($params) {
    $rate_class_id = $booking['customer_id']['rate_class_id'];
    return isset($rate_class_id) && $rate_class_id == $params['rate_class_id'];
    });
}

$map_centers = [];

foreach($bookings as $booking) {

    $center_id = $booking['center_id']['id'];
    $rate_class_id = $booking['customer_id']['rate_class_id'];
    $customer_nature_id =  $booking['customer_id']['customer_nature_id'];

    if(!isset($map_centers[$center_id])) {
        $map_centers[$center_id] = [];
    }

    if(!isset($map_centers[$center_id][$rate_class_id])) {
        $map_centers[$booking[$center_id][$rate_class_id]] = [];
    }

    if(!isset($map_centers[$center_id][$rate_class_id][$customer_nature_id])) {
        $map_centers[$booking[$center_id][$rate_class_id][$customer_nature_id]] = [];
    }

    // find all sojourns
    $groups = BookingLineGroup::search([
            ['id', 'in', $booking['booking_lines_groups_ids']],
            ['is_sojourn', '=', true]
        ])
        ->read([
            'id',
            'nb_pers',
            'nb_nights',
            'age_range_assignments_ids' => ['qty', 'age_range_id'],
            'booking_lines_ids'
        ])
        ->get(true);

    foreach($groups as $group) {

        $lines = BookingLine::search([
                ['id', 'in', $group['booking_lines_ids']],
                ['is_accomodation', '=', true]
            ])
            ->read([
                'id',
                'qty',
                'price',
                'is_accomodation',
                'product_id' => ['has_age_range', 'age_range_id' => ['id', 'name']]
            ])
            ->get(true);

        $group_age_range_id = 0;
        if(count($group['age_range_assignments_ids']) == 1) {
            $age_range_assignment = reset($group['age_range_assignments_ids']);
            $group_age_range_id = $age_range_assignment['age_range_id'];
            // discard groups not matching given age_range
            if(isset($params['age_range_id']) && $group_age_range_id != $params['age_range_id']) {
                continue;
            }
        }

        foreach($lines as $line) {
            if($line['price'] < 0 || $line['qty'] < 0) {
                continue;
            }

            $age_range_id = $group_age_range_id;
            $nb_pers = $group['nb_pers'];
            if($line['product_id']['has_age_range']) {
                $age_range_id = $line['product_id']['age_range_id']['id'];
                // discard lines not matching given age_range
                if(isset($params['age_range_id']) && $age_range_id != $params['age_range_id']) {
                    continue;
                }
                foreach($group['age_range_assignments_ids'] as $age_range_assignment) {
                    if($age_range_assignment['id'] == $age_range_id) {
                        $nb_pers = $age_range_assignment['qty'];
                        break;
                    }
                }
            }
            $rate_class = $map_rate_class[$booking['customer_id']['rate_class_id']];
            $customer_nature = $map_customer_nature[$booking['customer_id']['customer_nature_id']];

            if(!isset($map_centers[$center_id][$rate_class_id][$customer_nature_id][$age_range_id])) {
                $map_centers[$center_id][$rate_class_id][$customer_nature_id][$age_range_id] = [
                    'center'                => $booking['center_id']['name'],
                    'rate_class'            => $rate_class['name'].' - '.$rate_class['description'],
                    'customer_nature'       => $customer_nature['description'],
                    'nb_pers'               => $nb_pers,
                    'nb_nights'             => $group['nb_nights'] * $nb_pers,
                    'age_range'             => ($age_range_id)?$map_age_ranges[$age_range_id]['name']:'tous les ages'
                ];
            }
            else {
                $map_centers[$center_id][$rate_class_id][$customer_nature_id][$age_range_id]['nb_pers'] += $nb_pers;
                $map_centers[$center_id][$rate_class_id][$customer_nature_id][$age_range_id]['nb_nights'] += ($group['nb_nights'] * $nb_pers);
            }

        }

    }
}

// linearize the result (there might be several lines for a same center)
$result = [];
foreach($map_centers as $center) {
    foreach($center as $rate_class_id) {
        foreach($rate_class_id as $customer_nature) {
            foreach($customer_nature as $age_range) {
                $result[] = $age_range;
            }
        }
    }
}
$context->httpResponse()
        ->header('X-Total-Count', count($bookings))
        ->body($result)
        ->send();
