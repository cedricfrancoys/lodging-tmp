<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class BookingLine extends \sale\booking\BookingLine {

    public static function getName() {
        return "Booking line";
    }

    public static function getDescription() {
        return "Booking lines describe the products and quantities that are part of a booking.";
    }

    public static function getColumns() {
        return [

            'qty' => [
                'type'              => 'float',
                'description'       => 'Quantity of product items for the line.',
                'onupdate'          => 'onupdateQty',
                'default'           => 1.0
            ],

            'is_rental_unit' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to a rental unit (from product_model).',
                'function'          => 'calcIsRentalUnit',
                'store'             => true
            ],

            'is_accomodation' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to an accommodation (from product_model).',
                'function'          => 'calcIsAccomodation',
                'store'             => true
            ],

            'is_meal' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to a meal (from product_model).',
                'function'          => 'calcIsMeal',
                'store'             => true
            ],

            'qty_accounting_method' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Quantity accounting method (from product_model).',
                'function'          => 'calcQtyAccountingMethod',
                'store'             => true
            ],

            'qty_vars' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'JSON array holding qty variation deltas (for \'by person\' products), if any.',
                'onupdate'          => 'onupdateQtyVars'
            ],

            'is_autosale' => [
                'type'              => 'boolean',
                'description'       => 'Does the line relate to an autosale product?',
                'default'           => false
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => BookingLineGroup::getType(),
                'description'       => 'Group the line relates to (in turn, groups relate to their booking).',
                'required'          => true,             // must be set at creation
                'onupdate'          => 'onupdateBookingLineGroupId',
                'ondelete'          => 'cascade'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Booking::getType(),
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.',
                'onupdate'          => 'onupdateProductId'
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\ProductModel',
                'description'       => 'The product model the line relates to (from product).'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Consumption',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'Consumptions related to the booking line.',
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => BookingPriceAdapter::getType(),
                'foreign_field'     => 'booking_line_id',
                'description'       => 'Price adapters holding the manual discounts applied on the line.',
                'onupdate'          => 'sale\booking\BookingLine::onupdatePriceAdaptersIds'
            ]

        ];
    }

    public static function calcIsAccomodation($om, $oids, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:calcIsAccomodation", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_accomodation'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_accomodation'];
            }
        }
        return $result;
    }

    public static function calcIsRentalUnit($om, $oids, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:calcIsRentalUnit", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_rental_unit'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_rental_unit'];
            }
        }
        return $result;
    }

    public static function calcIsMeal($om, $oids, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:calcIsMeal", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_meal'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_meal'];
            }
        }
        return $result;
    }

    public static function calcQtyAccountingMethod($om, $oids, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:calcQtyAccountingMethod", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.qty_accounting_method'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.qty_accounting_method'];
            }
        }
        return $result;
    }

    /**
     *
     * New group assignment (should be called upon creation only)
     *
     */
    public static function onupdateBookingLineGroupId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:onupdateBookingLineGroupId", QN_REPORT_DEBUG);
    }

    /**
     * Update the price_id according to booking line settings.
     *
     * This method is called at booking line creation if product_id is amongst the fields.
     */
    public static function onupdateProductId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:onupdateProductId", QN_REPORT_DEBUG);

        /*
            update product model according to newly set product
        */
        $lines = $om->read(self::getType(), $oids, ['product_id.product_model_id', 'booking_line_group_id'], $lang);
        foreach($lines as $lid => $line) {
            $om->update(self::getType(), $lid, ['product_model_id' => $line['product_id.product_model_id']]);
        }

        /*
            reset computed fields related to product model
        */
        $om->update(self::getType(), $oids, ['name' => null, 'qty_accounting_method' => null, 'is_rental_unit' => null, 'is_accomodation' => null, 'is_meal' => null]);

        /*
            update SPM, if necessary
        */
        $om->callonce(self::getType(), '_updateSPM', $oids);

        /*
            resolve price_id according to new product_id
        */
        $om->callonce(self::getType(), '_updatePriceId', $oids, [], $lang);

        /*
            check booking type and checkin/out times dependencies, and auto-assign qty if required
        */

        $lines = $om->read(self::getType(), $oids, [
                'product_id.product_model_id',
                'product_id.product_model_id.booking_type_id',
                'product_id.product_model_id.capacity',
                'product_id.product_model_id.has_duration',
                'product_id.product_model_id.duration',
                'product_id.product_model_id.is_repeatable',
                'product_id.has_age_range',
                'product_id.age_range_id',
                'booking_id',
                'booking_line_group_id',
                'booking_line_group_id.is_sojourn',
                'booking_line_group_id.is_event',
                'booking_line_group_id.nb_pers',
                'booking_line_group_id.nb_nights',
                'booking_line_group_id.has_pack',
                'booking_line_group_id.pack_id.has_age_range',
                'booking_line_group_id.age_range_assignments_ids',
                'qty',
                'has_own_qty',
                'is_rental_unit',
                'is_accomodation',
                'is_meal',
                'qty_accounting_method'
            ], $lang);

        foreach($lines as $lid => $line) {
            // if model of chosen product has a non-generic booking type, update the booking of the line accordingly
            if(isset($line['product_id.product_model_id.booking_type_id']) && $line['product_id.product_model_id.booking_type_id'] != 1) {
                $om->update(Booking::getType(), $line['booking_id'], ['type_id' => $line['product_id.product_model_id.booking_type_id']]);
            }

            // if line is a rental unit, use its related product info to update parent group schedule, if possible
            if($line['is_rental_unit']) {
                $models = $om->read(\lodging\sale\catalog\ProductModel::getType(), $line['product_id.product_model_id'], ['type', 'service_type', 'schedule_type', 'schedule_default_value'], $lang);
                if($models > 0 && count($models)) {
                    $model = reset($models);
                    if($model['type'] == 'service' && $model['service_type'] == 'schedulable' && $model['schedule_type'] == 'timerange') {
                        // retrieve relative timestamps
                        $schedule = $model['schedule_default_value'];
                        if(strlen($schedule)) {
                            $times = explode('-', $schedule);
                            $parts = explode(':', $times[0]);
                            $schedule_from = $parts[0]*3600 + $parts[1]*60;
                            $parts = explode(':', $times[1]);
                            $schedule_to = $parts[0]*3600 + $parts[1]*60;
                            // update the parent group schedule
                            $om->update(BookingLineGroup::getType(), $line['booking_line_group_id'], ['time_from' => $schedule_from, 'time_to' => $schedule_to], $lang);
                        }
                    }
                }
            }
        }

        // #memo - qty must always be recomputed, even if given amongst (updated) $values (when a new line is created the default qty is 1.0)
        foreach($lines as $lid => $line) {
            $qty = $line['qty'];
            if(!$line['has_own_qty']) {
                // retrieve number of persons to whom the product will be delivered (either nb_pers or age_range.qty)
                $nb_pers = $line['booking_line_group_id.nb_pers'];
                // retrieve nb_pers from age range
                // #memo - if parent group has a age_range set, keep `booking_line_group_id.nb_pers`
                if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
                    $age_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
                    foreach($age_assignments as $assignment) {
                        if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                            $nb_pers = $assignment['qty'];
                            break;
                        }
                    }
                }
                // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
                $nb_repeat = 1;
                if($line['product_id.product_model_id.has_duration']) {
                    $nb_repeat = $line['product_id.product_model_id.duration'];
                }
                elseif($line['booking_line_group_id.is_sojourn']) {
                    if($line['product_id.product_model_id.is_repeatable']) {
                        $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                    }
                }
                elseif($line['booking_line_group_id.is_event']) {
                    if($line['product_id.product_model_id.is_repeatable']) {
                        $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                    }
                }
                // retrieve quantity to consider
                $qty = self::_computeLineQty(
                        $line['qty_accounting_method'],
                        $nb_repeat,
                        $nb_pers,
                        $line['product_id.product_model_id.is_repeatable'],
                        $line['is_accomodation'],
                        $line['product_id.product_model_id.capacity']
                    );
            }
            if($qty != $line['qty'] || $line['is_rental_unit']) {
                $om->update(self::getType(), $lid, ['qty' => $qty]);
            }
        }

        /*
            qty might have been updated: make sure qty_var is consistent
        */
        $om->callonce(self::getType(), '_updateQty', $oids, [], $lang);

        /*
            update parent groups rental unit assignments
        */

        // group lines by booking_line_group
        $sojourns = [];
        foreach($lines as $lid => $line) {
            $gid = $line['booking_line_group_id'];
            if(!isset($sojourns[$gid])) {
                $sojourns[$gid] = [];
            }
            $sojourns[$gid][] = $lid;
        }
        foreach($sojourns as $gid => $lines_ids) {
            $groups = $om->read(BookingLineGroup::getType(), $gid, ['has_locked_rental_units', 'booking_id.center_office_id']);
            if($groups > 0 && count($groups)) {
                $group = reset($groups);
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
            }
            // retrieve all impacted product_models
            $olines = $om->read(self::getType(), $lines_ids, ['product_id.product_model_id'], $lang);
            $product_models_ids = array_map(function($a) { return $a['product_id.product_model_id'];}, $olines);
            if(!$rentalunits_manual_assignment) {
                // remove all assignments from the group that relate to found product_model
                $spm_ids = $om->search(SojournProductModel::getType(), [['booking_line_group_id', '=', $gid], ['product_model_id', 'in', $product_models_ids]]);
                $om->remove(SojournProductModel::getType(), $spm_ids, true);
            }
            // retrieve all lines from parent group that need to be reassigned
            // #memo - we need to handle these all at a time to avoid assigning a same rental unit twice
            $lines_ids = $om->search(self::getType(), [['booking_line_group_id', '=', $gid], ['product_model_id', 'in', $product_models_ids]]);
            // recreate rental unit assignments
            $om->callonce(BookingLineGroup::getType(), 'createRentalUnitsAssignmentsFromLines', $gid, $lines_ids, $lang);
        }


        /*
            update parent groups price adapters
        */

        // group lines by booking_line_group
        $sojourns = [];
        foreach($lines as $lid => $line) {
            $gid = $line['booking_line_group_id'];
            if(!isset($sojourns[$gid])) {
                $sojourns[$gid] = [];
            }
            $sojourns[$gid][$lid] = true;
        }
        foreach($sojourns as $gid => $map_lines_ids) {
            $om->callonce(BookingLineGroup::getType(), 'updatePriceAdaptersFromLines', $gid, array_keys($map_lines_ids), $lang);
        }

        /*
            reset computed fields related to price
        */
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);

    }

    public static function onupdateQtyVars($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:onupdateQtyVars", QN_REPORT_DEBUG);

        // reset computed fields related to price
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);

        $lines = $om->read(self::getType(), $oids, [
            'qty_vars',
            'has_own_qty',
            'is_meal',
            'is_rental_unit',
            'is_accomodation',
            'qty_accounting_method',
            'booking_line_group_id.nb_pers',
            'booking_line_group_id.nb_nights',
            'booking_line_group_id.age_range_assignments_ids',
            'booking_line_group_id.is_sojourn',
            'booking_line_group_id.is_event',
            'booking_line_group_id.has_pack',
            'booking_line_group_id.pack_id.has_age_range',
            'product_id.product_model_id.capacity',
            'product_id.product_model_id.has_duration',
            'product_id.product_model_id.duration',
            'product_id.product_model_id.is_repeatable',
            'product_id.has_age_range',
            'product_id.age_range_id'
        ]);

        if($lines > 0) {
            // set quantities according to qty_vars arrays
            foreach($lines as $lid => $line) {
                // qty_vars should be a JSON array holding a series of deltas
                $qty_vars = json_decode($line['qty_vars']);
                if($qty_vars && !$line['has_own_qty']) {
                    // retrieve number of persons to whom the product will be delivered (either nb_pers or age_range.qty)
                    $nb_pers = $line['booking_line_group_id.nb_pers'];
                    // #memo - if parent group has a age_range set, keep `booking_line_group_id.nb_pers`
                    if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
                        $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
                        foreach($age_range_assignments as $assignment) {
                            if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                                $nb_pers = $assignment['qty'];
                                break;
                            }
                        }
                    }
                    // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
                    $nb_repeat = 1;
                    if($line['product_id.product_model_id.has_duration']) {
                        $nb_repeat = $line['product_id.product_model_id.duration'];
                    }
                    elseif($line['booking_line_group_id.is_sojourn']) {
                        if($line['product_id.product_model_id.is_repeatable']) {
                            $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                        }
                    }
                    elseif($line['booking_line_group_id.is_event']) {
                        if($line['product_id.product_model_id.is_repeatable']) {
                            $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                        }
                    }
                    // retrieve quantity to consider
                    $qty = self::_computeLineQty(
                            $line['qty_accounting_method'],
                            $nb_repeat,
                            $nb_pers,
                            $line['product_id.product_model_id.is_repeatable'],
                            $line['is_accomodation'],
                            $line['product_id.product_model_id.capacity']
                        );
                    // adapt final qty according to variations
                    foreach($qty_vars as $variation) {
                        $qty += $variation;
                    }
                    $om->update(self::getType(), $lid, ['qty' => $qty]);
                }
                else {
                    $om->callonce(self::getType(), '_updateQty', $oids, [], $lang);
                }
            }
        }
    }


    /**
     * Update the quantity of products.
     *
     * This handler is called at booking line creation and all subsequent qty updates.
     * It is in charge of updating the rental units assignments related to the line.
     */
    public static function onupdateQty($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:onupdateQty", QN_REPORT_DEBUG);

        // Reset computed fields related to price (because they depend on qty)
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);
    }


    /**
     * Check wether an object can be created, and optionally perform additional operations.
     * These tests come in addition to the unique constraints return by method `getUnique()`.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  array                      $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {
        if(isset($values['booking_id']) && isset($values['booking_line_group_id'])) {
            $bookings = $om->read(Booking::getType(), $values['booking_id'], ['status'], $lang);
            $groups = $om->read(BookingLineGroup::getType(), $values['booking_line_group_id'], ['is_extra', 'has_schedulable_services', 'has_consumptions'], $lang);

            if($bookings > 0 && $groups > 0) {
                $booking = reset($bookings);
                $group = reset($groups);

                if( in_array($booking['status'], ['invoiced', 'debit_balance', 'credit_balance', 'balanced'])
                    || ($booking['status'] != 'quote' && !$group['is_extra']) ) {
                    return ['status' => ['non_editable' => 'Non-extra service lines cannot be changed for non-quote bookings.']];
                }
                if( $group['is_extra'] && $group['has_schedulable_services'] && $group['has_consumptions']) {
                    return ['status' => ['non_editable' => 'Lines from extra services cannot be added once consumptions have been created.']];
                }
            }
        }

        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {

        // handle exceptions for fields that can always be updated
        $allowed = ['is_contractual', 'is_invoiced'];
        $count_non_allowed = 0;

        foreach($values as $field => $value) {
            if(!in_array($field, $allowed)) {
                ++$count_non_allowed;
            }
        }

        if($count_non_allowed > 0) {
            $lines = $om->read(self::getType(), $oids, ['booking_id.status', 'booking_line_group_id.is_extra', 'booking_line_group_id.has_schedulable_services', 'booking_line_group_id.has_consumptions'], $lang);
            if($lines > 0) {
                foreach($lines as $line) {
                    if($line['booking_id.status'] != 'quote' && !$line['booking_line_group_id.is_extra']) {
                        return ['booking_id' => ['non_editable' => 'Services cannot be updated for non-quote bookings.']];
                    }
                    if($line['booking_line_group_id.is_extra'] && $line['booking_line_group_id.has_schedulable_services'] && $line['booking_line_group_id.has_consumptions']) {
                        return ['booking_id' => ['non_editable' => 'Lines from extra services cannot be changed once consumptions have been created.']];
                    }
                }
            }
        }

        return parent::canupdate($om, $oids, $values, $lang);
    }

    /**
     * Check wether an object can be deleted, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @return boolean  Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $oids) {
        $lines = $om->read(self::getType(), $oids, ['booking_id.status', 'booking_line_group_id.is_extra', 'booking_line_group_id.has_schedulable_services', 'booking_line_group_id.has_consumptions']);

        if($lines > 0) {
            foreach($lines as $line) {
                // #memo - booking might have been deleted (this is triggered by the Booking::onafterdelete callback)
                if($line['booking_line_group_id.is_extra']) {
                    if($line['booking_id.status'] && !in_array($line['booking_id.status'], ['quote', 'confirmed', 'validated', 'checkedin', 'checkedout'])) {
                        return ['booking_id' => ['non_deletable' => 'Extra Services can only be updated after confirmation and before invoicing.']];
                    }
                }
                else {
                    if($line['booking_id.status'] && $line['booking_id.status'] != 'quote') {
                        return ['booking_id' => ['non_deletable' => 'Services cannot be updated for non-quote bookings.']];
                    }
                    if($line['booking_line_group_id.is_extra'] && $line['booking_line_group_id.has_schedulable_services'] && $line['booking_line_group_id.has_consumptions']) {
                        return ['booking_id' => ['non_editable' => 'Lines from extra services cannot be removed once consumptions have been created.']];
                    }
                }
            }
        }

        return parent::candelete($om, $oids);
    }

    /**
     * Update the quantity according to parent group (pack_id, nb_pers, nb_nights) and variation array.
     * This method is triggered on fields update from BookingLineGroup or onupdateQtyVars from BookingLine.
     *
     */
    public static function _updateQty($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:_updateQty", QN_REPORT_DEBUG);

        $lines = $om->read(self::getType(), $oids, [
                'product_id.has_age_range',
                'product_id.age_range_id',
                'product_id.product_model_id.capacity',
                'product_id.product_model_id.has_duration',
                'product_id.product_model_id.duration',
                'product_id.product_model_id.is_repeatable',
                'booking_line_group_id.is_sojourn',
                'booking_line_group_id.is_event',
                'booking_line_group_id.nb_pers',
                'booking_line_group_id.nb_nights',
                'booking_line_group_id.age_range_assignments_ids',
                'booking_line_group_id.has_pack',
                'booking_line_group_id.pack_id.has_age_range',
                'qty_vars',
                'has_own_qty',
                'is_rental_unit',
                'is_accomodation',
                'is_meal',
                'qty_accounting_method'
            ],
            $lang);

        if($lines > 0) {
            // update lines quantities
            foreach($lines as $lid => $line) {
                if($line['has_own_qty']) {
                    // own quantity has been assigned in onupdateProductId
                    continue;
                }
                $qty_vars = json_decode($line['qty_vars']);
                // qty_vars is not set yet
                if(!$qty_vars) {
                    // retrieve number of persons to whom the product will be delivered (either nb_pers or age_range.qty)
                    $nb_pers = $line['booking_line_group_id.nb_pers'];
                    // #memo - if parent group has a age_range set, keep `booking_line_group_id.nb_pers`
                    if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
                        $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
                        foreach($age_range_assignments as $assignment) {
                            if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                                $nb_pers = $assignment['qty'];
                                break;
                            }
                        }
                    }
                    // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
                    $nb_repeat = 1;
                    if($line['product_id.product_model_id.has_duration']) {
                        $nb_repeat = $line['product_id.product_model_id.duration'];
                    }
                    elseif($line['booking_line_group_id.is_sojourn']) {
                        if($line['product_id.product_model_id.is_repeatable']) {
                            $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                        }
                    }
                    elseif($line['booking_line_group_id.is_event']) {
                        if($line['product_id.product_model_id.is_repeatable']) {
                            $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                        }
                    }
                    // retrieve quantity to consider
                    $qty = self::_computeLineQty(
                            $line['qty_accounting_method'],
                            $nb_repeat,
                            $nb_pers,
                            $line['product_id.product_model_id.is_repeatable'],
                            $line['is_accomodation'],
                            $line['product_id.product_model_id.capacity']
                        );
                    // fill qty_vars with zeros
                    $qty_vars = array_fill(0, $nb_repeat, 0);
                    // #memo - triggers onupdateQty and onupdateQtyVar
                    $om->update(self::getType(), $lid, ['qty' => $qty, 'qty_vars' => json_encode($qty_vars)]);
                }
                // qty_vars is set and valid
                else {
                    // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
                    $nb_repeat = 1;
                    if($line['product_id.product_model_id.has_duration']) {
                        $nb_repeat = $line['product_id.product_model_id.duration'];
                    }
                    elseif($line['booking_line_group_id.is_sojourn']) {
                        $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                    }
                    elseif($line['booking_line_group_id.is_event']) {
                        $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                    }
                    $diff = $nb_repeat - count($qty_vars);
                    if($diff > 0) {
                        $qty_vars = array_pad($qty_vars, $nb_repeat, 0);
                    }
                    else if($diff < 0) {
                        $qty_vars = array_slice($qty_vars, 0, $nb_repeat);
                    }
                    // #memo - will trigger onupdateQtyVar which will update qty
                    $om->update(self::getType(), $lid, ['qty_vars' => json_encode($qty_vars)]);
                }
            }
        }
    }


    /**
     * Try to assign the price_id according to the current product_id.
     * Resolve the price from the first applicable price list, based on booking_line_group settings and booking center.
     * If found price list is pending, marks the booking as TBC.
     *
     * #memo - _updatePriceId is also called upon change on booking_id.center_id and booking_line_group_id.date_from.
     *
     * @param \equal\orm\ObjectManager $om
     */
    public static function _updatePriceId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLine:_updatePriceId", QN_REPORT_DEBUG);

        /*
            There are 2 situations :

            1) either the booking is not locked by a contract, in which case, we perform a regular lookup for an applicable pricelist
            2) or the booking has a locked contract, then we start by looking for a price amongst existing line targeting the same product (if not found, fallback to regular pricelist search)
        */

        $lines = $om->read(self::getType(), $oids, [
            'booking_line_group_id.date_from',
            'product_id',
            'booking_id',
            'booking_id.is_locked',
            'booking_id.center_id.price_list_category_id',
            'has_manual_unit_price',
            'has_manual_vat_rate'
        ]);

        foreach($lines as $line_id => $line) {
            $found = false;

            /**
             * Locked booking relate to a contract that has been locked : this guarantees that additional services must be billed at the same price
             * than equivalent services subscribed when the contract was established, whatever the current price lists
             */
            if($line['booking_id.is_locked']) {
                trigger_error("ORM::booking is locked", QN_REPORT_DEBUG);
                // search booking line from same booking, targeting the same product
                $booking_lines_ids = $om->search(self::getType(), [['booking_id', '=', $line['booking_id']],  ['product_id', '=', $line['product_id']], ['id', '<>', $line_id]]);
                if($booking_lines_ids > 0 && count($booking_lines_ids)) {
                    $booking_lines = $om->read(self::getType(), $booking_lines_ids, ['product_id', 'price_id', 'unit_price', 'vat_rate']);
                    if($booking_lines > 0 && count($booking_lines)) {
                        $booking_line = reset($booking_lines);
                        $found = true;
                        // #memo - price_id is set for consistency, but since we want to force the same price regardless of the advantages linked to the group, we copy unit_price and vat_rate
                        $om->update(self::getType(), $line_id, ['price_id' => $booking_line['price_id']]);
                        // #memo - this will set the has_manual_unit_price and has_manual_vat_rate to true
                        $om->update(self::getType(), $line_id, ['unit_price' => $booking_line['unit_price'], 'vat_rate' => $booking_line['vat_rate']]);
                        trigger_error("ORM::assigned price from {$booking_line['product_id']}", QN_REPORT_WARNING);
                    }
                }
            }

            /*
                Find the Price List that matches the criteria from the booking (shortest duration first)
            */
            if(!$found) {
                trigger_error("ORM::no price from previous contract", QN_REPORT_DEBUG);
                $is_tbc = false;
                $selected_price_id = 0;

                $product_id = $line['product_id'];

                // 1) use searchPriceId (published price lists)
                $prices = self::searchPriceId($om, [$line_id], $product_id);

                trigger_error("ORM::searchPriceId result:".implode(',', array_values($prices)), QN_REPORT_DEBUG);
                if(isset($prices[$line_id])) {
                    $selected_price_id = $prices[$line_id];
                    trigger_error("ORM::found published price:".$selected_price_id, QN_REPORT_DEBUG);
                }
                // 2) if not found, search for a matching Price within the pending Price Lists
                else {
                    $prices = self::searchPriceIdUnpublished($om, [$line_id], $product_id);
                    if(isset($prices[$line_id])) {
                        $is_tbc = true;
                        $selected_price_id = $prices[$line_id];
                        trigger_error("ORM::found non-published price:".$selected_price_id, QN_REPORT_DEBUG);
                    }
                }

                if($selected_price_id > 0) {
                    // assign found Price to current line
                    $om->update(self::getType(), $line_id, ['price_id' => $selected_price_id]);
                    if($is_tbc) {
                        trigger_error("ORM::setting booking as TBC", QN_REPORT_DEBUG);
                        // found price is TBC: mark booking as to be confirmed
                        $om->update(Booking::getType(), $line['booking_id'], ['is_price_tbc' => true]);
                    }
                    $date = date('Y-m-d', $line['booking_line_group_id.date_from']);
                    trigger_error("ORM::assigned price {$selected_price_id} ({$is_tbc}) for product {$line['product_id']} for date {$date}", QN_REPORT_INFO);
                }
                else {
                    trigger_error("ORM::no price found : force to zero", QN_REPORT_DEBUG);
                    $om->update(self::getType(), $line_id, ['price_id' => null, 'price' => null]);
                    if(!$line['has_manual_unit_price']) {
                        $om->update(self::getType(), $line_id, ['unit_price' => 0]);
                    }
                    if(!$line['has_manual_vat_rate']) {
                        $om->update(self::getType(), $line_id, ['vat_rate' => 0]);
                    }
                    $date = date('Y-m-d', $line['booking_line_group_id.date_from']);
                    trigger_error("ORM::no matching price found for product {$line['product_id']} for date {$date}", QN_REPORT_WARNING);
                }
            }
        }
    }

    /**
     * Search for a Price within the matching published Price Lists of the given lines.
     * If no value is found for a line, the result is not set.
     * #memo This method has the same format and behavior as regular calc_() methods but `price_id` is not a computed field.
     *
     */
    public static function searchPriceId($om, $ids, $product_id) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, [
                'booking_line_group_id.date_from',
                'booking_id.center_id.price_list_category_id',
            ]);

        foreach($lines as $line_id => $line) {
            // search for matching price lists by starting with the one having the shortest duration
            $price_lists_ids = $om->search(
                    \sale\price\PriceList::getType(),
                    [
                        [
                            ['price_list_category_id', '=', $line['booking_id.center_id.price_list_category_id']],
                            ['date_from', '<=', $line['booking_line_group_id.date_from']],
                            ['date_to', '>=', $line['booking_line_group_id.date_from']],
                            ['status', '=', 'published']
                        ]
                    ],
                    ['duration' => 'asc']
                );

            if($price_lists_ids > 0 && count($price_lists_ids)) {
                foreach($price_lists_ids as $price_list_id) {
                    $prices_ids = $om->search(\sale\price\Price::getType(), [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $product_id] ]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        $result[$line_id] = reset($prices_ids);
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Search for a Price within the matching unpublished/pending Price Lists of the given lines.
     * If no value is found for a line, the result is not set.
     * #memo This method has the same format and behavior as regular calc_() methods but `price_id` is not a computed field.
     *
     */
    public static function searchPriceIdUnpublished($om, $ids, $product_id) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, [
                'booking_line_group_id.date_from',
                'booking_id.center_id.price_list_category_id',
            ]);

        foreach($lines as $line_id => $line) {
            // search for matching price lists by starting with the one having the shortest duration
            $price_lists_ids = $om->search(
                    \sale\price\PriceList::getType(),
                    [
                        [
                            ['price_list_category_id', '=', $line['booking_id.center_id.price_list_category_id']],
                            ['date_from', '<=', $line['booking_line_group_id.date_from']],
                            ['date_to', '>=', $line['booking_line_group_id.date_from']],
                            ['status', '=', 'pending']
                        ]
                    ],
                    ['duration' => 'asc']
                );

            if($price_lists_ids > 0 && count($price_lists_ids)) {
                foreach($price_lists_ids as $price_list_id) {
                    $prices_ids = $om->search(\sale\price\Price::getType(), [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $product_id] ]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        $result[$line_id] = reset($prices_ids);
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Retrieve quantity to consider for a line, according to context.
     *
     * @param string    $method             Accounting method, according to qty_accounting_method of the related Product Model ('accomodation', 'person' or 'unit').
     * @param integer   $nb_repeat          Number of times the product must be repeated.
     * @param integer   $nb_pers            Number of persons the line refers to (from parent group).
     * @param boolean   $is_repeatable      Flag marking the line as to be repeat for the duration relating to the parent group.
     * @param boolean   $is_accommodation   Flag marking the line as an accommodation.
     * @param boolean   $capacity           Capacity of the product model the line refers to.
     */
    public static function _computeLineQty($method, $nb_repeat, $nb_pers, $is_repeatable, $is_accommodation, $capacity) {
        // default quantity (duration of the group or own quantity or method = 'unit')
        $qty = $nb_repeat;
        // service is accounted by accommodation
        if($method == 'accomodation') {
            $qty = $nb_repeat;
            if($is_repeatable) {
                // lines having a product 'by accommodation' have a qty assigned to the computed duration of the group (events are accounted in days, and sojourns in nights)
                if($capacity < $nb_pers && $capacity > 0) {
                    $qty = $nb_repeat * ceil($nb_pers / $capacity);
                }
                else {
                    $qty = $nb_repeat;
                }
            }
            else {
                if($capacity < $nb_pers && $capacity > 0) {
                    $qty = ceil($nb_pers / $capacity);
                }
                else {
                    $qty = 1;
                }
            }
        }
        // service is accounted by person
        elseif($method == 'person') {
            if($is_repeatable) {
                if($is_accommodation && $capacity > 0) {
                    // either 1 accomodation, or as many accommodations as necessary to host the number of persons
                    $qty = $nb_repeat * ceil($nb_pers / $capacity);
                }
                else {
                    // other repeatable services (meeting rooms, meals, animations, ...)
                    $qty = $nb_pers * $nb_repeat;
                }
            }
            else {
                $qty = $nb_pers;
            }
        }
        return $qty;
    }

    /**
     * This method is used to remove all SPM relating to the product model if parent group does not hold a similar product anymore.
     *
     * @param  \equal\orm\ObjectManager     $om        ObjectManager instance.
     * @param  array                        $ids       List of objects identifiers.
     * @return void
     */
    public static function _updateSPM($om, $ids, $values=[], $lang='en') {
        $lines = $om->read(self::getType(), $ids, ['booking_line_group_id']);
        if($lines > 0 && count($lines)) {
            $groups_ids = array_map(function($a) {return $a['booking_line_group_id'];}, $lines);
            $om->callonce(BookingLineGroup::getType(), '_updateSPM', $groups_ids, $values);
        }
    }

     /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     * This hook is used to remove all SPM relating to the product model if parent group does not hold a similar product anymore.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $oids) {
        $om->callonce(self::getType(), '_updateSPM', $oids, ['deleted' => $oids]);
    }

}