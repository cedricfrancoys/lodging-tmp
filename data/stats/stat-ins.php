<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\identity\Identity;
use lodging\sale\catalog\Product;

list($params, $providers) = announce([
    'description'   => 'Provides data for mandatory INS statistics declaration (Statbel).',
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
        'nb_pers' => [
            'type'              => 'integer',
            'description'       => 'Number of hosted persons.'
        ],
        'nb_nights' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
        ],
        'invoiced_nights' => [
            'type'              => 'integer',
            'description'       => 'Quantity of invoiced nights (theorically nb_pers x nb_nights).'
        ],
        'nb_rental_units' => [
            'type'              => 'integer',
            'description'       => 'Number of rental units (accommodations) involved in the sojourn.'
        ],
        'ref_booking' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_name' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_zip' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_region' => [
            'type'              => 'string',
            'description'       => 'Customer region (BE only).'
        ],
        'customer_country' => [
            'type'              => 'string',
            'usage'             => 'country/iso-3166:2',
            'description'       => 'Customer country.'
        ],
        'customer_lang' => [
            'type'              => 'string',
            'description'       => 'Customer lang.'
        ],
        'purpose_of_stay' => [
            'type'              => 'integer',
            'description'       => ""
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

// #memo - we consider all bookings for which at least one sojourn finishes during the given period
// #memo - only date_to matters : we collect all bookings that finished during the selection period (this is also the way stats are done in the accounting software)

$result = [];

if(isset($params['center_id']) || $params['all_centers']) {
    $domain = [
        ['date_to', '>=', $params['date_from'] ],
        ['date_to', '<=', $params['date_to'] ],
        ['state', 'in', ['instance', 'archive']],
        ['is_cancelled', '=', false],
        ['status', 'not in', ['quote','option']]
    ];

    if($params['center_id'] && !$params['all_centers']) {
        $domain[] = [ 'center_id', '=', $params['center_id'] ];
    }

    $bookings = [];

    if($domain){
        $bookings = Booking::search($domain, ['sort'  => ['date_to' => 'asc']])
        ->read([
            'id',
            'name',
            'center_id'                 => ['id', 'name'],
            'customer_identity_id'      => ['id', 'name', 'address_zip', 'address_country', 'lang_id' => ['code'] ]
        ])
        ->get(true);
    }

    foreach($bookings as $booking) {
        // find all sojourns
        $sojourns = BookingLineGroup::search([
                ['booking_id', '=', $booking['id']],
                ['is_sojourn', '=', true]
            ])
            ->read([
                'id',
                'date_from',
                'date_to',
                'is_sojourn',
                'nb_pers',
                'nb_nights',
                'rental_unit_assignments_ids' => ['id', 'is_accomodation', 'qty']
            ])
            ->get(true);

        foreach($sojourns as $sojourn) {
            $rental_unit_assignments = array_filter($sojourn['rental_unit_assignments_ids'], function($a) {return $a['is_accomodation'];});
            $count_rental_unit_assignments = count($rental_unit_assignments);

            if(!$count_rental_unit_assignments) {
                continue;
            }

            // find all lines relating to an accommodation
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
                // #memo - qty is impacted by nb_pers and nb_nights but might not be equal to nb_nights x nb_pers
                if($line['qty_accounting_method'] == 'person') {
                    $sojourn_nb_pers_nights += $line['qty'];
                }
                // by accommodation
                else {
                    $product = Product::id($line['product_id'])->read(['sku', 'product_model_id' => ['id', 'capacity']])->first(true);
                    $capacity = $product['product_model_id']['capacity'];

                    // #memo - special case for OTA :  "NUIT_OTA" is accounted for in lodging, but the actual lodging (capacity of 1) is unknown.
                    if($capacity < $sojourn['nb_pers'] && $product['sku'] != 'NUIT_OTA') {
                        // $line['qty'] should be nb_nights * ceil(nb_pers/capacity)
                        $sojourn_nb_pers_nights += $line['qty'] * $capacity;
                    }
                    else {
                        // $line['qty'] should be either the number of nights
                        $sojourn_nb_pers_nights += $line['qty'] * $sojourn['nb_pers'];
                    }
                }
            }

            //$sojourn_nb_pers_nights = array_reduce($lines, function($c, $a) { return $c + $a['qty'];}, 0);

            $result[] = [
                'center'            => $booking['center_id']['name'],
                'date_from'         => $adapter->adapt($sojourn['date_from'], 'date', 'txt', 'php'),
                'date_to'           => $adapter->adapt($sojourn['date_to'], 'date', 'txt', 'php'),
                'nb_pers'           => $sojourn['nb_pers'],
                'nb_nights'         => $sojourn['nb_nights'],
                'invoiced_nights'   => $sojourn_nb_pers_nights,
                'ref_booking'       => $booking['name'],
                'purpose_of_stay'   => 1,
                'nb_rental_units'   => $count_rental_unit_assignments,
                'customer_name'     => $booking['customer_identity_id']['name'],
                'customer_zip'      => $booking['customer_identity_id']['address_zip'],
                'customer_country'  => $booking['customer_identity_id']['address_country'],
                'customer_region'   => Identity::_getRegionByZip($booking['customer_identity_id']['address_zip'], $booking['customer_identity_id']['address_country']),
                'customer_lang'     => $booking['customer_identity_id']['lang_id']['code'],
            ];
        }
    }


    if($params['all_centers']) {
        usort($result, function ($a, $b) {
            return strcmp($a['center'], $b['center']);
        });
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
