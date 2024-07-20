<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Composition;
use lodging\sale\booking\CompositionItem;
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Generate the composition (hosts listing) for a given booking. If a composition already exists, it is reset.",
    'params'        => [
        'booking_id' =>  [
            'description'   => 'Identifier of the booking for which the composition has to be generated.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'data' => [
            'description'   => 'Raw data to be used for filling in the hosts details.',
            'type'          => 'array'
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
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$user_id = $auth->userId();


// read groups and nb_pers from the targeted booking object, and subsequent lines (make sure user has access to it)
$booking = Booking::id($params['booking_id'])
    ->read([
        'customer_identity_id' => ['type', 'firstname', 'lastname', 'gender', 'date_of_birth', 'email', 'phone', 'address_street', 'address_city', 'address_country'],
        'booking_lines_groups_ids' => [
            'nb_pers',
            'rental_unit_assignments_ids' => [
                'qty',
                'rental_unit_id' => ['id', 'capacity', 'has_children', 'children_ids']
            ]
        ]
    ])
    ->first(true);

if(!$booking) {
    throw new Exception('unknown_booking', QN_ERROR_INVALID_PARAM);
}


$auth->su();
    // look for an existing composition (this can only be done while booking is not finished)
    $composition = Composition::search(['booking_id', '=', $booking['id']])->read(['id'])->first(true);
    if(!$composition) {
        // create a new composition attached to current booking
        $composition = Composition::create(['booking_id' => $booking['id']])->read(['id'])->first(true);
        $composition_id = $composition['id'];
        // update booking accordingly (o2o relation)
        Booking::id($booking['id'])->update(['composition_id' => $composition_id]);
    }
    else {
        // if composition has already been generated, remove related composition items (we allow to regenerate a composition at any time)
        $composition_id = $composition['id'];
        CompositionItem::search(['composition_id', '=', $composition_id])->delete(true);
    }
$auth->su($user_id);


foreach($booking['booking_lines_groups_ids'] as $group) {
    $nb_pers = $group['nb_pers'];

    // #memo - we dont limit the assignment (a total capacity bigger than the number of persons is accepted, even if inconsistent)
    $remainder = $nb_pers;

    /*
        pass-1 : list all involved rental units on involved booking_lines.
        If a rental unit has children, we only add the children (not the UL itself)
    */

    $map_extra_rental_units = [];
    foreach($group['rental_unit_assignments_ids'] as $assignment) {
        $rental_unit = $assignment['rental_unit_id'];
        if($rental_unit) {
            if($rental_unit['has_children'] && $rental_unit['capacity'] > 10) {
                foreach($rental_unit['children_ids'] as $child_id) {
                    $map_extra_rental_units[$child_id] = true;
                }
            }
        }
    }


    /*
        pass-2 : assign qty to rental units
    */

    // to be used is data was received
    $item_index = 0;
    $is_first = true;

    foreach($group['rental_unit_assignments_ids'] as $assignment) {

        $unit = $assignment['rental_unit_id'];
        $qty = min($assignment['qty'], $nb_pers);
        $remainder -= $qty;

        for($i = 0; $i < $qty; ++$i) {

            $item = [
                'composition_id' => $composition_id,
                'rental_unit_id' => $unit['id']
            ];

            if(isset($params['data']) && isset($params['data'][$item_index])) {
                $item = array_merge($item, $params['data'][$item_index]);
                ++$item_index;
            }

            if($is_first) {
                // if customer is an individual, use its details for first entry
                if($booking['customer_identity_id']['type'] == 'I') {
                    $item['firstname'] = $booking['customer_identity_id']['firstname'];
                    $item['lastname'] = $booking['customer_identity_id']['lastname'];
                    $item['gender'] = $booking['customer_identity_id']['gender'];
                    $item['date_of_birth'] = $booking['customer_identity_id']['date_of_birth'];
                    $item['email'] = $booking['customer_identity_id']['email'];
                    $item['phone'] = $booking['customer_identity_id']['phone'];
                    $item['address'] = $booking['customer_identity_id']['address_street'].' '.$booking['customer_identity_id']['address_city'];
                    $item['country'] = $booking['customer_identity_id']['address_country'];
                }
                $is_first = false;
            }
            CompositionItem::create($item);
        }
    }
}


$context->httpResponse()
        ->status(204)
        ->send();