<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Lang;
use lodging\sale\booking\BookingHistoryEntry;

list($params, $providers) = eQual::announce([
    'description'   => 'Lists all historic contracts (imported from Hestia data) and their related details for a given period.',
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
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'adapt']
]);

/**
 * @var \equal\php\Context      $context
 * @var \equal\data\DataAdapter $adapter
 */
list($context, $adapter) = [$providers['context'], $providers['adapt']];

$getPivotDate = function($organisation_id, $center_id) {
    $villers_organisation_id = 2;
    $organisation_pivot_date_map = [
        $villers_organisation_id => strtotime('2023-12-31 23:59:59'),
    ];

    $villers_center_id = 25;
    $center_pivot_date_map = [
        $villers_center_id => strtotime('2023-12-31 23:59:59'),
    ];

    $pivot_date = strtotime('2022-08-15 23:59:59');
    if(!isset($center_id) && isset($organisation_id, $organisation_pivot_date_map[$organisation_id])) {
        $pivot_date = $organisation_pivot_date_map[$organisation_id];
    }
    elseif(isset($center_id, $center_pivot_date_map[$center_id])) {
        $pivot_date = $center_pivot_date_map[$center_id];
    }

    return $pivot_date;
};

$organisation_id = $params['organisation_id'] ?? null;
$center_id = $params['center_id'] ?? null;

$domain = [];
if(isset($organisation_id) || isset($center_id)) {
    $pivot_date = $getPivotDate($organisation_id, $center_id);

    $domain = [
        ['date_to', '>=', $params['date_from']],
        ['date_to', '<=', $params['date_to']],
        ['date_to', '<=', $pivot_date]
    ];

    if(isset($organisation_id) && $organisation_id > 0) {
        $domain[] = ['organisation_id', '=', $organisation_id];
    }

    if(isset($center_id) && $center_id > 0) {
        $domain[] = ['center_id', '=', $center_id];
    }
}

$historic_bookings = [];
if(!empty($domain)) {
    $historic_bookings = BookingHistoryEntry::search($domain, ['sort'  => ['date_from' => 'asc']])
        ->read([
            'id',
            'date_create',
            'historic_identifier',
            'date_from',
            'date_to',
            'total',
            'price',
            'center_type',
            'customer_name',
            'customer_street',
            'customer_zip',
            'customer_city',
            'customer_country_code',
            'customer_language_code',
            'nb_pers',
            'nb_nights',
            'nb_rental_units',
            'nb_pers_nights',
            'nb_room_nights',
            'center_id'              => ['id', 'name', 'center_office_id'],
            'customer_rate_class_id' => ['id', 'name']
        ])
        ->get(true);
}

$langs = Lang::search()
    ->read(['name', 'code'])
    ->get();

$map_langs = [];
foreach($langs as $lang) {
    $map_langs[strtoupper($lang['code'])] = $lang['name'];
}

$result = [];
foreach($historic_bookings as $booking) {
    $result[] = [
        'center'            => $booking['center_id']['name'],
        'center_type'       => $booking['center_type'],
        'booking'           => $booking['historic_identifier'],
        'created'           => $adapter->adapt($booking['date_create'], 'date', 'txt', 'php'),
        'created_aamm'      => date('Y-m', $booking['date_create']),
        'date_from'         => $adapter->adapt($booking['date_from'], 'date', 'txt', 'php'),
        'date_to'           => $adapter->adapt($booking['date_to'], 'date', 'txt', 'php'),
        'aamm'              => date('Y/m', $booking['date_from']),
        'year'              => date('Y', $booking['date_from']),
        'nb_pers'           => $booking['nb_pers'],
        'nb_nights'         => $booking['nb_nights'],
        'nb_rental_units'   => $booking['nb_rental_units'],
        'nb_pers_nights'    => $booking['nb_pers_nights'],
        'nb_room_nights'    => $booking['nb_room_nights'],
        'rate_class'        => $booking['customer_rate_class_id']['name'],
        'customer_name'     => $booking['customer_name'],
        'customer_lang'     => $map_langs[strtoupper($booking['customer_language_code'])] ?? $booking['customer_language_code'],
        'customer_zip'      => $booking['customer_zip'],
        'customer_country'  => $booking['customer_country_code'],
        'price_vate'        => $booking['total'],
        'price_vati'        => $booking['price']
    ];
}

$context->httpResponse()
        ->header('X-Total-Count', count($historic_bookings))
        ->body($result)
        ->send();
