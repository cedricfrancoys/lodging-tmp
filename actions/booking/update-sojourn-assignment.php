<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Consumption;
use lodging\realestate\RentalUnit;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\SojournProductModel;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;
use lodging\sale\catalog\ProductModel;

// announce script and fetch parameters values
list($params, $providers) = announce([
    'description'	=>	"Update the assignments of a given sojourn for a specific product model.",
    'params' 		=>	[
        'product_model_id' =>  [
            'type'              => 'many2one',
            'description'       => 'Identifier of the Product Model the assignments relate to.',
            'foreign_object'    => 'lodging\sale\catalog\ProductModel',
            'required'          => true
        ],
        'booking_line_group_id' =>  [
            'type'              => 'many2one',
            'description'       => 'Identifier of the sojourn (services group) the assignments relate to.',
            'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
            'required'          => true
        ],
        'assignments' => [
            'type'              => 'array',
            'description'       => 'List of assignments with rental unit id and quantity (rental_unit_id; qty).',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'orm', 'cron']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\cron\Scheduler       $cron
 */
list($context, $orm, $cron) = [ $providers['context'], $providers['orm'], $providers['cron'] ];

// adapt received assignments (workaround for wrong type assignment from front-end)
foreach($params['assignments'] as $assignment) {
    $assignment['qty'] = intval($assignment['qty']);
}

// fetch the group (nb_pers)
$group = BookingLineGroup::id($params['booking_line_group_id'])
    ->read([
            'id', 'nb_pers', 'nb_nights', 'date_from', 'date_to', 'time_from', 'time_to',
            'booking_id' => ['id', 'center_id']
        ])
    ->first(true);

if(!$group) {
    throw new Exception('unknown_sojourn', QN_ERROR_UNKNOWN_OBJECT);
}

// fetch the product_model
$product_model = ProductModel::id($params['product_model_id'])->read(['id', 'is_accomodation', 'capacity'])->first(true);

if(!$product_model) {
    throw new Exception('unknown_product_model', QN_ERROR_UNKNOWN_OBJECT);
}

$total_qty = 0;
foreach($params['assignments'] as $assignment) {
    $total_qty += $assignment['qty'];
    $rental_unit = RentalUnit::id($assignment['rental_unit_id'])->read(['is_accomodation', 'capacity'])->first(true);
    if(!$rental_unit) {
        throw new Exception('unknown_rental_unit', QN_ERROR_UNKNOWN_OBJECT);
    }
    /*
    // #memo - assigning more capacity than the actual number of persons is allowed
    if($group['nb_pers'] < $assignment['qty']) {
        throw new Exception('quantity_exceeds_group', QN_ERROR_INVALID_PARAM);
    }
    */
    if($rental_unit['capacity'] < $assignment['qty']) {
        throw new Exception('quantity_exceed_accommodation', QN_ERROR_INVALID_PARAM);
    }
    /*
    if($product_model['capacity'] < $assignment['qty']) {
        // overflow error
    }
    */
}

if($group['nb_pers'] > $total_qty) {
    // incomplete assignment: reject request
    throw new Exception('group_assignment_too_low', QN_ERROR_INVALID_PARAM);
}

// compute timestamps for the sojourn date range
$date_from = $group['date_from'] + $group['time_from'];
$date_to = $group['date_to'] + $group['time_to'];

// retrieve existing consumptions for sojourn date range
$existing_consumptions_map = Consumption::getExistingConsumptions($orm, [$group['booking_id']['center_id']], $date_from, $date_to);

// check assignments consistency : there is no consumption from other bookings that overlap with updated assignments (based on group details)
foreach($params['assignments'] as $assignment) {
    $target_rental_unit_id = $assignment['rental_unit_id'];
    foreach($existing_consumptions_map as $rental_unit_id => $dates) {
        if($rental_unit_id != $target_rental_unit_id) {
            continue;
        }
        foreach($dates as $date_index => $consumptions) {
            foreach($consumptions as $consumption) {
                // ignore consumptions from same booking
                if($consumption['booking_id'] == $group['booking_id']['id']) {
                    continue;
                }
                $consumption_from = $consumption['date_from'] + $consumption['schedule_from'];
                $consumption_to = $consumption['date_to'] + $consumption['schedule_to'];
                // #memo - we don't allow instant transition (i.e. checkin time of a booking equals checkout time of a previous booking)
                if(max($date_from, $consumption_from) < min($date_to, $consumption_to)) {
                    throw new Exception("booked_rental_unit: {$rental_unit_id} in booking {$consumption['booking_id']}", QN_ERROR_CONFLICT_OBJECT);
                }
            }
        }
    }
}

// everything went well (all checks passed): proceed

// retrieve SPM
$spm = SojournProductModel::search([ ['booking_line_group_id', '=',  $group['id']], ['product_model_id', '=', $product_model['id']] ] )->read(['id', 'is_accomodation'])->first(true);
if(!$spm) {
    throw new Exception('missing_spm', QN_ERROR_UNKNOWN_OBJECT);
}

// retrieve original consumptions
$original_consumptions_ids = Consumption::search([ ['product_model_id', '=', $params['product_model_id']], ['booking_line_group_id', '=', $params['booking_line_group_id']] ])->ids();

// remove old assignments
$spm_assignments_ids = SojournProductModelRentalUnitAssignement::search([ ['booking_line_group_id', '=', $group['id']], ['sojourn_product_model_id', '=', $spm['id']] ])->ids();
SojournProductModelRentalUnitAssignement::ids($spm_assignments_ids)->delete(true);

// create new assignments
foreach($params['assignments'] as $assignment) {
    SojournProductModelRentalUnitAssignement::create([
            'booking_id'                => $group['booking_id']['id'],
            'booking_line_group_id'     => $group['id'],
            'sojourn_product_model_id'  => $spm['id'],
            'qty'                       => $assignment['qty'],
            'rental_unit_id'            => $assignment['rental_unit_id'],
            'is_accomodation'           => $spm['is_accomodation']
        ]);
}

// retrieve new resulting consumptions
$consumptions = BookingLineGroup::getResultingConsumptions($orm, $group['id'], [], 'en');

$new_consumptions_ids = [];
try {
    // create new consumptions objects
    foreach($consumptions as $consumption) {
        // discard consumptions not relating to provided product model
        if($consumption['product_model_id'] != $product_model['id']) {
            continue;
        }
        $new_consumption = Consumption::create($consumption)->read(['id'])->first(true);
        $new_consumptions_ids[] = $new_consumption['id'];
    }
}
catch(Exception $e) {
    // something went wrong : abort
    Consumption::ids($new_consumptions_ids)->delete(true);
    throw new Exception('unexpected: '.$e->getMessage(), QN_ERROR_UNKNOWN);
}


/*
    Check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
*/

$map_rental_units_ids = [];

// availability might be impacted by original rental units...
$consumptions = Consumption::ids($original_consumptions_ids)->read(['rental_unit_id'])->get(true);
foreach($consumptions as $consumption) {
    $map_rental_units_ids[$consumption['rental_unit_id']] = true;
}
// ... or by new assignments
foreach($params['assignments'] as $assignment) {
    $map_rental_units_ids[$assignment['rental_unit_id']] = true;
}

if(count($map_rental_units_ids)) {
    $cron->schedule(
            "channelmanager.check-contingencies.{$group['booking_id']['id']}",
            time(),
            'lodging_booking_check-contingencies',
            [
                'date_from'         => date('c', $group['date_from']),
                'date_to'           => date('c', $group['date_to']),
                'rental_units_ids'  => array_keys($map_rental_units_ids)
            ]
        );
}

// if everything went well, remove old consumptions
Consumption::ids($original_consumptions_ids)->delete(true);

$context->httpResponse()
        ->send();
