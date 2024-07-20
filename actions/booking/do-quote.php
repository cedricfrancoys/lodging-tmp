<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\Contract;
use lodging\sale\booking\Consumption;

list($params, $providers) = eQual::announce([
    'description'   => "Reverts a booking to 'quote' status. Booking status of rental units is maintained unless there is an explicit request for releasing them.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'free_rental_units' =>  [
            'description'   => 'Flag for marking reserved rental units to be release immediately, if any.',
            'type'          => 'boolean',
            'default'       => false
        ]
    ],
    'access' => [
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
 * @var \equal\orm\ObjectManager            $om
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $om, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

// read booking object
$booking = Booking::id($params['id'])
    ->read(['id', 'name', 'status', 'is_locked', 'contracts_ids', 'booking_lines_ids', 'booking_lines_groups_ids', 'fundings_ids' => ['id', 'type', 'is_paid']])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] == 'quote') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if($booking['is_locked']) {
    throw new Exception("locked_contract", QN_ERROR_INVALID_PARAM);
}

/*
    Update booking status
    // #memo - this must be done first in order to allow updates on sub-objects (which might be prohibited on non-quote bookings)
*/
Booking::id($params['id'])->update(['status' => 'quote']);


/*
    Update alerts & cron jobs
*/

// remove messages about readiness for this booking, if any
$dispatch->cancel('lodging.booking.ready', 'lodging\sale\booking\Booking', $params['id']);

// remove existing CRON tasks for reverting the booking to quote
$cron->cancel("booking.option.deprecation.{$params['id']}");

// remove non-invoiced fundings, if any
$fundings_ids_to_remove = [];
foreach($booking['fundings_ids'] as $fid => $funding) {
    if($funding['type'] == 'invoice') {
        // once emitted, we cannot remove an invoice without creating a credit note
        continue;
    }
    if(!$funding['is_paid']) {
        $fundings_ids_to_remove[] = "-$fid";
    }
}

// mark contracts as expired
// #memo - generated contracts are kept for history (we never delete them)
Contract::ids($booking['contracts_ids'])->update(['status' => 'cancelled']);

// mark lines as not 'invoiced' (waiting for payment)
BookingLine::ids($booking['booking_lines_ids'])->update(['is_contractual' => false]);

// extra groups do not make sense for quotes (and cannot be updated before checkin)
BookingLineGroup::ids($booking['booking_lines_groups_ids'])->update(['is_extra' => false]);

// mark booking as non-having contract, remove non-paid fundings and remove existing consumptions
Booking::id($params['id'])->update(['has_contract' => false, 'fundings_ids' => $fundings_ids_to_remove]);

/*
    Reset computed fields
*/

// #memo - this does not reset `has_manual_*` fields
$om->callonce(BookingLine::getType(), '_resetPrices', $booking['booking_lines_ids']);
BookingLineGroup::ids($booking['booking_lines_groups_ids'])->update(['unit_price' => null, 'price' => null, 'vat_rate' => null, 'total' => null]);
Booking::id($params['id'])->update(['is_price_tbc' => false, 'price' => null, 'total' => null]);

// we also need to force re-assignment of the price_id of each line, since the applicable price list might have changed
// #memo - this will not reset values for fields marked with `has_manual_*`
$om->callonce(BookingLine::getType(), '_updatePriceId', $booking['booking_lines_ids']);


// in case rental units were freed, check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
if($params['free_rental_units']) {

    $booking = Booking::id($params['id'])
        ->read(['date_from', 'date_to', 'consumptions_ids' => ['is_accomodation', 'rental_unit_id']])
        ->first(true);

    $map_rental_units_ids = [];
    foreach($booking['consumptions_ids'] as $consumption) {
        if($consumption['is_accomodation']) {
            $map_rental_units_ids[$consumption['rental_unit_id']] = true;
        }
    }

    // remove consumptions if requested (link & part)
    Consumption::search(['booking_id', '=', $params['id']])->delete(true);

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
}


// remove pending alerts, if any
$dispatch->cancel('lodging.booking.contract.unsigned', 'lodging\sale\booking\Booking', $params['id']);
$dispatch->cancel('lodging.booking.date.checkin', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.rental_units_ready', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.payments', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.prices_assignment', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.composition', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.consistency', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.rental_units_assignment', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.overbooking', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.sojourns_accomodations', 'lodging\sale\booking\Booking', $booking['id']);

$context->httpResponse()
        ->status(204)
        ->send();
