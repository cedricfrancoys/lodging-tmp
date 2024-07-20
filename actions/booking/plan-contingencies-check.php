<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Schedule a check of the contingencies (if a change made has implications in the channelmanager).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking for which the check must be planned.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 */
list($context, $orm, $cron) = [$providers['context'], $providers['orm'], $providers['cron']];

/*
    Check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
*/

// read booking object
/** @var Booking */
$booking = Booking::id($params['id'])
    ->read(['date_from', 'date_to', 'consumptions_ids' => ['is_accomodation', 'rental_unit_id']])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$map_rental_units_ids = [];

foreach($booking['consumptions_ids'] as $consumption) {
    if($consumption['is_accomodation']) {
        $map_rental_units_ids[$consumption['rental_unit_id']] = true;
    }
}

if(count($map_rental_units_ids)) {
    $cron->schedule(
            "channelmanager.check-contingencies.{$params['id']}",
            time(),
            'lodging_booking_check-contingencies',
            [
                'date_from'         => date('c', $booking['date_from']),
                'date_to'           => date('c', $booking['date_to']),
                'rental_units_ids'  => array_keys($map_rental_units_ids)
            ]
        );
}

$context->httpResponse()
        ->status(204)
        ->send();
