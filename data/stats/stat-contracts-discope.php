<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\catalog\Product;

list($params, $providers) = eQual::announce([
    'description'   => 'Lists all contracts and their related details for a given period.',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required."
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
        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Identity',
            'description'       => "The organisation the establishment belongs to.",
            'domain'            => ['id', '<', 6]
        ],

        /* parameters used as properties of virtual entity */

        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'center_type' => [
            'type'              => 'string',
            'selection'         => [
                'GA',
                'GG'
            ],
            'description'       => 'Type of the center.'
        ],
        'booking' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'created' => [
            'type'              => 'date',
            'description'       => 'Creation date of the booking.'
        ],
        'created_aamm' => [
            'type'              => 'string',
            'description'       => 'Index date of the creation date of the booking.'
        ],
        'aamm' => [
            'type'              => 'string',
            'description'       => 'Index date of the first day of the sojourn.'
        ],
        'year' => [
            'type'              => 'string',
            'description'       => 'Index date of the first day of the sojourn.'
        ],
        'nb_pers' => [
            'type'              => 'integer',
            'description'       => 'Number of hosted persons.'
        ],
        'nb_nights' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
        ],
        'nb_pers_nights' => [
            'type'              => 'integer',
            'description'       => 'Number of nights/accommodations.'
        ],
        'nb_room_nights' => [
            'type'              => 'integer',
            'description'       => 'Number of nights/accommodations.'
        ],
        'nb_rental_units' => [
            'type'              => 'integer',
            'description'       => 'Number of rental units (accommodations) involved in the sojourn.'
        ],
        'rate_class' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_name' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_lang' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_zip' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_country' => [
            'type'              => 'string',
            'usage'             => 'country/iso-3166:2',
            'description'       => 'Country.'
        ],
        'price_vate' => [
            'type'              => 'float',
            'description'       => 'Price of the sojourn VAT excluded.'
        ],
        'price_vati' => [
            'type'              => 'float',
            'description'       => 'Price of the sojourn VAT included.'
        ],
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

if (($params['center_id'] || $params['organisation_id'])){
    $domain = [
        ['date_to', '>=', $params['date_from']],
        ['date_to', '<=', $params['date_to']],
        ['state', 'in', ['instance','archive']],
        ['is_cancelled', '=', false],
        ['status', 'not in', ['quote','option']]
    ];

    if($params['center_id'] && $params['center_id'] > 0) {
        $domain[] = [ 'center_id', '=', $params['center_id'] ];
    }

    if($params['organisation_id'] && $params['organisation_id'] > 0) {
        $domain[] = [ 'organisation_id', '=', $params['organisation_id'] ];
    }
}

if($domain){
    $bookings = Booking::search($domain, ['sort'  => ['date_from' => 'asc']])
        ->read([
            'id',
            'created',
            'name',
            'date_from',
            'date_to',
            'total',
            'price',
            'center_id'                 => ['id', 'name', 'center_office_id'],
            'customer_id'               => ['rate_class_id' => ['name']],
            'customer_identity_id'      => [
                'id',
                'name',
                'lang_id' => ['id', 'name'],
                'address_zip',
                'address_country'
            ]
        ])
        ->get(true);
}

$result = [];

foreach($bookings as $booking) {
    // find all sojourns
    $sojourns = BookingLineGroup::search([
            ['booking_id', '=', $booking['id']],
            ['is_sojourn', '=', true]
        ])
        ->read([
            'id',
            'nb_pers',
            'nb_nights',
            'rental_unit_assignments_ids' => ['id', 'is_accomodation', 'qty']
        ])
        ->get(true);


    // nb_nights depends on booking
    $booking_nb_nights = round( ($booking['date_to'] - $booking['date_from']) / (3600*24) );
    // nb_rental_unit and nb_pers depend on sojourns
    $booking_nb_rental_units = 0;
    $booking_nb_pers = 0;

    $count_nb_pers_nights = 0;
    $count_nb_room_nights = 0;

    foreach($sojourns as $sojourn) {
        // retrieve all lines relating to an accommodation
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
                $product = Product::id($line['product_id'])->read(['product_model_id' => ['id', 'capacity']])->first(true);
                $capacity = $product['product_model_id']['capacity'];

                if($capacity < $sojourn['nb_pers']) {
                    // $line['qty'] should be nb_nights * ceil(nb_pers/capacity)
                    $sojourn_nb_pers_nights += $line['qty'] * $capacity;
                }
                else {
                    // $line['qty'] should be the number of nights
                    $sojourn_nb_pers_nights += $line['qty'] * $sojourn['nb_pers'];
                }
            }
        }

        // $sojourn_nb_pers_nights = array_reduce($lines, function($c, $a) { return $c + $a['qty'];}, 0);
        $sojourn_nb_accommodations = count(array_filter($sojourn['rental_unit_assignments_ids'], function($a) {return $a['is_accomodation'];}));

        $sojourn_nb_pers = (count($lines))?$sojourn['nb_pers']:0;
        $sojourn_nb_nights = (count($lines))?$sojourn['nb_nights']:0;

        $booking_nb_rental_units += $sojourn_nb_accommodations;
        $booking_nb_pers += $sojourn_nb_pers;

        $count_nb_pers_nights += $sojourn_nb_pers_nights;
        $count_nb_room_nights += $sojourn_nb_nights * $sojourn_nb_accommodations;
    }
    // #memo - one entry by booking
    $result[] = [
        'center'            => $booking['center_id']['name'],
        'center_type'       => ($booking['center_id']['center_office_id'] == 1)?'GG':'GA',
        'booking'           => $booking['name'],
        'created'           => $adapter->adapt($booking['created'], 'date', 'txt', 'php'),
        'created_aamm'      => date('Y-m', $booking['created']),
        'date_from'         => $adapter->adapt($booking['date_from'], 'date', 'txt', 'php'),
        'date_to'           => $adapter->adapt($booking['date_to'], 'date', 'txt', 'php'),
        'aamm'              => date('Y/m', $booking['date_from']),
        'year'              => date('Y', $booking['date_from']),
        'nb_pers'           => $booking_nb_pers,
        'nb_nights'         => $booking_nb_nights,
        'nb_rental_units'   => $booking_nb_rental_units,
        'nb_pers_nights'    => $count_nb_pers_nights,
        'nb_room_nights'    => $count_nb_room_nights,
        'rate_class'        => $booking['customer_id']['rate_class_id']['name'],
        'customer_name'     => $booking['customer_identity_id']['name'],
        'customer_lang'     => $booking['customer_identity_id']['lang_id']['name'],
        'customer_zip'      => $booking['customer_identity_id']['address_zip'],
        'customer_country'  => $booking['customer_identity_id']['address_country'],
        'price_vate'        => $booking['total'],
        'price_vati'        => $booking['price']
    ];
}

$context->httpResponse()
        ->header('X-Total-Count', count($bookings))
        ->body($result)
        ->send();
