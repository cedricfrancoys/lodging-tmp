<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use lodging\realestate\RentalUnit;
use lodging\sale\booking\channelmanager\RoomType;
use lodging\sale\booking\Consumption;

list($params, $providers) = eQual::announce([
    'description'   => "Checks if changes made to one or more rental units impacts the availability of a Room Type. If a change is detected, update availability set at the channelmanager side.",
    'params'        => [
        'date_from' => [
            'type'              => 'date',
            'description'       => "Day of arrival.",
            'required'          => true
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Day of departure (date not included in the update).",
            'required'          => true
        ],
        'rental_units_ids' => [
            'type'              => 'array',
            'description'       => 'List of rental units identifiers that must be checked.'
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'constants'     => ['ROOT_APP_URL', 'EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'dispatch', 'cron']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\dispatch\Dispatcher          $dispatch
 * @var \equal\cron\Scheduler               $cron
 */
list($context, $orm, $dispatch, $cron) = [ $providers['context'], $providers['orm'], $providers['dispatch'], $providers['cron']];

$result = [
    'errors'                => 0,
    'warnings'              => 0,
    'logs'                  => []
];

$last_updatable_day = strtotime('+2 years -2 days', time());

// trunk end date if needed
if($params['date_to'] > $last_updatable_day) {
    $params['date_to'] = $last_updatable_day+86400;
}

// ignore updates for dates rejected by Cubilis (+2 years)
if($params['date_from'] <= $last_updatable_day) {

    // $result['logs'][] = "performing checks for interval : ".date('Y-m-d', $params['date_from']).' to '.date('Y-m-d', $params['date_to']);

    // append parents and children to target rental units
    $map_rental_units_ids = array_flip($params['rental_units_ids']);
    $units = RentalUnit::ids($params['rental_units_ids'])->read(['parent_id', 'children_ids'])->get(true);
    foreach($units as $unit) {
        if($unit['parent_id']) {
            $map_rental_units_ids[$unit['parent_id']] = true;
        }
        if($unit['children_ids'] && count($unit['children_ids'])) {
            foreach($unit['children_ids'] as $child_id) {
                $map_rental_units_ids[$child_id] = true;
            }
        }
    }

    // $result['logs'][] = "impacted rental units: ".implode(',', array_keys($map_rental_units_ids));

    try {
        $map_room_types_ids = [];
        $rental_units = RentalUnit::ids(array_keys($map_rental_units_ids))->read(['room_types_ids'])->get(true);
        foreach($rental_units as $rental_unit) {
            if(is_array($rental_unit['room_types_ids']) && count($rental_unit['room_types_ids'])) {
                foreach($rental_unit['room_types_ids'] as $room_type_id) {
                    $map_room_types_ids[$room_type_id] = true;
                }
            }
        }

        $room_types_ids = array_keys($map_room_types_ids);
        $result['logs'][] = "impacted room types: ".implode(',', $room_types_ids);

        $room_types = RoomType::ids($room_types_ids)->read(['id', 'center_id', 'is_active', 'property_id' => ['id', 'is_active', 'extref_property_id'], 'extref_roomtype_id', 'rental_units_ids'])->get(true);

        foreach($room_types as $room_type) {
            // discard non-active Properties
            if( (!isset($room_type['property_id']['is_active']) || !$room_type['property_id']['is_active'])
                || !$room_type['is_active']) {
                continue;
            }
            $total = count($room_type['rental_units_ids']);
            $map_count_available = [];
            for($d = $params['date_from']; $d < $params['date_to']; $d = strtotime('+1 day', $d)) {
                $date_index = substr(date('c', $d), 0, 10);
                $map_count_available[$date_index] = $total;
            }

            // #memo - this logic is duplicated in `stat-roomtypes`

            // #memo - this returns the compacted version of the consumptions (with virtual consumptions that span over several days) holding a date_from and date_to computed fields
            $map_existing_consumptions = Consumption::getExistingConsumptions($orm, [$room_type['center_id']], $params['date_from'], $params['date_to']);

            foreach($map_existing_consumptions as $rental_unit_id => $dates) {
                // we consider consumptions relating to rental unit, whatever the type of the consumption
                if(in_array($rental_unit_id, $room_type['rental_units_ids'])) {
                    foreach($dates as $index => $consumptions) {
                        foreach($consumptions as $consumption) {
                            for($d = $consumption['date_from']+$consumption['schedule_from']; $d < $consumption['date_to']+$consumption['schedule_to']; $d = strtotime('+1 day', $d)) {
                                $date_index = substr(date('c', $d), 0, 10);
                                if(isset($map_count_available[$date_index]) && $map_count_available[$date_index] > 0) {
                                    --$map_count_available[$date_index];
                                    // #memo #todo - this might require a review in case of change in the logic of repairings (the same processing occurs in the planning.calendar.component of the Booking App)
                                    if($consumption['type'] == 'ooo' && $d == $consumption['date_from']+$consumption['schedule_from']) {
                                        $prev_date_index = substr(date('c', strtotime('-1 day', $consumption['date_from']+$consumption['schedule_from'])), 0, 10);
                                        if(isset($map_count_available[$prev_date_index]) && $map_count_available[$prev_date_index] > 0) {
                                            --$map_count_available[$prev_date_index];
                                        }
                                    }
                                }
                                else {
                                    // unexpected situation (date_index is not part of the range)
                                }
                            }
                        }
                    }
                }
            }

            // prevent sending updates involving more than 30 requests to Cubilis
            if(count($map_count_available) > 31) {
                ++$result['errors'];
                $result['logs'][] = "ERR - Update limit reached for property {$room_type['property_id']['extref_property_id']} - room type {$room_type['extref_roomtype_id']} : limit is set to 30 dates. Manual update in Cubilis is necessary.";
                continue;
            }
            ob_start();
            print_r($map_count_available);
            $out = ob_get_clean();
            $result['logs'][] = 'Identified '.count($map_count_available)." updates for room type {$room_type['id']}: $out";

            $today_index = date('Ymd');
            foreach($map_count_available as $date_index => $count_available) {
                // discard dates in the past
                if(intval(str_replace('-', '', $date_index)) < intval($today_index)) {
                    continue;
                }
                // make sure available count is positive or null
                if($count_available < 0) {
                    $count_available = 0;
                }
                // send an update to Cubilis (set_availability) with the new value of availability for these dates (Cubilis crawls the date span based on nights / last date is discarded)
                try {
                    // echo "$date_index : updating room type {$room_type['extref_roomtype_id']}, setting availability to {$count_available}".PHP_EOL;
                    eQual::run('do', 'lodging_cubilis_update-availability', [
                            'property_id'   => $room_type['property_id']['extref_property_id'],
                            'room_type_id'  => $room_type['extref_roomtype_id'],
                            'date'          => $date_index,
                            'availability'  => $count_available
                        ]);
                    $result['logs'][] = "$date_index : updated room type {$room_type['extref_roomtype_id']} for property {$room_type['property_id']['extref_property_id']}, setting availability to {$count_available}";
                    // prevent flooding Cubilis
                    usleep(500 * 1000);
                }
                catch(Exception $e) {
                    // unexpected error : warn tech team
                    ++$result['errors'];
                    $result['logs'][] = "ERR - Unable to set new availability ($count_available) for property {$room_type['property_id']['extref_property_id']} - room type  {$room_type['extref_roomtype_id']} on $date_index (retry scheduled):".$e->getMessage();
                    // wait 15 seconds for preventing error "Transaction context in use by another session"
                    usleep(15 * 1000 * 1000);
                    // reschedule a sync attempt in 5 minutes
                    $cron->schedule(
                            "channelmanager.check-contingencies.retry.{$room_type['center_id']}",
                            time()+(5*60),
                            'lodging_booking_check-contingencies',
                            [
                                'date_from'         => date('c', $date_index),
                                'date_to'           => date('c', strtotime($date_index.' +1day')),
                                'rental_units_ids'  => $room_type['rental_units_ids']
                            ]
                        );
                }
            }
        }
    }
    catch(Exception $e) {
        ++$result['errors'];
        $result['logs'][] = "ERR - Unexpected error while checking contingencies : ".$e->getMessage();
    }

    // some errors or warnings might still have occurred : send an error alert email to YB and Kaleo Manager
    if($result['warnings'] || $result['errors']) {
        ob_start();
        print_r($result);
        $report = ob_get_clean();

        // build email message
        $message = new Email();
        $message->setTo(constant('EMAIL_REPORT_RECIPIENT'))
                ->addCc(constant('EMAIL_ERRORS_RECIPIENT'))
                ->setSubject('Discope - ERREUR (Cubilis)')
                ->setContentType("text/html")
                ->setBody("<html>
                        <body>
                        <p>Alertes lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
                        <pre>".$report."</pre>
                        </body>
                    </html>");

        // queue message
        Mail::queue($message);
    }

}


$context->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
