<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

list($params, $providers) = eQual::announce([
    'description'   => 'Lists all+historic contracts and their related details for a given period.',
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
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$result = array_merge(
    eQual::run('get', 'lodging_stats_stat-contracts-history', $params),
    eQual::run('get', 'lodging_stats_stat-contracts-discope', $params)
);

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
