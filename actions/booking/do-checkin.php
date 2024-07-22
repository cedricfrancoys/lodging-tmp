<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Booking;
use lodging\sale\booking\Consumption;

list($params, $providers) = announce([
    'description'   => "Sets booking as checked in.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking on which to perform the checkin.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'no_payment' =>  [
            'description'   => 'Do not check for payments and allow to checkin even if some payments are due.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'no_composition' =>  [
            'description'   => 'Do not check for composition completeness and allow to checkin without hosts personal details.',
            'type'          => 'boolean',
            'default'       => false
        ],
        'no_rental_unit_cleaned' =>  [
            'description'   => 'Do not check for rental units cleaned and allow to check in even if some rental unit have be cleaned.',
            'type'          => 'boolean',
            'default'       => false
        ],
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];


// read booking object
$booking = Booking::id($params['id'])
                  ->read(['id', 'name', 'status', 'composition_items_ids'])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}


/*
    Check booking consistency
*/

$errors = [];

// check accommodations assignments
$data = eQual::run('do', 'lodging_booking_check-units-assignments', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'invalid_accommodation_detected';
}

// check status of the contract
$data = eQual::run('do', 'lodging_booking_check-contract', ['id' => $params['id']]);
if(is_array($data) && count($data)) {
    $errors[] = 'unsigned_contract';
}

// check checkin time consistency and emit an alert if necessary (but do not block current transition)
eQual::run('do', 'lodging_booking_check-date-checkin', ['id' => $booking['id']]);

// check booking for due payments
if(!$params['no_payment']) {
    $data = eQual::run('do', 'lodging_booking_check-payments', ['id' => $params['id']]);

    if(is_array($data) && count($data)) {
        // raise an exception with remaining due amount detected (an alert should have been issued in the check controller)
        $errors[] = 'due_amount';
    }
}

// check booking for composition
if(!$params['no_composition']) {
    $data = eQual::run('do', 'lodging_booking_check-composition', ['id' => $params['id']]);

    if(is_array($data) && count($data)) {
        // raise an exception with incomplete composition detected (an alert should have been issued in the check controller)
        $errors[] = 'incomplete_composition';
    }
}

if(!$params['no_rental_unit_cleaned']) {
    $data =  eQual::run('do', 'lodging_booking_check-units-ready', ['id' => $booking['id']]);
    if(is_array($data) && count($data)) {
        $errors[] = 'rental_unit_not_cleaned';
    }
}
// raise an exception with first error (alerts should have been issued in the check controllers)
foreach($errors as $error) {
    throw new Exception($error, QN_ERROR_INVALID_PARAM);
}


/*
    Adapt status for rental_units targeted by consumptions
*/

$today = time();
$consumptions = Consumption::search([
                    ['booking_id','=', $params['id']],
                    // mark only accomodations...
                    ['is_accomodation', '=', true],
                    // ...impacted by current date
                    ['date', '<=', $today]
                ])
                ->read(['rental_unit_id'])
                ->get();

if($consumptions) {
    foreach($consumptions as $cid => $consumption) {
        if($consumption['type'] == 'book') {
            $orm->update('lodging\realestate\RentalUnit', $rental_unit_id, ['status' => 'busy_full']);
        }
        else if($consumption['type'] == 'link') {
            $orm->update('lodging\realestate\RentalUnit', $rental_unit_id, ['status' => 'busy_full']);
        }
        else if($consumption['type'] == 'part') {
            $orm->update('lodging\realestate\RentalUnit', $rental_unit_id, ['status' => 'busy_part']);
        }
    }
}

/*
    Update booking status
*/

Booking::id($params['id'])->update(['status' => 'checkedin']);


$context->httpResponse()
        ->status(204)
        ->send();