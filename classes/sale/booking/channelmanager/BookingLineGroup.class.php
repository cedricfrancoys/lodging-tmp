<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;


class BookingLineGroup extends \lodging\sale\booking\BookingLineGroup {

    public static function getName() {
        return "Booking line group";
    }

    public static function getDescription() {
        return "Booking line groups are related to a booking and describe one or more sojourns and their related consumptions.";
    }

    public static function getColumns() {
        return [
            'is_sojourn' => [
                'type'              => 'boolean',
                'description'       => 'Does the group spans over several nights and relate to accommodations?',
                'default'           => false
            ],

            'is_event' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relate to an event occurring on a single day?',
                'default'           => false
            ],

            'has_pack' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relates to a pack?',
                'default'           => false
            ],

            'pack_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => 'Pack (product) the group relates to, if any.',
                'visible'           => ['has_pack', '=', true]
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price (retrieved by price list) the pack relates to.',
                'visible'           => ['has_pack', '=', true]
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => 'Are modifications disabled for the group?',
                'default'           => false,
                'visible'           => ['has_pack', '=', true]
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Day of arrival.",
                'default'           => time()
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Day of departure.",
                'default'           => time()
            ],

            'time_from' => [
                'type'              => 'time',
                'description'       => "Checkin time on the day of arrival.",
                'default'           => 14 * 3600
            ],

            'time_to' => [
                'type'              => 'time',
                'description'       => "Checkout time on the day of departure.",
                'default'           => 10 * 3600
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\SojournType',
                'description'       => 'The kind of sojourn the group is about.',
                'default'           => 1,
                'visible'           => ['is_sojourn', '=', true]
            ],

            'nb_pers' => [
                'type'              => 'integer',
                'description'       => 'Amount of persons this group is about.',
                'default'           => 1
            ],

            /* a booking can be split into several groups on which distinct rate classes apply, by default the rate_class of the customer is used */
            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The fare class that applies to the group.",
                // default to 'general public'
                'default'           => 4
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLine',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines that belong to the group.',
                'ondetach'          => 'delete',
                'order'             => 'order'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\Booking',
                'description'       => 'Booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            // we mean rental_units_ids (for rental units assignment)
            // #todo - deprecate
            'accomodations_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\BookingLine',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines relating to accommodations.',
                'ondetach'          => 'delete',
                'domain'            => ['is_rental_unit', '=', true]
            ],

            'age_range_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\BookingLineGroupAgeRangeAssignment',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Age range assignments defined for the group.'
            ]

        ];
    }

    /**
     *
     */
    public static function oncreate($om, $oids, $values, $lang) {

    }



    /**
     * Check wether an object can be created, and optionally perform additional operations.
     * These tests come in addition to the unique constraints return by method `getUnique()`.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {

        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {

        return parent::canupdate($om, $oids, $values, $lang);
    }

    /**
     * Check wether an object can be deleted, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return boolean  Returns true if the object can be deleted, or false otherwise.
     */
    public static function candelete($om, $ids) {
        $groups = $om->read(self::getType(), $ids, ['booking_id.status', 'is_extra']);

        if($groups > 0) {
            foreach($groups as $group) {

            }
        }

        return parent::candelete($om, $ids);
    }

    /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $ids) {

        return parent::ondelete($om, $ids);
    }

    /**
     * Hook invoked after object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function onafterdelete($om, $oids) {
        // #memo - we do this to handle case where auto products are re-created during the delete cycle
        $lines_ids = $om->search(BookingLine::getType(), ['booking_line_group_id', 'in', $oids]);
        $om->delete(BookingLine::getType(), $lines_ids, true);
    }



    /**
     * Resets all rental unit assignments and process each line for auto-assignment, if possible.
     *
     *   1) decrement nb_pers for lines accounted by 'accomodation' (capacity)
     *   2) create missing SPM
     *
     *  qty_accounting_method = 'accomodation'
     *    (we consider product and unit to have is_accomodation to true)
     *    1) find a free accomodation  (capacity >= product_model.capacity)
     *    2) create assignment @capacity
     *
     *  qty_accounting_method = 'person'
     *  if is_accomodation
     *      1) find a free accomodation
     *      2) create assignment @nb_pers
     *        (ignore next lines accounted by 'person')
     *  otherwise
     *       1) find a free rental unit
     *       2) create assignment @group.nb_pers
     *
     *  qty_accounting_method = 'unit'
     *      1) find a free rental unit
     *      2) create assignment @group.nb_pers
     */
    public static function createRentalUnitsAssignments($om, $oids, $values, $lang) {
        /*
            Update of the rental-units assignments

            ## when we "add" a booking line (onupdateProductId)
            * we create new rental-unit assignments depending on the product_model of the line

            ## when we remove a booking line (onupdateBookingLinesIds)
            * we do a reset of the rental-unit assignments

            ## when we update nb_pers (onupdateNbPers) or age range qty fields
            * we do a reset of the rental-unit assignments

            ## when we update a pack (onupdatePackId)

            * we reset rental-unit assignments
            * we create an assignment for all line at once (_createRentalUnitsAssignements)

            ## when we remove an age-range (ondelete)
            * we remove all lines whose product_id relates to that age-range
        */

        /* find existing SPM (for resetting) */

        $groups = $om->read(self::getType(), $oids, [
            'booking_id.center_office_id',
            'has_locked_rental_units',
            'booking_lines_ids',
            'sojourn_product_models_ids'
        ]);

        foreach($groups as $gid => $group) {
            // retrieve rental unit assignment preference
            $rentalunits_manual_assignment = false;
            $offices_preferences = $om->read(\lodging\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['rentalunits_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $rentalunits_manual_assignment = (bool) $prefs['rentalunits_manual_assignment'];
            }
            // ignore groups with explicitly locked rental unit assignments
            if(!$rentalunits_manual_assignment && $group['has_locked_rental_units']) {
                continue;
            }
            if(!$rentalunits_manual_assignment) {
                // remove all previous SPM and rental_unit assignements
                $om->update(self::getType(), $gid, ['sojourn_product_models_ids' => array_map(function($a) { return "-$a";}, $group['sojourn_product_models_ids'])]);
            }
            // attempt to auto-assign rental units
            $om->callonce(self::getType(), 'createRentalUnitsAssignmentsFromLines', $gid, $group['booking_lines_ids'], $lang);
        }

    }

    /**
     * Resets and recreates consumptions that relate to the given groups.
     * This method is meant to be used for 'extra' groups, that require immediate update of the consumptions.
     * #memo - When a whole booking must (re)create its consumptions, use Booking::createConsumptions()
     * #memo - consumptions are used in the planning.
     *
     */
    public static function createConsumptions($om, $ids, $values, $lang) {

        $groups = $om->read(self::getType(), $ids, ['booking_id', 'booking_id.status', 'consumptions_ids'], $lang);

        // remove previous consumptions
        $bookings_ids = [];
        foreach($groups as $gid => $group) {
            $om->delete(Consumption::getType(), $group['consumptions_ids'], true);
            if($group['booking_id.status'] != 'quote') {
                $bookings_ids[] = $group['booking_id'];
            }
        }

        // recreate consumptions for all impacted extra groups

        // get in-memory list of consumptions for all groups
        $consumptions = $om->call(self::getType(), 'getResultingConsumptions', $ids, [], $lang);
        foreach($consumptions as $consumption) {
            $om->create(Consumption::getType(), $consumption, $lang);
        }

        // schedule a check for non quote bookings
        $cron = $om->getContainer()->get('cron');
        foreach($bookings_ids as $booking_id) {
            // add a task to the CRON for updating status of bookings waiting for the pricelist
            $cron->schedule(
                    "booking.assign.units.{$booking_id}",
                    // run as soon as possible
                    time() + 60,
                    'lodging_booking_check-units-assignments',
                    [ 'id' => $booking_id ]
                );
        }

    }

    public static function getResultingConsumptions($om, $oids, $values, $lang) {
        $consumptions = [];
        $groups = $om->read(self::getType(), $oids, [
                    'booking_id',
                    'booking_id.center_id',
                    'booking_lines_ids',
                    'nb_pers',
                    'nb_nights',
                    'is_event',
                    'is_sojourn',
                    'date_from',
                    'time_from',
                    'time_to',
                    'age_range_assignments_ids',
                    'rental_unit_assignments_ids',
                    'meal_preferences_ids'
                ],
                $lang);

        if($groups > 0) {

            // pass-1 : create consumptions for rental_units
            foreach($groups as $gid => $group) {
                // retrieve assigned rental units (assigned during booking)

                $sojourn_products_models_ids = $om->search(SojournProductModel::getType(), ['booking_line_group_id', '=', $gid]);
                if($sojourn_products_models_ids <= 0) {
                    continue;
                }
                $sojourn_product_models = $om->read(SojournProductModel::getType(), $sojourn_products_models_ids, [
                        'product_model_id',
                        'product_model_id.is_accomodation',
                        'product_model_id.qty_accounting_method',
                        'product_model_id.schedule_offset',
                        'product_model_id.schedule_default_value',
                        'rental_unit_assignments_ids'
                    ]);
                if($sojourn_product_models <= 0) {
                    continue;
                }
                foreach($sojourn_product_models as $spid => $spm) {
                    $rental_units_assignments = $om->read(SojournProductModelRentalUnitAssignement::getType(), $spm['rental_unit_assignments_ids'], ['rental_unit_id','qty']);
                    // retrieve all involved rental units (limited to 2 levels above and 2 levels below)
                    $rental_units = [];
                    if($rental_units_assignments > 0) {
                        $rental_units_ids = array_map(function ($a) { return $a['rental_unit_id']; }, array_values($rental_units_assignments));

                        // fetch 2 levels of rental units identifiers
                        for($i = 0; $i < 2; ++$i) {
                            $units = $om->read('lodging\realestate\RentalUnit', $rental_units_ids, ['parent_id', 'children_ids', 'can_partial_rent']);
                            if($units > 0) {
                                foreach($units as $uid => $unit) {
                                    if($unit['parent_id'] > 0) {
                                        $rental_units_ids[] = $unit['parent_id'];
                                    }
                                    if(count($unit['children_ids'])) {
                                        foreach($unit['children_ids'] as $uid) {
                                            $rental_units_ids[] = $uid;
                                        }
                                    }
                                }
                            }
                        }
                        // read all involved rental units
                        $rental_units = $om->read('lodging\realestate\RentalUnit', $rental_units_ids, ['parent_id', 'children_ids', 'can_partial_rent']);
                    }

                    // being an accomodation or not, the rental unit will be (partially) occupied on range of nb_night+1 day(s)
                    $nb_nights = $group['nb_nights']+1;

                    if($spm['product_model_id.qty_accounting_method'] == 'person') {
                        // #todo - we don't check (yet) for daily variations (from booking lines)
                        // rental_units_assignments.qty should be adapted on a daily basis
                    }

                    list($day, $month, $year) = [ date('j', $group['date_from']), date('n', $group['date_from']), date('Y', $group['date_from']) ];

                    // retrieve default time for consumption
                    list($hour_from, $minute_from, $hour_to, $minute_to) = [12, 0, 13, 0];
                    $schedule_default_value = $spm['product_model_id.schedule_default_value'];
                    if(strpos($schedule_default_value, ':')) {
                        $parts = explode('-', $schedule_default_value);
                        list($hour_from, $minute_from) = explode(':', $parts[0]);
                        list($hour_to, $minute_to) = [$hour_from+1, $minute_from];
                        if(count($parts) > 1) {
                            list($hour_to, $minute_to) = explode(':', $parts[1]);
                        }
                    }

                    $schedule_from  = $hour_from * 3600 + $minute_from * 60;
                    $schedule_to    = $hour_to * 3600 + $minute_to * 60;

                    // fetch the offset, in days, for the scheduling (applies only on sojourns)
                    $offset = ($group['is_sojourn'])?$spm['product_model_id.schedule_offset']:0;
                    $is_accomodation = $spm['product_model_id.is_accomodation'];

                    // for events, non-accomodations are scheduled according to the event (group)
                    if($group['is_event'] && !$is_accomodation) {
                        $schedule_from = $group['time_from'];
                        $schedule_to = $group['time_to'];
                    }

                    $is_first = true;
                    for($i = 0; $i < $nb_nights; ++$i) {
                        $c_date = mktime(0, 0, 0, $month, $day+$i+$offset, $year);
                        $c_schedule_from = $schedule_from;
                        $c_schedule_to = $schedule_to;

                        // first accomodation has to match the checkin time of the sojourn (from group)
                        if($is_first && $is_accomodation) {
                            $is_first = false;
                            $diff = $c_schedule_to - $schedule_from;
                            $c_schedule_from = $group['time_from'];
                            $c_schedule_to = $c_schedule_from + $diff;
                        }

                        // if day is not the arrival day
                        if($i > 0) {
                            $c_schedule_from = 0;                       // midnight same day
                        }

                        if($i == ($nb_nights-1) || !$is_accomodation) { // last day
                            $c_schedule_to = $group['time_to'];
                        }
                        else {
                            $c_schedule_to = 24 * 3600;                 // midnight next day
                        }

                        if($rental_units_assignments > 0) {
                            foreach($rental_units_assignments as $assignment) {
                                $rental_unit_id = $assignment['rental_unit_id'];
                                $consumption = [
                                    'booking_id'            => $group['booking_id'],
                                    'booking_line_group_id' => $gid,
                                    'center_id'             => $group['booking_id.center_id'],
                                    'date'                  => $c_date,
                                    'schedule_from'         => $c_schedule_from,
                                    'schedule_to'           => $c_schedule_to,
                                    'product_model_id'      => $spm['product_model_id'],
                                    'age_range_id'          => null,
                                    'is_rental_unit'        => true,
                                    'is_accomodation'       => $spm['product_model_id.is_accomodation'],
                                    'is_meal'               => false,
                                    'rental_unit_id'        => $rental_unit_id,
                                    'qty'                   => $assignment['qty'],
                                    'type'                  => 'book'
                                ];
                                $consumptions[] = $consumption;

                                // 1) recurse through children : all child units are blocked as 'link'
                                $children_ids = [];
                                $children_stack = (isset($rental_units[$rental_unit_id]) && isset($rental_units[$rental_unit_id]['children_ids']))?$rental_units[$rental_unit_id]['children_ids']:[];
                                while(count($children_stack)) {
                                    $unit_id = array_pop($children_stack);
                                    $children_ids[] = $unit_id;
                                    if(isset($rental_units[$unit_id]) && $rental_units[$unit_id]['children_ids']) {
                                        foreach($units[$unit_id]['children_ids'] as $child_id) {
                                            $children_stack[] = $child_id;
                                        }
                                    }
                                }

                                foreach($children_ids as $child_id) {
                                    $consumption['type'] = 'link';
                                    $consumption['rental_unit_id'] = $child_id;
                                    $consumptions[] = $consumption;
                                }

                                // 2) loop through parents : if a parent has 'can_partial_rent', it is partially blocked as 'part', otherwise fully blocked as 'link'
                                $parents_ids = [];
                                $unit_id = $rental_unit_id;

                                while( isset($rental_units[$unit_id]) ) {
                                    $parent_id = $rental_units[$unit_id]['parent_id'];
                                    if($parent_id > 0) {
                                        $parents_ids[] = $parent_id;
                                    }
                                    $unit_id = $parent_id;
                                }

                                foreach($parents_ids as $parent_id) {
                                    $consumption['type'] = ($rental_units[$parent_id]['can_partial_rent'])?'part':'link';
                                    $consumption['rental_unit_id'] = $parent_id;
                                    $consumptions[] = $consumption;
                                }
                            }
                        }
                    }
                }
            }

            // pass-2 : create consumptions for booking lines targeting non-rental_unit products (any other schedulable product, e.g. meals)
            foreach($groups as $gid => $group) {

                $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                        'product_id',
                        'qty',
                        'qty_vars',
                        'product_id.product_model_id',
                        'product_id.has_age_range',
                        'product_id.age_range_id'
                    ],
                    $lang);

                if($lines > 0 && count($lines)) {

                    // read all related product models at once
                    $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_id.product_model_id'];}, array_keys($lines));
                    $product_models = $om->read(\lodging\sale\catalog\ProductModel::getType(), $product_models_ids, [
                            'type',
                            'service_type',
                            'schedule_offset',
                            'schedule_type',
                            'schedule_default_value',
                            'qty_accounting_method',
                            'has_duration',
                            'duration',
                            'is_rental_unit',
                            'is_accomodation',
                            'is_meal'
                        ]);

                    // create consumptions according to each line product and quantity
                    foreach($lines as $lid => $line) {

                        if($line['qty'] <= 0) {
                            continue;
                        }
                        // ignore rental units : these are already been handled for the booking (grouped in SPM rental unit assignments)
                        if($product_models[$line['product_id.product_model_id']]['is_rental_unit']) {
                            continue;
                        }

                        $product_type = $product_models[$line['product_id.product_model_id']]['type'];
                        $service_type = $product_models[$line['product_id.product_model_id']]['service_type'];
                        $has_duration = $product_models[$line['product_id.product_model_id']]['has_duration'];

                        // consumptions are schedulable services
                        if($product_type != 'service' || $service_type != 'schedulable') {
                            continue;
                        }

                        // retrieve default time for consumption
                        list($hour_from, $minute_from, $hour_to, $minute_to) = [12, 0, 13, 0];
                        $schedule_default_value = $product_models[$line['product_id.product_model_id']]['schedule_default_value'];
                        if(strpos($schedule_default_value, ':')) {
                            $parts = explode('-', $schedule_default_value);
                            list($hour_from, $minute_from) = explode(':', $parts[0]);
                            list($hour_to, $minute_to) = [$hour_from+1, $minute_from];
                            if(count($parts) > 1) {
                                list($hour_to, $minute_to) = explode(':', $parts[1]);
                            }
                        }
                        $schedule_from  = $hour_from * 3600 + $minute_from * 60;
                        $schedule_to    = $hour_to * 3600 + $minute_to * 60;

                        $is_meal = $product_models[$line['product_id.product_model_id']]['is_meal'];
                        $qty_accounting_method = $product_models[$line['product_id.product_model_id']]['qty_accounting_method'];

                        // #memo - number of consumptions differs for accommodations (rooms are occupied nb_nights + 1, until sometime in the morning)
                        // #memo - sojourns are accounted in nights, while events are accounted in days
                        $nb_products = ($group['is_sojourn'])?$group['nb_nights']:(($group['is_event'])?$group['nb_nights']+1:1);
                        $nb_times = $group['nb_pers'];

                        // adapt nb_pers based on if product from line has age_range
                        if($qty_accounting_method == 'person') {
                            $age_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['age_range_id', 'qty']);
                            if($line['product_id.has_age_range']) {
                                foreach($age_assignments as $aid => $assignment) {
                                    if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                                        $nb_times = $assignment['qty'];
                                    }
                                }
                            }
                        }
                        // adapt duration for products with fixed duration
                        if($has_duration) {
                            $nb_products = $product_models[$line['product_id.product_model_id']]['duration'];
                        }

                        list($day, $month, $year) = [ date('j', $group['date_from']), date('n', $group['date_from']), date('Y', $group['date_from']) ];
                        // fetch the offset, in days, for the scheduling (only applies on sojourns)
                        $offset = ($group['is_sojourn'])?$product_models[$line['product_id.product_model_id']]['schedule_offset']:0;

                        $days_nb_times = array_fill(0, $nb_products, $nb_times);

                        if( $qty_accounting_method == 'person' && ($nb_times * $nb_products) != $line['qty']) {
                            // $nb_times varies from one day to another : load specific days_nb_times array
                            $qty_vars = json_decode($line['qty_vars']);
                            // qty_vars is set and valid
                            if($qty_vars) {
                                $i = 0;
                                foreach($qty_vars as $variation) {
                                    if($nb_products < $i+1) {
                                        break;
                                    }
                                    $days_nb_times[$i] = $nb_times + $variation;
                                    ++$i;
                                }
                            }
                        }

                        // $nb_products represent each day of the stay
                        for($i = 0; $i < $nb_products; ++$i) {
                            $c_date = mktime(0, 0, 0, $month, $day+$i+$offset, $year);
                            $c_schedule_from = $schedule_from;
                            $c_schedule_to = $schedule_to;

                            // create a single consumption with the quantity set accordingly (may vary from one day to another)

                            $consumption = [
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $gid,
                                'booking_line_id'       => $lid,
                                'center_id'             => $group['booking_id.center_id'],
                                'date'                  => $c_date,
                                'schedule_from'         => $c_schedule_from,
                                'schedule_to'           => $c_schedule_to,
                                'product_model_id'      => $line['product_id.product_model_id'],
                                'age_range_id'          => $line['product_id.age_range_id'],
                                'is_rental_unit'        => false,
                                'is_accomodation'       => false,
                                'is_meal'               => $is_meal,
                                'qty'                   => $days_nb_times[$i],
                                'type'                  => 'book'
                            ];
                            // for meals, we add the age ranges and prefs with the description field
                            if($is_meal) {
                                $description = '';
                                $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['age_range_id.name','qty'], $lang);
                                $meal_preferences = $om->read(\sale\booking\MealPreference::getType(), $group['meal_preferences_ids'], ['type','pref', 'qty'], $lang);
                                foreach($age_range_assignments as $oid => $assignment) {
                                    $description .= "<p>{$assignment['age_range_id.name']} : {$assignment['qty']} ; </p>";
                                }
                                foreach($meal_preferences as $oid => $preference) {
                                    // #todo : use translation file
                                    $type = ($preference['type'] == '3_courses')?'3 services':'2 services';
                                    $pref = ($preference['pref'] == 'veggie')?'végétarien':(($preference['pref'] == 'allergen_free')?'sans allergène':'normal');
                                    $description .= "<p>{$type} / {$pref} : {$preference['qty']} ; </p>";
                                }
                                $consumption['description'] = $description;
                            }
                            $consumptions[] = $consumption;
                        }
                    }
                }
            }
        }

        return $consumptions;
    }



}