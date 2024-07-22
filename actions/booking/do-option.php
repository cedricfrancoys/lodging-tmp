<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use core\setting\Setting;

list($params, $providers) = eQual::announce([
    'description'   => "Update the status of given booking to 'option'. Related consumptions are added to the planning. Auto-deprecation of the option is scheduled according to setting `sale.booking.option.validity`.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'no_expiry' =>  [
            'description'   => 'The option will remain active without time limit.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'free_rental_units' =>  [
            'description'   => 'At expiration of the option, automatically release reserved rental units, if any.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'days_expiry' =>  [
            'description'   => 'The number of days for the option to expire.',
            'type'          => 'integer',
            'default'       =>  Setting::get_value('sale', 'booking', 'option.validity', 10)
        ],
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
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];


/*
    Check if rental_units assigned to the booking are still available at given dates (otherwise, generate an error).

    We perform a 2-pass operation:
        1. First, we create the consumptions
        2. Second, we try to detect an overbooking for the current booking (based on booking_id)
*/

// read booking object
/** @var Booking */
$booking = Booking::id($params['id'])
    ->read([
        'status',
        'is_price_tbc',
        'date_expiry',
        'consumptions_ids' => ['is_accomodation', 'rental_unit_id'],
        'type_id' => ['id', 'days_expiry_option']
    ])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'quote') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

$days_expiry = $params['days_expiry'];
// Check if the expiration days of the reservation type are greater than the days entered in the option to obtain the days until the reservation date in the option
if(isset($booking['type_id']['days_expiry_option']) && $booking['type_id']['days_expiry_option'] > $params['days_expiry']) {
    $days_expiry = $booking['type_id']['days_expiry_option'];
}
/*
    Check booking consistency
*/

$errors = [];

// check customer's previous bookings for remaining unpaid amount
eQual::run('do', 'lodging_booking_check-customer-debtor', ['id' => $params['id']]);

// check age ranges assignments
$data = eQual::run('do', 'lodging_booking_check-ages-assignments', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'invalid_booking';
}

// check age ranges assignments
$data = eQual::run('do', 'lodging_booking_check-mealprefs-assignments', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'invalid_booking';
}

// check rental units assignments
$data = eQual::run('do', 'lodging_booking_check-units-assignments', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'invalid_booking';
}

// check list of services
$data = eQual::run('do', 'lodging_booking_check-empty', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'empty_booking';
}

// check overbooking
$data = eQual::run('do', 'lodging_booking_check-overbooking', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'overbooking_detected';
}

// check sojourns accommodations
$data = eQual::run('do', 'lodging_booking_check-sojourns-accomodations', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'invalid_accommodation_detected';
}

// raise an exception with first error (alerts should have been issued in the check controllers)
foreach($errors as $error) {
    throw new Exception($error, QN_ERROR_INVALID_PARAM);
}


/*
    Create the consumptions in order to see them in the planning (scheduled services) and to mark related rental units as booked.
    If consumptions already exist, they're removed before hand.
*/

// remember rental units impacted by this operation (before and after)
$map_rental_units_ids = [];

foreach($booking['consumptions_ids'] as $consumption) {
    if($consumption['is_accomodation']) {
        $map_rental_units_ids[$consumption['rental_unit_id']] = true;
    }
}

// re-create consumptions (if any, previous consumptions from option-quote reverting, will be removed)
$orm->call(Booking::getType(), 'createConsumptions', $params['id']);

/*
    Update alerts & cron jobs
*/

// discard any pending alert related to rental units blocking
$dispatch->cancel('lodging.booking.quote.blocking', 'lodging\sale\booking\Booking', $params['id']);
// make sure there is no pending scheduled task
$cron->cancel("booking.option.deprecation.{$params['id']}");

if($params['no_expiry'] || $booking['is_price_tbc']) {
    // set booking as never expiring
    Booking::id($params['id'])->update(['is_noexpiry' => true]);
}
else {
    // memo - no_expiry could have been set previously (returned from option to quote, with an original flag set to true): in that situation we must consider the 'false' value
    Booking::id($params['id'])
        ->update([
            'is_noexpiry' => false,
            'date_expiry' => strtotime(' +' . $days_expiry . ' days')
        ]);

    // setup a scheduled job to set back the booking to a quote according to delay set by Setting `option.validity`
    $cron->schedule(
        "booking.option.deprecation.{$params['id']}",             // assign a reproducible unique name
        time() + $days_expiry  * 86400,                 // remind after {sale.booking.option.validity} days (default 10 days), or manually given value
        'lodging_booking_update-booking',
        [ 'id' => $params['id'], 'free_rental_units' => $params['free_rental_units'] ]
    );
}

/*
    Update booking status
*/
Booking::id($params['id'])->update(['status' => 'option']);


/*
    Perform additional checks to ensure Booking is in a consistent state (if not, an alert will be raised)
*/

// check booking rental units assignment
eQual::run('do', 'lodging_booking_check-consistency', ['id' => $booking['id']]);


/*
    Check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
*/

// #memo - through map_rental_units_ids, we consider consumptions BEFORE the booking being set as option (in case of a booking reverted to quote) AND consumptions created AFTER
$booking = Booking::id($params['id'])
    ->read(['date_from', 'date_to', 'consumptions_ids' => ['is_accomodation', 'rental_unit_id']])
    ->first(true);

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
