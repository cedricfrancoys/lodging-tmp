<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\Consumption;

list($params, $providers) = announce([
    'description'   => "Checks the consistency of a booking, according to its status and booked services that must match consumptions.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the consistency must be checked.',
            'type'          => 'integer',
            'required'      => true
        ]
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
    ->read([
            'id', 'name', 'status', 'center_office_id',
            'booking_lines_groups_ids' => [
                'id',
                'group_type',
                'has_pack',
                'pack_id',
                'booking_lines_ids' => [
                    'id',
                    'is_accomodation',
                    'product_model_id'
                ]
            ],
            'sojourn_product_models_ids' => [
                'id',
                'is_accomodation',
                'rental_unit_assignments_ids'
            ]
        ])
    ->first(true);

/*
    This controller is a check: an empty response means that no alert was raised
*/

$result = [];
$httpResponse = $context->httpResponse()->status(200);


if($booking && in_array($booking['status'], ['option', 'confirmed'])) {
    $has_error = false;

    foreach($booking['booking_lines_groups_ids'] as $group) {
        // #todo 2024-02-21 - quick workaround for allowing camps without rental units
        if($group['group_type'] == 'camp') {
            continue;
        }
        // #memo - there is an exception for (temporary) pack [KA-SejSco0-A - 1766] (gratuité professeurs) - no accommodation needed
        if($group['has_pack'] && $group['pack_id'] == 1766) {
            continue;
        }

        // quick (incomplete) check on consumptions
        foreach($group['booking_lines_ids'] as $line) {
            if($line['is_accomodation']) {
                $consumptions = Consumption::search([['booking_id', '=', $params['id']], ['product_model_id', '=', $line['product_model_id']]])->get(true);
                if(count($consumptions) == 0) {
                    $has_error = true;
                    break;
                }
            }
        }
    }

    // check that no 'accommodation' SPM is empty
    foreach($booking['sojourn_product_models_ids'] as $spm) {
        if($spm['is_accomodation'] && count($spm['rental_unit_assignments_ids']) <= 0) {
            $has_error = true;
            break;
        }
    }

    if($has_error) {
        $result[] = $params['id'];
        // raise a warning
        $dispatch->dispatch('lodging.booking.consistency', 'lodging\sale\booking\Booking', $params['id'], 'important', 'lodging_booking_check-consistency', ['id' => $params['id']], [], null, $booking['center_office_id']);
    }
    else {
        // symmetrical removal of the alert (if any)
        $dispatch->cancel('lodging.booking.consistency', 'lodging\sale\booking\Booking', $params['id']);
    }
}


$httpResponse->body($result)
             ->send();
