<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;
use lodging\sale\booking\Consumption;
use lodging\sale\booking\Booking;
use lodging\realestate\RentalUnit;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\SojournProductModel;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;
use lodging\sale\catalog\ProductModel;

list($params, $providers) = eQual::announce([
    'description'   => "Retrieve the list of available rental units for a given sojourn, during a specific time range.",
    'params'        => [
        'booking_line_group_id' =>  [
            'description'   => 'Specific sojourn for which is made the request.',
            'type'          => 'integer',
            'required'      => true
        ],
        'product_model_id' =>  [
            'description'   => 'Specific product model for which a matching rental unit list is requested.',
            'type'          => 'integer',
            'required'      => true
        ],
        'domain' =>  [
            'description'   => 'Domain for additional filtering.',
            'type'          => 'array',
            'default'       => []
        ],
    ],
    'access' => [
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);


list($context, $orm) = [$providers['context'], $providers['orm']];

$result = [];

// retrieve product model
$product_model = ProductModel::id($params['product_model_id'])->read(['is_accomodation', 'rental_unit_assignement', 'rental_unit_id', 'rental_unit_category_id'])->first(true);

if(!$product_model) {
    throw new Exception('unknown_product_model', EQ_ERROR_UNKNOWN_OBJECT);
}

// retrieve sojourn data
$sojourn = BookingLineGroup::id($params['booking_line_group_id'])
    ->read([
        'booking_id' => [
            'id',
            'center_id'
        ],
        'date_from',
        'date_to',
        'time_from',
        'time_to'
    ])
    ->first(true);

if($sojourn) {
    $date_from = $sojourn['date_from'] + $sojourn['time_from'];
    $date_to = $sojourn['date_to'] + $sojourn['time_to'];

    $booking_assigned_rental_units_ids = [];
    $booking_unassigned_rental_units_ids = [];

    // look for existing SPMs defined within the targeted group, and:
    // * exclude targeted rental-units from the same SPM
    // * exclude fully assigned rental-units from other SPM
    $spms = SojournProductModel::search([
            ['booking_line_group_id', '=', $params['booking_line_group_id']]
        ])
        ->read(['product_model_id', 'rental_unit_assignments_ids' => ['qty', 'rental_unit_id' => ['id', 'capacity']]])
        ->get();

    foreach($spms as $spm) {
        if($spm['rental_unit_assignments_ids'] > 0 && count($spm['rental_unit_assignments_ids'])) {
            // same SPM : do not show the rental units as assignment possibility
            if($spm['product_model_id'] == $params['product_model_id']) {
                foreach($spm['rental_unit_assignments_ids'] as $assignment) {
                    if(isset($assignment['rental_unit_id']) && $assignment['rental_unit_id']['id'] > 0) {
                        $booking_assigned_rental_units_ids[] = $assignment['rental_unit_id']['id'];
                    }
                }
            }
            else {
                // #memo - rental units can be assigned to distinct product models
                foreach($spm['rental_unit_assignments_ids'] as $assignment) {
                    if(isset($assignment['rental_unit_id']) && $assignment['rental_unit_id']['capacity'] <= $assignment['qty']) {
                        $booking_assigned_rental_units_ids[] = $assignment['rental_unit_id']['id'];
                    }
                }
            }
        }
        elseif($spm['product_model_id'] == $params['product_model_id']) {
            // #memo - special case where consumptions have been created (option) and user switched back to 'quote' without releasing the rental units (i.e. removing the consumptions)
            // SPM is empty (no assignment yet) but some consumption are already be present : force adding related rental_units
            $consumptions = Consumption::search([
                    ['is_rental_unit', '=', true],
                    ['type', '=', 'book'],
                    ['booking_line_group_id', '=', $params['booking_line_group_id']]
                ])
                ->read(['rental_unit_id', 'product_model_id'])
                ->get();
            foreach($consumptions as $cid => $consumption) {
                if($consumption['product_model_id'] == $spm['product_model_id']) {
                    $booking_unassigned_rental_units_ids[] = $consumption['rental_unit_id'];
                }
                else {
                    // retrieve children rental_unit
                    // #memo - since there is no link between rental units and product models, we add all children
                    $c_rental_units_ids = RentalUnit::search(['parent_id', '=', $consumption['rental_unit_id']])->ids();
                    $booking_unassigned_rental_units_ids = array_merge($booking_unassigned_rental_units_ids, $c_rental_units_ids);
                }
            }
        }
    }

    $map_booking_partially_assigned_rental_units = [];

    // retrieve rental units that are already assigned by other groups within same time range, if any (independently from consumptions)
    // (we need to withdraw those from available units)
    $booking = Booking::id($sojourn['booking_id']['id'])->read(['booking_lines_groups_ids', 'rental_unit_assignments_ids'])->first(true);
    if($booking) {
        $groups = BookingLineGroup::ids($booking['booking_lines_groups_ids'])->read(['id', 'date_from', 'date_to', 'time_from', 'time_to'])->get();
        $assignments = SojournProductModelRentalUnitAssignement::ids($booking['rental_unit_assignments_ids'])->read(['qty', 'rental_unit_id' => ['id', 'capacity'], 'booking_line_group_id'])->get();
        foreach($assignments as $oid => $assignment) {
            // process rental units from groups having a time range intersection
            $group_id = $assignment['booking_line_group_id'];
            $group_date_from = $groups[$group_id]['date_from'] + $groups[$group_id]['time_from'];
            $group_date_to = $groups[$group_id]['date_to'] + $groups[$group_id]['time_to'];
            if($assignment['booking_line_group_id'] == $params['booking_line_group_id'] || max($date_from, $group_date_from) < min($date_to, $group_date_to)) {
                if($assignment['qty'] >= $assignment['rental_unit_id']['capacity'] ) {
                    $booking_assigned_rental_units_ids[] = $assignment['rental_unit_id']['id'];
                }
                else {
                    if(isset($map_booking_partially_assigned_rental_units[$assignment['rental_unit_id']['id']])) {
                        $map_booking_partially_assigned_rental_units[$assignment['rental_unit_id']['id']]['qty'] += $assignment['qty'];
                    }
                    else {
                        $map_booking_partially_assigned_rental_units[$assignment['rental_unit_id']['id']] = [
                                'qty'       => $assignment['qty'],
                                'capacity'  => $assignment['rental_unit_id']['capacity']
                            ];
                    }
                }
            }
        }
    }

    /* remove parent and children units of assigned rental units */

    // #memo - we need to do this in 2 steps : first, remove all ancestors, second, remove all descendants
    $map_rental_units_ids_to_remove = [];

	$children_ids = $booking_assigned_rental_units_ids;
    for($i = 0; $i < 2; ++$i) {
        $units = RentalUnit::ids($children_ids)->read(['parent_id', 'can_partial_rent'])->get();
        if($units > 0) {
            foreach($units as $uid => $unit) {
                if($unit['parent_id'] > 0) {
					$map_rental_units_ids_to_remove[(int)$unit['parent_id']] = true;
                }
            }
        }
    }

	$parents_ids = $booking_assigned_rental_units_ids;
	for($i = 0; $i < 2; ++$i) {
        $units = RentalUnit::ids($parents_ids)->read(['children_ids'])->get();
        if($units > 0) {
            foreach($units as $uid => $unit) {
                if(count($unit['children_ids'])) {
                    foreach($unit['children_ids'] as $uid) {
						$map_rental_units_ids_to_remove[(int)$uid] = true;
                    }
                }
            }
        }
    }

	$booking_assigned_rental_units_ids = array_merge($booking_assigned_rental_units_ids, array_keys($map_rental_units_ids_to_remove));

    // retrieve available rental units based on schedule and product_id
    $rental_units_ids = Consumption::getAvailableRentalUnits($orm, $sojourn['booking_id']['center_id'], $params['product_model_id'], $date_from, $date_to);

    // #memo - we don't remove units already assigned in same group in other SPM, since the allocation of an accommodation might be split on several age ranges (ex: room for 5 pers. with 2 adults and 3 children)

    // #todo - we should remove units already assigned in same group and same SPM (we cannot since this controller is called for a whole sojourn on a specific Product Model - we don't have a specific SPM here)

    // remove rental units from other groups of same booking, having an intersection on the dates range, and fully assigned (qty = capacity)
    $rental_units_ids = array_diff($rental_units_ids, $booking_assigned_rental_units_ids);

    // add rental units in consumption but not in SPMA
    $rental_units_ids = array_merge($rental_units_ids, $booking_unassigned_rental_units_ids);

    // (temporarily) add rental units from SPMA of same booking : will be filtered out afterward
    $rental_units_ids = array_merge($rental_units_ids, array_keys($map_booking_partially_assigned_rental_units));


    // #memo - we cannot append rental units from own booking consumptions :
    // It was first implemented to cover the use case: "come and go between 'draft' and 'option'", where units are already attached to consumptions
    // but this leads to an edge case: quote -> option -> quote (without releasing the consumptions)
    // 1) update nb_pers or time_from (list is not accurate and might return units that are not free)
    // 2) if another booking has booked the units in the meanwhile
    // In order to resolve that situation, user has to manually release the rental units (through action release-rentalunits.php)

    $rental_units = RentalUnit::ids($rental_units_ids)
        ->read(['id', 'name', 'capacity', 'order', 'is_accomodation', 'can_rent', 'rental_unit_category_id'])
        ->adapt('json')
        ->get(true);

    $domain = new Domain($params['domain']);

    /* filter results */

    foreach($rental_units as $index => $rental_unit) {
        // discard rental units that cannot be rent
        if(!$rental_unit['can_rent']) {
            continue;
        }
        // discard rental units that do not match the product model type (accommodation)
        if($rental_unit['is_accomodation'] != $product_model['is_accomodation']) {
            continue;
        }
        // discard rental units that do not belong to the rental unit category of the product model, if specified
        if($product_model['rental_unit_assignement'] == 'category' && $product_model['rental_unit_category_id'] != $rental_unit['rental_unit_category_id']) {
            continue;
        }
        // discard rental units that do not match the rental unit of the product model, if specified
        if($product_model['rental_unit_assignement'] == 'unit' && $product_model['rental_unit_id'] != $rental_unit['id']) {
            continue;
        }
        if($domain->evaluate($rental_unit)) {
            // adapt capacity to remaining capacity according to existing assignments from overlapping groups
            if(isset($map_booking_partially_assigned_rental_units[$rental_unit['id']])) {
                $rental_unit['capacity'] -= $map_booking_partially_assigned_rental_units[$rental_unit['id']]['qty'];
                if($rental_unit['capacity'] <= 0) {
                    continue;
                }
            }
            $result[] = $rental_unit;
        }
    }
    usort($result, function($a, $b) {return $a['order'] > $b['order'];});
}

$context->httpResponse()
        ->body($result)
        ->send();
