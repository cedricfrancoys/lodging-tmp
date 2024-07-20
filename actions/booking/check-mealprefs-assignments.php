<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;

list($params, $providers) = eQual::announce([
    'description'   => "Checks consistency of meal preferences and restrictions (nb_pers) for a given booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the preferences must be checked.',
            'type'          => 'integer',
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

// ensure booking object exists and is readable
$booking = Booking::id($params['id'])
    ->read(['id', 'name', 'center_office_id', 'booking_lines_groups_ids' => ['is_sojourn', 'is_event', 'nb_pers', 'booking_lines_ids', 'meal_preferences_ids' => ['qty']]])
    ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$booking_line_groups = $booking['booking_lines_groups_ids'];
$mismatch = false;

if($booking_line_groups) {
    foreach($booking_line_groups as $gid => $group) {
        if(!$group['is_sojourn'] && !$group['is_event']) {
            continue;
        }

        $has_meals = false;
        $lines = BookingLine::ids($group['booking_lines_ids'])->read(['id', 'is_meal'])->get(true);
        if($lines) {
            foreach($lines as $lid => $line) {
                if($line['is_meal']) {
                    $has_meals = true;
                    break;
                }
            }
        }

        if(!$has_meals) {
            continue;
        }

        if(!$group['meal_preferences_ids'] || !count($group['meal_preferences_ids'])) {
            $mismatch = true;
            break;
        }

        $nb_pers = $group['nb_pers'];
        $assigned_count = 0;
        foreach($group['meal_preferences_ids'] as $aid => $assignment) {
            $assigned_count += $assignment['qty'];
        }
        if($nb_pers != $assigned_count) {
            $mismatch = true;
            break;
        }
    }
}


/*
    This controller is a check: an empty response means that no alert was raised
*/

$result = [];
$httpResponse = $context->httpResponse()->status(200);

// compare with the number of lines of compositions we got so far
if($mismatch) {
    $result[] = $params['id'];
    // by convention we dispatch an alert that relates to the controller itself.
    $dispatch->dispatch('lodging.booking.mealprefs_assignment', 'lodging\sale\booking\Booking', $params['id'], 'warning', 'lodging_booking_check-mealprefs-assignments', ['id' => $params['id']], [], null, $booking['center_office_id']);
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('lodging.booking.mealprefs_assignment', 'lodging\sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();