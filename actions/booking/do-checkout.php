<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Invoice;
use lodging\sale\booking\Booking;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;

list($params, $providers) = eQual::announce([
    'description'   => "Sets booking as checked out.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the composition has to be generated.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'public',		// 'public' (default) or 'private' (can be invoked by CLI only)
        'groups'            => ['booking.default.user'],// list of groups ids or names granted
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [$providers['context'], $providers['orm'], $providers['auth'],  $providers['dispatch']];

$booking = Booking::id($params['id'])->read(['id'])->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// a booking cannot switch back to "checkedout" if it has a non-cancelled balance invoice
$balance_invoice = Invoice::search([['booking_id', '=', $params['id']], ['is_deposit', '=', false], ['type', '=', 'invoice'], ['status', '=', 'invoice']])->read(['id'])->first(true);

if($balance_invoice) {
    throw new Exception("emitted_balance_invoice", QN_ERROR_INVALID_PARAM);
}

// mark booking as checked-out
Booking::id($params['id'])->update(['status' => 'checkedout']);

// mark involved rental_units as ready (no more customer occupying)
// retrieve accommodations assigned to the booking
$assignments_ids = SojournProductModelRentalUnitAssignement::search([
        ['booking_id', '=', $params['id']],
        ['is_accomodation', '=', true]
    ])
    ->update(['status' => 'empty', 'action_required' => 'cleanup_full']);

// #memo - now user can complete the booking with additional services, if any

// remove pending alerts, if any
$dispatch->cancel('lodging.booking.composition', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.consistency', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.rental_units_assignment', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.overbooking', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.sojourns_accomodations', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.date.checkin', 'lodging\sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.rental_units_ready', 'lodging\sale\booking\Booking', $booking['id']);

$context->httpResponse()
        ->status(204)
        ->send();