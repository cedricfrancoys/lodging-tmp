<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;


list($params, $providers) = announce([
    'description'   => "Checks consistency of hosts composition (age ranges) for a given booking.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the composition must be checked.',
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
$booking = Booking::id($params['id'])->read(['id', 'name', 'center_office_id', 'booking_lines_groups_ids' => ['nb_pers', 'age_range_assignments_ids' => ['age_range_id', 'qty']]])->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$booking_line_groups = $booking['booking_lines_groups_ids'];
$mismatch = false;

if($booking_line_groups) {
    foreach($booking_line_groups as $gid => $group) {
        if(!$group['age_range_assignments_ids'] || !count($group['age_range_assignments_ids'])) {
            continue;
        }
        $nb_pers = $group['nb_pers'];
        $assigned_count = 0;
        foreach($group['age_range_assignments_ids'] as $aid => $assignment) {
            if($assignment['age_range_id'] <= 0) {
                $mismatch = true;
                break 2;
            }
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
    $dispatch->dispatch('lodging.booking.ages_assignment', 'lodging\sale\booking\Booking', $params['id'], 'important', 'lodging_booking_check-ages-assignments', ['id' => $params['id']],[],null,$booking['center_office_id']);
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
}
else {
    // symetrical removal of the alert (if any)
    $dispatch->cancel('lodging.booking.ages_assignment', 'lodging\sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();
