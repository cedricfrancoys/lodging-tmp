<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\sale\booking\BookingHistoryEntry;

list($params, $providers) = announce([
    'description'   => 'Import linearized historic data from Hestia to booking history entries.',
    'params'        => [
        'center' => [
            'type'      => 'string',
            'selection' => ['Eupe', 'GiGr', 'HanL', 'Louv', 'Ovif', 'Roch', 'Vill', 'Wann'],
            'required'  => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected',
        'groups'     => ['admins']
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

$getLinearizedBookings = function($center) {
    $linearized_file = fopen(
        QN_BASEDIR . '/packages/lodging/init/history/'.$center.'_linearized.csv',
        'r'
    );

    $headings = fgetcsv($linearized_file, null, ';');
    $headings[0] = trim($headings[0], "\xEF\xBB\xBF");
    $headings[0] = str_replace('"', '', $headings[0]);

    $bookings = [];
    while($row = fgetcsv($linearized_file, null, ';')) {
        $booking = [];
        foreach($headings as $index => $heading) {
            $booking[$heading] = $row[$index];
        }

        $bookings[] = $booking;
    }

    fclose($linearized_file);

    return $bookings;
};

$getCentersMap = function() {
    $centers = Center::search()
        ->read(['name', 'code_alpha', 'organisation_id'])
        ->get();

    $centers_map = [];
    foreach($centers as $center) {
        $centers_map[$center['code_alpha']] = $center;
    }

    return $centers_map;
};

$convertAmount = function($string_amount) {
    $string_amount = str_replace('.', '', $string_amount);
    return str_replace(',', '.', $string_amount);
};


$bookings = $getLinearizedBookings($params['center']);

$centers_map = $getCentersMap();

$create_entities_count = 0;
foreach($bookings as $booking) {
    try {
        BookingHistoryEntry::create([
            'historic_identifier'    => $booking['id'],
            'name'                   => $booking['name'],
            'date_create'            => strtotime($booking['created']),
            'date_from'              => strtotime($booking['date_from']),
            'date_to'                => strtotime($booking['date_to']),
            'total'                  => $convertAmount($booking['price_ex_vat']),
            'price'                  => $convertAmount($booking['price_inc_vat']),
            'organisation_id'        => $centers_map[$booking['center_alpha_code']]['organisation_id'],
            'center_id'              => $centers_map[$booking['center_alpha_code']]['id'],
            'center_type'            => $booking['center_type'],
            'nb_pers'                => $booking['nb_pers'],
            'nb_nights'              => $booking['nb_nights'],
            'nb_rental_units'        => $booking['nb_rental_units'],
            'nb_pers_nights'         => $booking['nb_pers_nights'],
            'nb_room_nights'         => $booking['nb_room_nights'],
            'customer_name'          => $booking['customer_name'],
            'customer_vat_rate'      => $booking['customer_vat_rate'],
            'customer_language_code' => $booking['customer_language_code'],
            'customer_street'        => $booking['customer_street'],
            'customer_zip'           => $booking['customer_zip'],
            'customer_city'          => $booking['customer_city'],
            'customer_country_code'  => $booking['customer_country_code'],
            'customer_rate_class_id' => $booking['customer_rate_class']
        ]);

        $create_entities_count++;
    } catch(Exception $e) {
        trigger_error('Historic booking '.$booking['id'].' not imported because invalid data.', QN_REPORT_WARNING);
    }
}

$context->httpResponse()
        ->body([
            'success'          => true,
            'handled_bookings' => count($bookings),
            'created_entities' => $create_entities_count
        ])
        ->send();
