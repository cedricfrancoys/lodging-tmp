<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;


use lodging\identity\Center;

class BookingLineGroup extends \sale\booking\BookingLineGroup {

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
                'default'           => false,
                'onupdate'          => 'onupdateIsSojourn'
            ],

            'is_event' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relate to an event occurring on a single day?',
                'default'           => false,
                'onupdate'          => 'onupdateIsEvent'
            ],

            'group_type' => [
                'type'              => 'string',
                'description'       => 'Type of lines group.',
                'help'              => 'This value replaces is_sojourn and is_events and handles the synchronization when necessary.',
                'default'           => 'simple',
                'selection'         => [
                    'simple',
                    'sojourn',
                    'event',
                    'camp'
                ],
                'onupdate'          => 'onupdateGroupType'
            ],

            'has_consumptions' => [
                'type'              => 'boolean',
                'description'       => 'Have consumptions been created for extra group?',
                'help'              => 'Once an extra services group has consumptions, it can no longer be updated.',
                'default'           => false,
                'visible'           => ['is_extra', '=', true]
            ],

            'has_locked_rental_units' => [
                'type'              => 'boolean',
                'description'       => 'Can the rental units assingments be changed?',
                'default'           => false
            ],

            'has_pack' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relates to a pack?',
                'default'           => false,
                'onupdate'          => 'onupdateHasPack'
            ],

            'pack_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => 'Pack (product) the group relates to, if any.',
                'visible'           => ['has_pack', '=', true],
                'onupdate'          => 'onupdatePackId'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price (retrieved by price list) the pack relates to.',
                'visible'           => ['has_pack', '=', true],
                'onupdate'          => 'onupdatePriceId'
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'VAT rate that applies to this group, when relating to a pack_id.',
                'function'          => 'calcVatRate',
                'store'             => true,
                'visible'           => ['has_pack', '=', true],
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Tax-excluded unit price (with automated discounts applied).',
                'function'          => 'calcUnitPrice',
                'store'             => true,
                'visible'           => ['has_pack', '=', true]
            ],

            'is_autosale' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relate to autosale products?',
                'default'           => false
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => 'Are modifications disabled for the group?',
                'default'           => false,
                'visible'           => ['has_pack', '=', true],
                'onupdate'          => 'onupdateIsLocked'
            ],

            'qty' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Quantity of product items for the group (pack).',
                'function'          => 'calcQty',
                'visible'           => ['has_pack', '=', true]
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Day of arrival.",
                'onupdate'          => 'onupdateDateFrom',
                'default'           => time()
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Day of departure.",
                'default'           => time(),
                'onupdate'          => 'onupdateDateTo'
            ],

            'time_from' => [
                'type'              => 'time',
                'description'       => "Checkin time on the day of arrival.",
                'default'           => 14 * 3600,
                'onupdate'          => 'onupdateTimeFrom'
            ],

            'time_to' => [
                'type'              => 'time',
                'description'       => "Checkout time on the day of departure.",
                'default'           => 10 * 3600,
                'onupdate'          => 'onupdateTimeTo'
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\SojournType',
                'description'       => 'The kind of sojourn the group is about.',
                'default'           => 1,       // 'GA'
                'onupdate'          => 'onupdateSojournTypeId',
                'visible'           => ['is_sojourn', '=', true]
            ],

            'nb_pers' => [
                'type'              => 'integer',
                'description'       => 'Amount of persons this group is about.',
                'default'           => 1,
                'onupdate'          => 'onupdateNbPers'
            ],

            'nb_children' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Amount of children this group is about.',
                'function'          => 'calcNbChildren',
                'store'             => true
            ],

            /* a booking can be split into several groups on which distinct rate classes apply, by default the rate_class of the customer is used */
            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The fare class that applies to the group.",
                'default'           => 4,                       // default to 'general public'
                'onupdate'          => 'onupdateRateClassId'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLine',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines that belong to the group.',
                'ondetach'          => 'delete',
                'order'             => 'order',
                'onupdate'          => 'onupdateBookingLinesIds'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => Consumption::getType(),
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Consumptions relating to the group.',
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Price adapters that apply to all lines of the group (based on group settings).'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Booking::getType(),
                'description'       => 'Booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'         // delete group when parent booking is deleted
            ],

            // we mean rental_units_ids (for rental units assignment)
            // #todo - deprecate
            'accomodations_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => BookingLine::getType(),
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines relating to accomodations.',
                'ondetach'          => 'delete',
                'domain'            => ['is_rental_unit', '=', true]
            ],

            'sojourn_product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\SojournProductModel',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The product models groups assigned to the sojourn (from lines).",
                'ondetach'          => 'delete'
            ],

            'rental_unit_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\SojournProductModelRentalUnitAssignement',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The rental units assigned to the group (from lines).",
                'ondetach'          => 'delete'
            ],

            'age_range_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroupAgeRangeAssignment',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Age range assignments defined for the group.',
                'ondetach'          => 'ondetachAgeRange'
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price for all lines (computed).',
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Final tax-included price for all lines (computed).',
                'function'          => 'calcPrice',
                'store'             => true
            ]

        ];
    }

    /**
     *
     */
    public static function oncreate($om, $oids, $values, $lang) {

    }

    public static function calcVatRate($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $oids, ['price_id.accounting_rule_id.vat_rule_id.rate']);
        foreach($lines as $oid => $odata) {
            $result[$oid] = floatval($odata['price_id.accounting_rule_id.vat_rule_id.rate']);
        }
        return $result;
    }

    public static function calcQty($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::getType(), $oids, [
                'has_pack',
                'is_locked',
                'is_sojourn',
                'is_event',
                'pack_id.product_model_id.qty_accounting_method',
                'pack_id.product_model_id.has_duration',
                'pack_id.product_model_id.duration',
                'nb_pers',
                'nb_nights'
            ]);
        foreach($groups as $gid => $group) {
            $result[$gid] = 1;
            // #memo - locked groups have a qty of 1
            if($group['has_pack'] && !$group['is_locked']) {
                // find the repetition factor
                $nb_repeat = 1;
                if($group['pack_id.product_model_id.has_duration']) {
                    $nb_repeat = $group['pack_id.product_model_id.duration'];
                }
                elseif($group['is_sojourn']) {
                    $nb_repeat = $group['nb_nights'];
                }
                elseif($group['is_event']) {
                    $nb_repeat = $group['nb_nights'] + 1;
                }
                // apply accounting method
                if(in_array($group['pack_id.product_model_id.qty_accounting_method'], ['unit', 'accomodation'])) {
                    $qty = $nb_repeat;
                }
                elseif($group['pack_id.product_model_id.qty_accounting_method'] == 'person') {
                    $qty =  $nb_repeat * $group['nb_pers'];
                }
                $result[$gid] = intval($qty);
            }
        }
        return $result;
    }

    /**
     * Compute the VAT excl. unit price of the group, with automated discounts applied.
     *
     */
    public static function calcUnitPrice($om, $oids, $lang) {
        $result = [];

        $groups = $om->read(self::getType(), $oids, ['price_id.price']);

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {

                $price_adapters_ids = $om->search(BookingPriceAdapter::getType(), [
                    ['booking_line_group_id', '=', $gid],
                    ['booking_line_id','=', 0],
                    ['is_manual_discount', '=', false]
                ]);

                $disc_value = 0.0;
                $disc_percent = 0.0;

                if($price_adapters_ids > 0) {
                    $adapters = $om->read(BookingPriceAdapter::getType(), $price_adapters_ids, ['type', 'value', 'discount_id.discount_list_id.rate_max']);

                    if($adapters > 0) {
                        foreach($adapters as $aid => $adata) {
                            if($adata['type'] == 'amount') {
                                $disc_value += $adata['value'];
                            }
                            else if($adata['type'] == 'percent') {
                                if($adata['discount_id.discount_list_id.rate_max'] && ($disc_percent + $adata['value']) > $adata['discount_id.discount_list_id.rate_max']) {
                                    $disc_percent = $adata['discount_id.discount_list_id.rate_max'];
                                }
                                else {
                                    $disc_percent += $adata['value'];
                                }
                            }
                        }
                    }
                }

                $result[$gid] = round(($group['price_id.price'] * (1-$disc_percent)) - $disc_value, 2);
            }
        }
        return $result;
    }

    /**
     * Compute the VAT incl. total price of the group (pack), with manual and automated discounts applied.
     *
     */
    public static function calcPrice($om, $oids, $lang) {
        $result = [];

        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids', 'total', 'vat_rate', 'is_locked', 'has_pack']);

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $result[$gid] = 0.0;

                // if the group relates to a pack and the product_model targeted by the pack has its own Price, then this is the one to return
                if($group['has_pack'] && $group['is_locked']) {
                    $result[$gid] = round($group['total'] * (1 + $group['vat_rate']), 2);
                }
                // otherwise, price is the sum of bookingLines prices
                else {
                    $lines = $om->read('lodging\sale\booking\BookingLine', $group['booking_lines_ids'], ['price']);
                    if($lines > 0 && count($lines)) {
                        foreach($lines as $line) {
                            $result[$gid] += $line['price'];
                        }
                        $result[$gid] = round($result[$gid], 2);
                    }
                }
            }
        }
        return $result;
    }

    public static function calcTotal($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'booking_lines_ids', 'is_locked', 'has_pack', 'unit_price', 'qty']);
        $bookings_ids = [];

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $result[$gid] = 0.0;

                $bookings_ids[] = $group['booking_id'];
                // if the group relates to a pack and the product_model targeted by the pack has its own Price, then this is the one to return
                if($group['has_pack'] && $group['is_locked']) {
                    $result[$gid] = $group['unit_price'] * $group['qty'];
                }
                // otherwise, price is the sum of bookingLines totals
                else {
                    $lines = $om->read('lodging\sale\booking\BookingLine', $group['booking_lines_ids'], ['total']);
                    if($lines > 0 && count($lines)) {
                        foreach($lines as $line) {
                            $result[$gid] += $line['total'];
                        }
                        $result[$gid] = round($result[$gid], 4);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param \equal\orm\ObjectManager  $om
     */
    public static function onupdateIsSojourn($om, $oids, $values, $lang) {
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'booking_lines_ids', 'nb_pers', 'is_sojourn', 'age_range_assignments_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $gid => $group) {
                if($group['is_sojourn']) {
                    // remove any previously set assignments
                    $om->delete(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], true);

                    if($group['is_sojourn']) {
                        // create default age_range assignment
                        $assignment = [
                            'age_range_id'          => 1,                       // adults
                            'booking_line_group_id' => $gid,
                            'booking_id'            => $group['booking_id'],
                            'qty'                   => $group['nb_pers']
                        ];
                        $om->create(BookingLineGroupAgeRangeAssignment::getType(), $assignment, $lang);
                    }
                }
                // re-compute bookinglines quantities
                $om->update(BookingLine::getType(), $group['booking_lines_ids'], ['qty_vars' => null], $lang);
                $om->callonce(BookingLine::getType(), '_updateQty', $group['booking_lines_ids'], [], $lang);
            }
            // update auto sales
            $om->callonce(self::getType(), '_updateAutosaleProducts', $oids, [], $lang);
        }
    }

    public static function onupdateGroupType($om, $ids, $values, $lang) {
        $groups = $om->read(self::getType(), $ids, ['group_type'], $lang);
        if($groups > 0) {
            foreach($groups as $id => $group) {
                if($group['group_type'] == 'simple') {
                    $om->update(self::getType(), $id, ['is_sojourn' => false]);
                    $om->update(self::getType(), $id, ['is_event' => false]);
                }
                elseif($group['group_type'] == 'sojourn' || $group['group_type'] == 'camp') {
                    $om->update(self::getType(), $id, ['is_sojourn' => true]);
                    $om->update(self::getType(), $id, ['is_event' => false]);
                }
                elseif($group['group_type'] == 'event') {
                    $om->update(self::getType(), $id, ['is_sojourn' => false]);
                    $om->update(self::getType(), $id, ['is_event' => true]);
                }
            }
        }
    }

    /**
     * @param \equal\orm\ObjectManager  $om
     */
    public static function onupdateIsEvent($om, $oids, $values, $lang) {
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $gid => $group) {
                // re-compute bookinglines quantities
                $om->update(BookingLine::getType(), $group['booking_lines_ids'], ['qty_vars' => null], $lang);
                $om->callonce(BookingLine::getType(), '_updateQty', $group['booking_lines_ids'], [], $lang);
            }
            // update auto sales
            $om->callonce(self::getType(), '_updateAutosaleProducts', $oids, [], $lang);
        }
    }

    public static function onupdateHasPack($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeHasPack", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, ['has_pack', 'booking_lines_ids']);
        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                if(!$group['has_pack']) {
                    // remove existing booking_lines
                    $om->update(self::getType(), $gid, ['booking_lines_ids' => array_map(function($a) { return "-$a";}, $group['booking_lines_ids'])]);
                    // reset lock and pack_id
                    $om->update(self::getType(), $gid, ['is_locked' => false, 'pack_id' => null ]);
                }
            }
        }
    }

    public static function onupdateIsLocked($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeIsLocked", QN_REPORT_DEBUG);
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->callonce(self::getType(), '_updatePriceId', $oids, [], $lang);
    }

    public static function onupdatePriceId($om, $oids, $values, $lang) {
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->update(self::getType(), $oids, ['vat_rate' => null, 'unit_price' => null]);
    }

    /**
     * Handler called after pack_id has changed (lodging\sale\catalog\Product).
     * Updates is_locked field according to selected pack (pack_id).
     * (is_locked can be manually set by the user afterward)
     *
     * Since this method is called, we assume that current group has 'has_pack' set to true,
     * and that pack_id relates to a product that is a pack.
     */
    public static function onupdatePackId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangePackId", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, [
            'name',
            'booking_id',
            'date_from',
            'nb_pers',
            'is_locked',
            'booking_lines_ids',
            'age_range_assignments_ids',
            'pack_id',
            'pack_id.has_age_range',
            'pack_id.age_range_id',
            'pack_id.product_model_id.name',
            'pack_id.product_model_id.qty_accounting_method',
            'pack_id.product_model_id.has_duration',
            'pack_id.product_model_id.duration',
            'pack_id.product_model_id.capacity',
            'pack_id.product_model_id.booking_type_id'
        ]);

        // pass-1 : update age ranges for packs with a specific age range

        /*
        // #memo - BookingLineGroupAgeRangeAssignment and BookingLineGroup.pack_id.age_range_id are distinct : it is possible to have several age ranges but consider another age range for the products of the pack
        foreach($groups as $gid => $group) {
            if($group['pack_id.has_age_range']) {
                // remove any previously set assignments
                $om->delete(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], true);
                // create default age_range assignment
                $assignment = [
                    'age_range_id'          => $group['pack_id.age_range_id'],
                    'booking_line_group_id' => $gid,
                    'booking_id'            => $group['booking_id'],
                    'qty'                   => $group['nb_pers']
                ];
                $om->create(BookingLineGroupAgeRangeAssignment::getType(), $assignment, $lang);
            }
        }
        */

        // (re)generate booking lines
        $om->callonce(self::getType(), '_updatePack', $oids, [], $lang);

        // pass-2 : update groups and related bookings, if necessary
        foreach($groups as $gid => $group) {
            // if model of chosen product has a non-generic booking type, update the booking of the group accordingly
            if(isset($group['pack_id.product_model_id.booking_type_id']) && $group['pack_id.product_model_id.booking_type_id'] != 1) {
                $om->update(Booking::getType(), $group['booking_id'], ['type_id' => $group['pack_id.product_model_id.booking_type_id']]);
            }

            $updated_fields = ['vat_rate' => null];

            // assign the name of the selected pack as group name
            if($group['pack_id'] && isset($group['pack_id.product_model_id.name'])) {
                $updated_fields['name'] = $group['pack_id.product_model_id.name'];
            }

            // if targeted product model has its own duration, date_to is updated accordingly
            if($group['pack_id.product_model_id.has_duration']) {
                $updated_fields['date_to'] = $group['date_from'] + ($group['pack_id.product_model_id.duration'] * 60*60*24);
                // will update price_adapters, nb_nights
            }

            // always update nb_pers
            // to make sure to trigger self::updatePriceAdapters and BookingLine::_updateQty
            $updated_fields['nb_pers'] = $group['nb_pers'];
            if($group['pack_id.product_model_id.qty_accounting_method'] == 'accomodation' && $group['pack_id.product_model_id.capacity'] > $group['nb_pers']) {
                $updated_fields['nb_pers'] = $group['pack_id.product_model_id.capacity'];
            }

            $om->update(self::getType(), $gid, $updated_fields, $lang);
        }

        // invalidate prices
        // #memo - this must be done after all other processing and should not alter price_id assignments (but only reset computed fields)
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);

    }

    public static function onupdateDateFrom($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeDateFrom", QN_REPORT_DEBUG);
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);

        $om->update(self::getType(), $oids, ['nb_nights' => null ]);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
        $om->callonce(self::getType(), '_updateAutosaleProducts', $oids, [], $lang);

        // update bookinglines
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event', 'has_pack', 'nb_nights', 'booking_lines_ids']);
        if($groups > 0 && count($groups)) {
            foreach($groups as $group) {
                // notify booking lines that price_id has to be updated
                $om->callonce('lodging\sale\booking\BookingLine', '_updatePriceId', $group['booking_lines_ids'], [], $lang);
                // recompute bookinglines quantities
                $om->callonce('lodging\sale\booking\BookingLine', '_updateQty', $group['booking_lines_ids'], [], $lang);
                if($group['is_sojourn']  || $group['is_event']) {
                    // force parent booking to recompute date_from
                    $om->update('lodging\sale\booking\Booking', $group['booking_id'], ['date_from' => null]);
                }
            }
        }
    }

    public static function onupdateDateTo($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeDateTo", QN_REPORT_DEBUG);
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);

        $om->update(self::getType(), $oids, ['nb_nights' => null ]);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
        $om->callonce(self::getType(), '_updateAutosaleProducts', $oids, [], $lang);

        // update bookinglines
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event', 'has_pack', 'nb_nights', 'nb_pers', 'booking_lines_ids']);
        if($groups > 0) {
            foreach($groups as $group) {
                // re-compute bookinglines quantities
                $om->callonce('lodging\sale\booking\BookingLine', '_updateQty', $group['booking_lines_ids'], [], $lang);
                if($group['is_sojourn'] || $group['is_event']) {
                    // force parent booking to recompute date_from
                    $om->update('lodging\sale\booking\Booking', $group['booking_id'], ['date_to' => null]);
                }
            }
        }
    }

    public static function onupdateTimeFrom($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onupdateTimeTo", QN_REPORT_DEBUG);

        // update parent booking
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event'], $lang);
        if($groups > 0) {
            foreach($groups as $group) {
                if($group['is_sojourn'] || $group['is_event']) {
                    // force parent booking to recompute time_from
                    $om->update('lodging\sale\booking\Booking', $group['booking_id'], ['time_from' => null]);
                }
            }
        }
    }

    public static function onupdateTimeTo($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onupdateTimeTo", QN_REPORT_DEBUG);

        // update parent booking
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event'], $lang);
        if($groups > 0) {
            foreach($groups as $group) {
                if($group['is_sojourn'] || $group['is_event']) {
                    // force parent booking to recompute time_to
                    $om->update('lodging\sale\booking\Booking', $group['booking_id'], ['time_to' => null]);
                }
            }
        }
    }

    public static function onupdateBookingLinesIds($om, $oids, $values, $lang) {
        // recompute sojourn prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        // reset rental units assignments
        $om->callonce(self::getType(), 'createRentalUnitsAssignments', $oids, [], $lang);
        // force parent booking to recompute times and prices
        $groups = $om->read(self::getType(), $oids, ['booking_id'], $lang);
        if($groups > 0) {
            $bookings_ids = array_map(function($a) {return $a['booking_id'];}, $groups);
            $om->update(Booking::getType(), $bookings_ids, ['time_from' => null, 'time_to' => null, 'total' => null, 'price' => null]);
        }
    }

    public static function onupdateRateClassId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeRateClassId", QN_REPORT_DEBUG);
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'rate_class_id.name'], $lang);
        // #todo - add support for assigning an optional booking_type_id to each rate_class
        foreach($groups as $gid => $group) {
            // if model of chosen product has a non-generic booking type, update the booking of the group accordingly
            if($group['rate_class_id.name'] == 'T5' || $group['rate_class_id.name'] == 'T7') {
                $om->update(Booking::getType(), $group['booking_id'], ['type_id' => 4]);
            }
        }
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
    }

    public static function onupdateSojournTypeId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeSojournTypeId", QN_REPORT_DEBUG);
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
    }

    public static function onupdateNbPers($om, $ids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:onchangeNbPers", QN_REPORT_DEBUG);

        // 0) discard non is_extra groups whose booking is in a non-modifiable status
        // #memo - this is necessary because nb_pers can be changed for GG at any time
        $groups = $om->read(self::getType(), $ids, [
                'booking_id.status',
                'is_extra'
            ]);
        $groups_ids_to_remove = [];
        foreach($groups as $gid => $group) {
            if(!$group['is_extra'] && $group['booking_id.status'] != 'quote') {
                $groups_ids_to_remove[] = $gid;
            }
        }
        $ids = array_diff($ids, $groups_ids_to_remove);

        // 1) invalidate prices
        $om->callonce(self::getType(), '_resetPrices', $ids, [], $lang);

        // 2) invalidate nb children
        $om->callonce(self::getType(), '_resetNbChildren', $ids, [], $lang);

        $groups = $om->read(self::getType(), $ids, [
                'booking_id',
                'nb_pers',
                'booking_lines_ids',
                'is_sojourn',
                'age_range_assignments_ids',
                'rate_class_id.name'
            ]);

        // 3) reset parent bookings nb_pers and update booking type
        if($groups > 0) {
            $bookings_ids = array_map(function($a) {return $a['booking_id'];}, $groups);
            $om->update(Booking::getType(), $bookings_ids, ['nb_pers' => null]);
            $om->callonce(Booking::getType(), '_updateAutosaleProducts', $bookings_ids, [], $lang);

            foreach($groups as $group) {
                if($group['is_sojourn'] && $group['rate_class_id.name'] == 'T4') {
                    if($group['nb_pers'] >= 10) {
                        // booking type 'TPG' (tout public groupe) is for booking with 10 pers. or more
                        $om->update(Booking::getType(), $group['booking_id'], ['type_id' => 6]);
                    }
                    else {
                        // booking type 'TP' (tout public) is for booking with less than 10 pers.
                        $om->update(Booking::getType(), $group['booking_id'], ['type_id' => 1]);
                    }
                }
            }
        }

        // 4) update agerange assignments (for single assignment)
        if($groups > 0) {
            $booking_lines_ids = [];
            foreach($groups as $group) {
                if($group['is_sojourn'] && count($group['age_range_assignments_ids']) == 1) {
                    $age_range_assignment_id = reset($group['age_range_assignments_ids']);
                    $om->update(BookingLineGroupAgeRangeAssignment::getType(), $age_range_assignment_id, ['qty' => $group['nb_pers']]);
                }
                $booking_lines_ids = array_merge($group['booking_lines_ids']);
                // trigger sibling groups nb_pers update (this is necessary since the nb_pers is based on the booking total participants)
            }
            // re-compute bookinglines quantities
            $om->update(BookingLine::getType(), $booking_lines_ids, ['qty_vars' => null], $lang);
            $om->callonce(BookingLine::getType(), '_updateQty', $booking_lines_ids, [], $lang);
        }

        // 5) update dependencies
        $om->callonce(self::getType(), 'createRentalUnitsAssignments', $ids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $ids, [], $lang);
        $om->callonce(self::getType(), '_updateAutosaleProducts', $ids, [], $lang);
        $om->callonce(self::getType(), '_updateMealPreferences', $ids, [], $lang);
    }

    /**
     * Reset the quantity of children for calculation when needed
     *
     * @param \equal\orm\ObjectManager  $om
     * @param int[]                     $oids
     * @param array                     $values
     * @param string                    $lang
     * @return void
     */
    public static function _resetNbChildren($om, $oids, $values, $lang) {
        $om->update(__CLASS__, $oids, ['nb_children' => null]);
    }

    /**
     * Calculate the quantity of children in the groups
     *
     * @param \equal\orm\ObjectManager  $om
     * @param int[]                     $oids
     * @param string                    $lang
     * @return array
     */
    public static function calcNbChildren($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::getType(), $oids, ['age_range_assignments_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $gid => $group) {
                $children_qty = 0;
                $assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['qty', 'age_range_id.age_from'], $lang);
                foreach($assignments as $assignment) {
                    if($assignment['age_range_id.age_from'] >= 3 && $assignment['age_range_id.age_from'] < 18) {
                        $children_qty += $assignment['qty'];
                    }
                }

                $result[$gid] = $children_qty;
            }
        }

        return $result;
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
        if(isset($values['booking_id'])) {
            $bookings = $om->read(Booking::getType(), $values['booking_id'], ['status'], $lang);

            if($bookings) {
                $booking = reset($bookings);

                if( in_array($booking['status'], ['invoiced', 'debit_balance', 'credit_balance', 'balanced'])
                    || ($booking['status'] != 'quote' && (!isset($values['is_extra']) || !$values['is_extra'])) ) {
                    return ['status' => ['non_editable' => 'Non-extra service lines cannot be changed for non-quote bookings.']];
                }
            }
        }

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
        // list of fields that can be updated at any time
        $allowed_fields = ['is_extra', 'has_schedulable_services', 'has_consumptions', 'has_locked_rental_units'];

        if(count(array_diff(array_keys($values), $allowed_fields))) {

            $groups = $om->read(self::getType(), $oids, ['booking_id.status', 'is_extra', 'has_schedulable_services', 'has_consumptions', 'is_sojourn', 'sojourn_type_id', 'age_range_assignments_ids', 'sojourn_product_models_ids'], $lang);

            if($groups > 0) {
                foreach($groups as $group) {
                    // for GG the number of persons does not impact the booking (GG only has pricing by_accomodation), so we allow change of nb_pers at any time
                    if($group['is_sojourn'] && $group['sojourn_type_id'] == 2) {
                        if(count($values) == 1 && isset($values['nb_pers'])) {
                            continue;
                        }
                    }
                    if($group['is_extra']) {
                        if(!in_array($group['booking_id.status'], ['confirmed', 'validated', 'checkedin', 'checkedout'])) {
                            return ['status' => ['non_editable' => 'Extra services can only be changed after confirmation and before invoicing.']];
                        }
                        if($group['has_schedulable_services'] && $group['has_consumptions']) {
                            return ['status' => ['non_editable' => 'Extra services groups with schedulable services cannot be changed once consumptions have been created.']];
                        }
                    }
                    else {
                        if($group['booking_id.status'] != 'quote') {
                            return ['status' => ['non_editable' => 'Non-extra services can only be changed for quote bookings.']];
                        }
                    }
                    if(isset($values['nb_pers']) && count($group['age_range_assignments_ids']) > 1 ) {
                        $assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['qty'], $lang);
                        $qty = array_reduce($assignments, function($c, $a) { return $c+$a['qty']; }, 0);
                        if($values['nb_pers'] != $qty) {
                            return ['nb_pers' => ['count_mismatch' => 'Number of persons does not match the age ranges.']];
                        }
                    }
                    if(isset($values['has_locked_rental_units']) && $values['has_locked_rental_units']) {
                        if(!count($group['sojourn_product_models_ids'])) {
                            return ['has_locked_rental_units' => ['invalid_status' => 'Cannot lock an empty assignment.']];
                        }
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
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return boolean  Returns true if the object can be deleted, or false otherwise.
     */
    public static function candelete($om, $oids) {
        $groups = $om->read(self::getType(), $oids, ['booking_id.status', 'is_extra']);

        if($groups > 0) {
            foreach($groups as $group) {
                if($group['is_extra']) {
                    if(!in_array($group['booking_id.status'], ['quote', 'confirmed', 'validated', 'checkedin', 'checkedout'])) {
                        return ['status' => ['non_editable' => 'Extra services can only be deleted after confirmation and before invoicing.']];
                    }
                }
                else {
                    // #memo - booking might have been deleted (this is triggered by the Booking::onafterdelete callback)
                    if($group['booking_id.status'] && $group['booking_id.status'] != 'quote') {
                        return ['status' => ['non_editable' => 'Non-extra services can only be deleted for quote bookings.']];
                    }
                }
            }
        }

        return parent::candelete($om, $oids);
    }

    /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $oids) {
        // trigger an update of parent booking nb_pers + sibling groups prices adapters
        $groups = $om->read(self::getType(), $oids, ['booking_id']);
        $bookings_ids = array_map(function($a) {return $a['booking_id'];}, $groups);
        $om->update(Booking::getType(), $bookings_ids, ['nb_pers' => null]);
        return parent::ondelete($om, $oids);
    }

    /**
     * Hook invoked after object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $orm       ObjectManager instance.
     * @param  array                        $ids       List of objects identifiers.
     * @return void
     */
    public static function onafterdelete($orm, $ids) {
        // #memo - we do this to handle case where auto products are re-created during the delete cycle
        $lines_ids = $orm->search(BookingLine::getType(), ['booking_line_group_id', 'in', $ids]);
        $orm->delete(BookingLine::getType(), $lines_ids, true);
    }

    public static function ondetachAgeRange($om, $oids, $detached_ids, $lang) {

        // retrieve age ranges being removed
        $age_range_ids = [];
        $assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $detached_ids, ['age_range_id'], $lang);
        if($assignments > 0) {
            $age_range_ids = array_map(function($a) {return $a['age_range_id'];}, $assignments);
        }

        // remove lines with a product_id referring to the removed age ranges
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids'], $lang);
        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['product_id.has_age_range', 'product_id.age_range_id'], $lang);
                $lines_ids_to_remove = [];
                if($lines > 0 && count($lines)) {
                    foreach($lines as $lid => $line) {
                        if($line['product_id.has_age_range']) {
                            if(in_array($line['product_id.age_range_id'], $age_range_ids)) {
                                $lines_ids_to_remove[] = -$lid;
                            }
                        }
                    }
                    // will trigger onupdateBookingLinesIds
                    $om->update(self::getType(), $gid, ['booking_lines_ids' => $lines_ids_to_remove], $lang);
                }
            }
        }

        // actually remove the age ranges
        $om->remove(BookingLineGroupAgeRangeAssignment::getType(), $detached_ids, true);
    }

    /**
     * Create Price adapters according to group settings.
     * Price adapters are applied only on meal and accommodation products.
     *
     */
    public static function updatePriceAdapters($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:updatePriceAdapters (".implode(',', $oids).")", QN_REPORT_DEBUG);
        /*
            Remove all previous price adapters that were automatically created
        */
        $price_adapters_ids = $om->search('lodging\sale\booking\BookingPriceAdapter', [ ['booking_line_group_id', 'in', $oids], ['is_manual_discount','=', false]]);

        $om->delete('lodging\sale\booking\BookingPriceAdapter', $price_adapters_ids, true);

        $line_groups = $om->read(self::getType(), $oids, [
                'has_pack',
                'pack_id.allow_price_adaptation',
                'rate_class_id',
                'sojourn_type_id',
                'sojourn_type_id.season_category_id',
                'date_from',
                'date_to',
                'nb_pers',
                'nb_children',
                'nb_nights',
                'booking_id',
                'is_locked',
                'booking_lines_ids',
                'booking_id.nb_pers',
                'booking_id.customer_id',
                'booking_id.center_id.season_category_id',
                'booking_id.center_id.discount_list_category_id',
                'booking_id.center_office_id'
            ]);

        foreach($line_groups as $group_id => $group) {

            if($group['has_pack']) {
                if(!$group['pack_id.allow_price_adaptation']) {
                    // skip group if it relates to a product model that prohibits price adaptation
                    continue;
                }
            }

            /*
                Read required preferences from the Center Office
            */
            $freebies_manual_assignment = false;
            $offices_preferences = $om->read(\lodging\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['freebies_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $freebies_manual_assignment = (bool) $prefs['freebies_manual_assignment'];
            }

            /*
                Find the first Discount List that matches the booking dates
            */

            // the discount list category to use is the one defined for the center, unless it is ('GA' or 'GG') AND sojourn_type <> category.name
            $discount_category_id = $group['booking_id.center_id.discount_list_category_id'];

            if(in_array($discount_category_id, [1 /*GA*/, 2 /*GG*/]) && $discount_category_id != $group['sojourn_type_id']) {
                $discount_category_id = $group['sojourn_type_id'];
            }

            $discount_lists_ids = $om->search('lodging\sale\discount\DiscountList', [
                ['rate_class_id', '=', $group['rate_class_id']],
                ['discount_list_category_id', '=', $discount_category_id],
                ['valid_from', '<=', $group['date_from']],
                ['valid_until', '>=', $group['date_from']]
            ]);

            $discount_lists = $om->read('lodging\sale\discount\DiscountList', $discount_lists_ids, ['id', 'discounts_ids', 'rate_min', 'rate_max']);
            $discount_list_id = 0;
            $discount_list = null;
            if($discount_lists > 0 && count($discount_lists)) {
                // use first match (there should always be only one or zero)
                $discount_list = array_pop($discount_lists);
                $discount_list_id = $discount_list['id'];
                trigger_error("ORM:: match with discount List {$discount_list_id}", QN_REPORT_DEBUG);
            }
            else {
                trigger_error("ORM:: no discount List found", QN_REPORT_DEBUG);
            }

            /*
                Search for matching Discounts within the found Discount List
            */
            if($discount_list_id) {
                $count_booking_24 = $om->call(self::getType(), 'calcCountBooking24', $group['booking_id.customer_id'], $group['date_from']);

                $operands = [
                    'count_booking_24'  => $count_booking_24,     // qty of customer bookings from 2 years ago to present
                    'duration'          => $group['nb_nights'],   // duration in nights
                    'nb_pers'           => $group['nb_pers'],     // total number of participants
                    'nb_children'       => $group['nb_children'], // number of children amongst participants
                    'nb_adults'         => $group['nb_pers'] - $group['nb_children']  // number of adults amongst participants
                ];

                $date = $group['date_from'];

                /*
                    Pick up the first season period that matches the year and the season category of the center
                */
                $cat_id = $group['booking_id.center_id.season_category_id'];
                if($cat_id == 2) { // GG
                    $cat_id = $group['sojourn_type_id.season_category_id'];
                }

                $year = date('Y', $date);
                $seasons_ids = $om->search('sale\season\SeasonPeriod', [
                    ['season_category_id', '=', $cat_id],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['year', '=', $year]
                ]);

                $periods = $om->read('sale\season\SeasonPeriod', $seasons_ids, ['id', 'season_type_id.name']);
                if($periods > 0 && count($periods)){
                    $period = array_shift($periods);
                    $operands['season'] = $period['season_type_id.name'];
                }

                $discounts = $om->read('lodging\sale\discount\Discount', $discount_list['discounts_ids'], ['value', 'type', 'conditions_ids', 'value_max', 'age_ranges_ids']);

                // filter discounts based on related conditions
                $discounts_to_apply = [];
                // keep track of the final rate (for discounts with type 'percent')
                $rate_to_apply = 0;

                // filter discounts to be applied on booking lines
                foreach($discounts as $discount_id => $discount) {
                    $conditions = $om->read('lodging\sale\discount\Condition', $discount['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                        if(!$valid) break;
                    }
                    if($valid) {
                        trigger_error("ORM:: all conditions fulfilled, applying {$discount['value']} {$discount['type']}", QN_REPORT_DEBUG);
                        $discounts_to_apply[$discount_id] = $discount;
                        if($discount['type'] == 'percent') {
                            $rate_to_apply += $discount['value'];
                        }
                    }
                }

                // guaranteed rate (rate_min) is always granted
                if($discount_list['rate_min'] > 0) {
                    $rate_to_apply += $discount_list['rate_min'];
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_min']
                    ];
                }

                // if max rate (rate_max) has been reached, use max instead
                if($rate_to_apply > $discount_list['rate_max'] ) {
                    // remove all 'percent' discounts
                    foreach($discounts_to_apply as $discount_id => $discount) {
                        if($discount['type'] == 'percent') {
                            unset($discounts_to_apply[$discount_id]);
                        }
                    }
                    // add a custom discount with maximal rate
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_max']
                    ];
                }

                // apply all applicable discounts on BookingLine Group
                foreach($discounts_to_apply as $discount_id => $discount) {
                    /*
                        create price adapter for group only, according to discount and group settings
                        (needed in case group targets a pack with own price)
                    */
                    $price_adapters_ids = $om->create('lodging\sale\booking\BookingPriceAdapter', [
                        'is_manual_discount'    => false,
                        'booking_id'            => $group['booking_id'],
                        'booking_line_group_id' => $group_id,
                        'booking_line_id'       => 0,
                        'discount_id'           => $discount_id,
                        'discount_list_id'      => $discount_list_id,
                        'type'                  => $discount['type'],
                        'value'                 => $discount['value']
                    ]);

                    /*
                        create related price adapter for all lines, according to discount and group settings
                    */

                    // read all lines from group
                    $lines = $om->read('lodging\sale\booking\BookingLine', $group['booking_lines_ids'], [
                        'product_id',
                        'product_id.product_model_id',
                        'product_id.product_model_id.has_duration',
                        'product_id.product_model_id.duration',
                        'product_id.age_range_id',
                        'is_meal',
                        'is_accomodation'
                    ]);

                    foreach($lines as $line_id => $line) {
                        // do not apply discount on lines that cannot have a price
                        if($group['is_locked']) {
                            continue;
                        }
                        // do not apply freebies if manual assignment is requested
                        if($discount['type'] == 'freebie' && $freebies_manual_assignment) {
                            continue;
                        }
                        // do not apply discount if it does not concern the product age range
                        if(isset($discount['age_ranges_ids']) && count($discount['age_ranges_ids']) && isset($line['product_id.age_range_id']) && !in_array($line['product_id.age_range_id'], $discount['age_ranges_ids'])) {
                            continue;
                        }
                        if( // for GG: apply discounts only on accommodations
                            (
                                $group['sojourn_type_id'] == 2 /*'GG'*/ && $line['is_accomodation']
                            )
                            ||
                            // for GA: apply discounts on meals and accommodations
                            (
                                $group['sojourn_type_id'] == 1 /*'GA'*/
                                &&
                                (
                                    $line['is_accomodation'] || $line['is_meal']
                                )
                            ) ) {
                            trigger_error("ORM:: creating price adapter", QN_REPORT_DEBUG);
                            $factor = $group['nb_nights'];

                            if($line['product_id.product_model_id.has_duration']) {
                                $factor = $line['product_id.product_model_id.duration'];
                            }

                            $discount_value = $discount['value'];
                            // ceil freebies amount according to value referenced by value_max (nb_pers by default)
                            if($discount['type'] == 'freebie') {
                                if(isset($discount['value_max']) && $discount_value > $operands[$discount['value_max']]) {
                                    $discount_value = $operands[$discount['value_max']];
                                }
                                $discount_value *= $factor;
                            }

                            // current discount must be applied on the line: create a price adapter
                            $price_adapters_ids = $om->create('lodging\sale\booking\BookingPriceAdapter', [
                                'is_manual_discount'    => false,
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $group_id,
                                'booking_line_id'       => $line_id,
                                'discount_id'           => $discount_id,
                                'discount_list_id'      => $discount_list_id,
                                'type'                  => $discount['type'],
                                'value'                 => $discount_value
                            ]);
                        }
                    }
                }

            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching discount list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }

    public static function calcCountBooking24($om, $customer_id, $date_from) {

        $bookings_ids = $om->search(Booking::getType(),[
            ['customer_id', '=', $customer_id],
            ['date_from', '>=', strtotime('-2 years', $date_from)],
            ['is_cancelled', '=', false],
            ['status', 'not in', ['quote', 'option']]
        ]);

        return ($bookings_ids > 0)?count($bookings_ids):0;
    }

    public static function calcCountBooking12($om, $customer_id, $date_from) {

        $bookings_ids = $om->search(Booking::getType(),[
            ['customer_id', '=', $customer_id],
            ['date_from', '>=', strtotime('-365 days', $date_from)],
            ['is_cancelled', '=', false],
            ['status', 'not in', ['quote', 'option']]
        ]);

        return ($bookings_ids > 0)?0:count($bookings_ids);
    }

    public static function updatePriceAdaptersFromLines($om, $oids, $booking_lines_ids, $lang) {
        /*
            Remove all previous price adapters relating to given lines were automatically created
        */
        $price_adapters_ids = $om->search('lodging\sale\booking\BookingPriceAdapter', [ ['booking_line_id', 'in', $booking_lines_ids], ['is_manual_discount','=', false]]);
        $om->remove('lodging\sale\booking\BookingPriceAdapter', $price_adapters_ids, true);

        $line_groups = $om->read(self::getType(), $oids, [
                'has_pack',
                'pack_id.allow_price_adaptation',
                'rate_class_id',
                'sojourn_type_id',
                'sojourn_type_id.season_category_id',
                'date_from',
                'date_to',
                'nb_pers',
                'nb_children',
                'nb_nights',
                'booking_id',
                'is_locked',
                'booking_lines_ids',
                'booking_id.nb_pers',
                'booking_id.customer_id',
                'booking_id.center_id.season_category_id',
                'booking_id.center_id.discount_list_category_id',
                'booking_id.center_office_id'
            ]);

        foreach($line_groups as $group_id => $group) {
            if($group['has_pack']) {
                if(!$group['pack_id.allow_price_adaptation']) {
                    // skip group if it relates to a product model that prohibits price adaptation
                    continue;
                }
            }

            /*
                Find the first Discount List that matches the booking dates
            */

            // the discount list category to use is the one defined for the center, unless it is ('GA' or 'GG') AND sojourn_type <> category.name
            $discount_category_id = $group['booking_id.center_id.discount_list_category_id'];

            if(in_array($discount_category_id, [1 /*GA*/, 2 /*GG*/]) && $discount_category_id != $group['sojourn_type_id']) {
                $discount_category_id = $group['sojourn_type_id'];
            }

            $discount_lists_ids = $om->search('lodging\sale\discount\DiscountList', [
                ['rate_class_id', '=', $group['rate_class_id']],
                ['discount_list_category_id', '=', $discount_category_id],
                ['valid_from', '<=', $group['date_from']],
                ['valid_until', '>=', $group['date_from']]
            ]);

            $discount_lists = $om->read('lodging\sale\discount\DiscountList', $discount_lists_ids, ['id', 'discounts_ids', 'rate_min', 'rate_max']);
            $discount_list_id = 0;
            $discount_list = null;
            if($discount_lists > 0 && count($discount_lists)) {
                // use first match (there should always be only one or zero)
                $discount_list = array_pop($discount_lists);
                $discount_list_id = $discount_list['id'];
                trigger_error("ORM:: match with discount List {$discount_list_id}", QN_REPORT_DEBUG);
            }
            else {
                trigger_error("ORM:: no discount List found", QN_REPORT_DEBUG);
            }

            /*
                Read required preferences from the Center Office
            */
            $freebies_manual_assignment = false;
            $offices_preferences = $om->read(\lodging\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['freebies_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $freebies_manual_assignment = (bool) $prefs['freebies_manual_assignment'];
            }

            /*
                Search for matching Discounts within the found Discount List
            */
            if($discount_list_id) {
                $count_booking_24 = $om->call(self::getType(), 'calcCountBooking24', $group['booking_id.customer_id'], $group['date_from']);

                $operands = [
                    'count_booking_24'  => $count_booking_24,     // qty of customer bookings from 2 years ago to present
                    'duration'          => $group['nb_nights'],   // duration in nights
                    'nb_pers'           => $group['nb_pers'],     // total number of participants
                    'nb_children'       => $group['nb_children'], // number of children amongst participants
                    'nb_adults'         => $group['nb_pers'] - $group['nb_children']  // number of adults amongst participants
                ];

                $date = $group['date_from'];

                /*
                    Pick up the first season period that matches the year and the season category of the center
                */
                $cat_id = $group['booking_id.center_id.season_category_id'];
                if($cat_id == 2) { // GG
                    $cat_id = $group['sojourn_type_id.season_category_id'];
                }

                $year = date('Y', $date);
                $seasons_ids = $om->search('sale\season\SeasonPeriod', [
                    ['season_category_id', '=', $cat_id],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['year', '=', $year]
                ]);

                $periods = $om->read('sale\season\SeasonPeriod', $seasons_ids, ['id', 'season_type_id.name']);
                if($periods > 0 && count($periods)){
                    $period = array_shift($periods);
                    $operands['season'] = $period['season_type_id.name'];
                }

                $discounts = $om->read('lodging\sale\discount\Discount', $discount_list['discounts_ids'], ['value', 'type', 'conditions_ids', 'value_max', 'age_ranges_ids']);

                // filter discounts based on related conditions
                $discounts_to_apply = [];
                // keep track of the final rate (for discounts with type 'percent')
                $rate_to_apply = 0;

                // filter discounts to be applied on booking lines
                foreach($discounts as $discount_id => $discount) {
                    $conditions = $om->read('sale\discount\Condition', $discount['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                        if(!$valid) break;
                    }
                    if($valid) {
                        trigger_error("ORM:: all conditions fullfilled, applying {$discount['value']} {$discount['type']}", QN_REPORT_DEBUG);
                        $discounts_to_apply[$discount_id] = $discount;
                        if($discount['type'] == 'percent') {
                            $rate_to_apply += $discount['value'];
                        }
                    }
                }

                // guaranteed rate (rate_min) is always granted
                if($discount_list['rate_min'] > 0) {
                    $rate_to_apply += $discount_list['rate_min'];
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_min']
                    ];
                }

                // if max rate (rate_max) has been reached, use max instead
                if($rate_to_apply > $discount_list['rate_max'] ) {
                    // remove all 'percent' discounts
                    foreach($discounts_to_apply as $discount_id => $discount) {
                        if($discount['type'] == 'percent') {
                            unset($discounts_to_apply[$discount_id]);
                        }
                    }
                    // add a custom discount with maximal rate
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_max']
                    ];
                }

                // apply all applicable discounts
                foreach($discounts_to_apply as $discount_id => $discount) {

                    /*
                        create related price adapter for all lines, according to discount and group settings
                    */

                    // read all lines from group
                    $lines = $om->read(BookingLine::getType(), $booking_lines_ids, [
                        'product_id',
                        'product_id.product_model_id',
                        'product_id.product_model_id.has_duration',
                        'product_id.product_model_id.duration',
                        'product_id.age_range_id',
                        'is_meal',
                        'is_accomodation',
                        'qty_accounting_method'
                    ]);

                    foreach($lines as $line_id => $line) {
                        // do not apply discount on lines that cannot have a price
                        if($group['is_locked']) {
                            continue;
                        }
                        // do not apply freebies on accommodations for groups
                        if($discount['type'] == 'freebie' && $line['qty_accounting_method'] == 'accomodation') {
                            continue;
                        }
                        // do not apply freebies if manual assignment is requested
                        if($discount['type'] == 'freebie' && $freebies_manual_assignment) {
                            continue;
                        }
                        // do not apply discount if it does not concern the product age range
                        if(isset($discount['age_ranges_ids']) && count($discount['age_ranges_ids']) && isset($line['product_id.age_range_id']) && !in_array($line['product_id.age_range_id'], $discount['age_ranges_ids'])) {
                            continue;
                        }
                        if(// for GG: apply discounts only on accommodations
                            (
                                $group['sojourn_type_id'] == 2 /*'GG'*/ && $line['is_accomodation']
                            )
                            ||
                            // for GA: apply discounts on meals and accommodations
                            (
                                $group['sojourn_type_id'] == 1 /*'GA'*/
                                &&
                                (
                                    $line['is_accomodation'] || $line['is_meal']
                                )
                            ) ) {
                            trigger_error("ORM:: creating price adapter", QN_REPORT_DEBUG);
                            $factor = $group['nb_nights'];

                            if($line['product_id.product_model_id.has_duration']) {
                                $factor = $line['product_id.product_model_id.duration'];
                            }

                            $discount_value = $discount['value'];
                            // ceil freebies amount according to value referenced by value_max (nb_pers by default)
                            if($discount['type'] == 'freebie') {
                                if(isset($discount['value_max']) && $discount_value > $operands[$discount['value_max']]) {
                                    $discount_value = $operands[$discount['value_max']];
                                }
                                $discount_value *= $factor;
                            }

                            // current discount must be applied on the line: create a price adapter
                            $price_adapters_ids = $om->create('lodging\sale\booking\BookingPriceAdapter', [
                                'is_manual_discount'    => false,
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $group_id,
                                'booking_line_id'       => $line_id,
                                'discount_id'           => $discount_id,
                                'discount_list_id'      => $discount_list_id,
                                'type'                  => $discount['type'],
                                'value'                 => $discount_value
                            ]);
                        }
                    }
                }

            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching discount list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }


    /**
     * Update pack_id and re-create booking lines accordingly.
     *
     */
    public static function _updatePack($om, $oids, $values, $lang) {
        trigger_error("ORM::calling lodging\sale\booking\BookingLineGroup:_updatePack", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, [
            'booking_id',
            'booking_lines_ids',
            'age_range_assignments_ids',
            'nb_pers',
            'pack_id.is_locked',
            'pack_id.has_age_range',
            'pack_id.age_range_id',
            'pack_id.pack_lines_ids',
            'pack_id.product_model_id.has_own_price',
            'pack_id.product_model_id.booking_type_id.code'
        ]);

        // #memo - we assume that current group has 'has_pack' set to true

        foreach($groups as $gid => $group) {

            // 1) Update current group according to selected pack

            // might need to update price_id
            if($group['pack_id.product_model_id.has_own_price']) {
                $om->update(self::getType(), $gid, ['is_locked' => true], $lang);
            }
            else {
                $om->update(self::getType(), $gid, ['is_locked' => $group['pack_id.is_locked'] ], $lang);
            }

            // retrieve the composition of the pack
            $pack_lines = $om->read('lodging\sale\catalog\PackLine', $group['pack_id.pack_lines_ids'], [
                'child_product_model_id',
                'has_own_qty',
                'own_qty',
                'has_own_duration',
                'own_duration',
                'child_product_model_id.qty_accounting_method'
            ]);

            $pack_product_models_ids = array_map(function($a) {return $a['child_product_model_id'];}, $pack_lines);

            // remove booking lines that are part of the pack (others might have been added manually, we leave them untouched)
            $booking_lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['product_id.product_model_id'], $lang);
            if($booking_lines > 0) {
                $filtered_lines_ids = [];
                foreach($booking_lines as $lid => $line) {
                    if(in_array($line['product_id.product_model_id'], $pack_product_models_ids) ) {
                        $filtered_lines_ids[] = $lid;
                    }
                }
                // remove existing booking_lines (updating booking_lines_ids will trigger ondetach events)
                $om->update(self::getType(), $gid, ['booking_lines_ids' => array_map(function($a) { return "-$a";}, $filtered_lines_ids)]);
            }


            // 2) Create booking lines according to pack composition.

            $order = 1;
            $age_assignments = [];
            $children_age_range_id = 0;

            // retrieve age_range assignments (there must be at least one)
            $age_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['age_range_id']);

            // #todo - temporary solution - remove and deprecate
            if($group['pack_id.has_age_range'] && isset($group['pack_id.age_range_id'])) {
                $age_assignments = ['age_range_id' => $group['pack_id.age_range_id']];
            }

            // special case for school sojourn (#kaleo)
            if($group['pack_id.product_model_id.booking_type_id.code'] == 'SEJ') {
                foreach($age_assignments as $age_assignment) {
                    if($age_assignment['age_range_id'] != 1) {
                        $children_age_range_id = $age_assignment['age_range_id'];
                        break;
                    }
                }
            }
            $new_lines_ids = [];
            // associative array mapping product_model_id with price_id
            $map_prices = [];

            // pass-1 : create lines
            foreach($pack_lines as $pid => $pack_line) {
                /*
                    retrieve the product(s) to add, based on child_product_model_id and group age_ranges, if set
                    if no specific product with age_range, use nb_pers
                    if no product for a specific age_range, use "all age" product
                */
                // we expect any group to have at min. 1 age range (default)
                foreach($age_assignments as $age_assignment) {

                    $line = [
                        'order'                     => $order++,
                        'qty_accounting_method'     => $pack_line['child_product_model_id.qty_accounting_method']
                    ];

                    // handle products with no age_range (group must have only one line for those)
                    $has_single_range = false;
                    $age_range_id = $age_assignment['age_range_id'];

                    // search for a product matching model and age_range (there should be 1 or 0)
                    $products_ids = $om->search('lodging\sale\catalog\Product', [ ['product_model_id', '=', $pack_line['child_product_model_id']], ['age_range_id', '=', $age_range_id], ['can_sell', '=', true] ]);
                    // if no product for a specific age_range, use "all age" product and use range.qty
                    if($products_ids < 0 || !count($products_ids)) {
                        $products_ids = $om->search('lodging\sale\catalog\Product', [ ['product_model_id', '=', $pack_line['child_product_model_id']], ['has_age_range', '=', false], ['can_sell', '=', true] ]);
                        if($products_ids < 0 || !count($products_ids)) {
                            // issue a warning : no product match for line
                            trigger_error("ORM::no match for age range {$age_range_id} and no 'all ages' product found for model {$pack_line['child_product_model_id']}", QN_REPORT_WARNING);
                            // skip the line (no age range found)
                            continue 2;
                        }
                        $has_single_range = true;
                    }
                    $product_id = reset($products_ids);

                    // create a booking line with found product
                    $line['product_id'] = $product_id;

                    if($pack_line['has_own_qty']) {
                        $line['has_own_qty'] = true;
                        $line['qty'] = $pack_line['own_qty'];
                    }
                    if($pack_line['has_own_duration']) {
                        $line['has_own_duration'] = true;
                        $line['own_duration'] = $pack_line['own_duration'];
                    }
                    $lid = $om->create('lodging\sale\booking\BookingLine', [
                            'booking_id'                => $group['booking_id'],
                            'booking_line_group_id'     => $gid,
                        ], $lang);
                    if($lid > 0) {
                        $new_lines_ids[] = $lid;
                        // #memo - price_id and qty are auto assigned upon line assignation to a product
                        $om->update('lodging\sale\booking\BookingLine', $lid, $line, $lang);
                        $om->update(self::getType(), $gid, ['booking_lines_ids' => ["+$lid"] ]);
                        // #kaleo - special case for school sojourn: adults use children price
                        if($age_range_id == $children_age_range_id) {
                            $lines = $om->read('lodging\sale\booking\BookingLine', $lid, ['price_id'], $lang);
                            $line = reset($lines);
                            $map_prices[$pack_line['child_product_model_id']] = $line['price_id'];
                        }
                    }
                    if($has_single_range) {
                        break;
                    }
                }
            }

            // pass-2 : for school sojourns only - update price_id according to product model
            if($group['pack_id.product_model_id.booking_type_id.code'] == 'SEJ') {
                $lines = $om->read('lodging\sale\booking\BookingLine', $new_lines_ids, ['product_model_id', 'price_id'], $lang);
                foreach($lines as $lid => $line) {
                    if(isset($map_prices[$line['product_model_id']]) && $line['price_id'] != $map_prices[$line['product_model_id']]) {
                        $om->update('lodging\sale\booking\BookingLine', $lid, ['price_id' => $map_prices[$line['product_model_id']] ], $lang);
                    }
                }
            }
        }

        // update dependencies
        $om->callonce(self::getType(), 'createRentalUnitsAssignments', $oids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
        $om->callonce(self::getType(), '_updateAutosaleProducts', $oids, [], $lang);
        $om->callonce(self::getType(), '_updateMealPreferences', $oids, [], $lang);
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

            ## when we update a pack (`onupdatePackId`)

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

                    // for events, non-accommodations are scheduled according to the event (group)
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
                            'is_meal',
                            'is_repeatable'
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
                        if(!$product_models[$line['product_id.product_model_id']]['is_repeatable']) {
                            $nb_products = 1;
                        }
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

                            // discard consumption with a resulting qty set to 0
                            if($days_nb_times[$i] == 0) {
                                continue;
                            }

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

    /**
     * Updates rental unit assignments from a set of booking lines (called by BookingLine::onupdateProductId).
     * The references booking_lines_ids are expected to be identifiers of lines that have just been modified and to belong to a same sojourn (BookingLineGroup).
     */
    public static function createRentalUnitsAssignmentsFromLines($om, $oids, $booking_lines_ids, $lang) {

        // Attempt to auto-assign rental units.
        $groups = $om->read(self::getType(), $oids, [
            'booking_id',
            'booking_id.center_office_id',
            'nb_pers',
            'has_locked_rental_units',
            'booking_lines_ids',
            'date_from',
            'date_to',
            'time_from',
            'time_to',
            'sojourn_product_models_ids',
            'rental_unit_assignments_ids.rental_unit_id'
        ]);

        // 1-st pass: check assignment prefs and try to auto-assign if necessary
        foreach($groups as $gid => $group) {

            /*
                Read required preferences from the Center Office
            */
            $rentalunits_manual_assignment = false;
            $offices_preferences = $om->read(\lodging\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['rentalunits_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $rentalunits_manual_assignment = (bool) $prefs['rentalunits_manual_assignment'];
            }

            if(!$rentalunits_manual_assignment && $group['has_locked_rental_units'] && count($group['sojourn_product_models_ids'])) {
                continue;
            }

            $nb_pers = $group['nb_pers'];
            $date_from = $group['date_from'] + $group['time_from'];
            $date_to = $group['date_to'] + $group['time_to'];

            // retrieve rental units that are already assigned by other groups within the same time range, if any
            // (we need to withdraw those from available units)
            $booking_assigned_rental_units_ids = [];
            $bookings = $om->read(Booking::getType(), $group['booking_id'], ['booking_lines_groups_ids', 'rental_unit_assignments_ids'], $lang);
            if($bookings > 0 && count($bookings)) {
                $booking = reset($bookings);
                $groups = $om->read(self::getType(), $booking['booking_lines_groups_ids'], ['id', 'date_from', 'date_to', 'time_from', 'time_to'], $lang);
                $assignments = $om->read(SojournProductModelRentalUnitAssignement::getType(), $booking['rental_unit_assignments_ids'], ['rental_unit_id', 'booking_line_group_id'], $lang);
                foreach($assignments as $oid => $assignment) {
                    // process rental units from other groups
                    if($assignment['booking_line_group_id'] != $gid) {
                        $group_id = $assignment['booking_line_group_id'];
                        $group_date_from = $groups[$group_id]['date_from'] + $groups[$group_id]['time_from'];
                        $group_date_to = $groups[$group_id]['date_to'] + $groups[$group_id]['time_to'];
                        // if groups have a time range intersection, mark the rental unit as assigned
                        if($group_date_from >= $date_from && $group_date_from <= $date_to
                        || $group_date_to >= $date_from && $group_date_to <= $date_to) {
                            $booking_assigned_rental_units_ids[] = $assignment['rental_unit_id'];
                        }
                    }
                }
            }

            // create a map with all product_model_id within the group
            $group_product_models_ids = [];

            $sojourn_product_models = $om->read(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['product_model_id'], $lang);
            foreach($sojourn_product_models as $spid => $spm){
                $group_product_models_ids[$spm['product_model_id']] = $spid;
            }

            // read children booking lines
            $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                    'booking_id.center_id',
                    'product_id',
                    'product_id.product_model_id',
                    'qty_accounting_method',
                    'is_rental_unit'
                ],
                $lang);

            // drop lines that do not relate to rental units
            $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });

            if(count($lines)) {

                // read all related product models at once
                $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_id.product_model_id'];}, array_keys($lines));
                $product_models = $om->read('lodging\sale\catalog\ProductModel', $product_models_ids, ['is_accomodation', 'qty_accounting_method', 'rental_unit_assignement', 'capacity'], $lang);

                // pass-1 : withdraw persons assigned to units accounted by 'accomodation' from nb_pers, and create SPMs
                foreach($lines as $lid => $line) {
                    $product_model_id = $line['product_id.product_model_id'];
                    if($product_models[$product_model_id]['qty_accounting_method'] == 'accomodation') {
                        $nb_pers -= $product_models[$product_model_id]['capacity'];
                    }
                    if(!isset($group_product_models_ids[$product_model_id])) {
                        $sojourn_product_model_id = $om->create(SojournProductModel::getType(), [
                            'booking_id'            => $group['booking_id'],
                            'booking_line_group_id' => $gid,
                            'product_model_id'      => $product_model_id
                        ]);
                        $group_product_models_ids[$product_model_id] = $sojourn_product_model_id;
                    }
                }
            }

            // do not auto-assign rental units if manual assignment is set in prefs
            if($rentalunits_manual_assignment) {
                continue;
            }

            // read targeted booking lines (received as method param)
            $lines = $om->read(BookingLine::getType(), $booking_lines_ids, [
                    'booking_id.center_id',
                    'product_id',
                    'product_id.product_model_id',
                    'qty_accounting_method',
                    'is_rental_unit'
                ],
                $lang);

            // drop lines that do not relate to rental units
            $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });

            if(count($lines)) {
                // pass-2 : process lines
                $group_assigned_rental_units_ids = [];
                $has_processed_accomodation_by_person = false;
                foreach($lines as $lid => $line) {

                    $center_id = $line['booking_id.center_id'];

                    $is_accomodation = $product_models[$line['product_id.product_model_id']]['is_accomodation'];
                    // 'accomodation', 'person', 'unit'
                    $qty_accounting_method = $product_models[$line['product_id.product_model_id']]['qty_accounting_method'];

                    // 'category', 'capacity', 'auto'
                    // #memo - the assignment-based filtering is done in `Consumption::getAvailableRentalUnits`
                    $rental_unit_assignment = $product_models[$line['product_id.product_model_id']]['rental_unit_assignement'];

                    // all lines with same product_model are processed at the first line, remaining lines must be ignored
                    if($qty_accounting_method == 'person' && $is_accomodation && $has_processed_accomodation_by_person) {
                        continue;
                    }

                    $nb_pers_to_assign = $nb_pers;

                    if($qty_accounting_method == 'accomodation') {
                        $nb_pers_to_assign = min($product_models[$line['product_id.product_model_id']]['capacity'], $group['nb_pers']);
                    }
                    elseif($qty_accounting_method == 'unit') {
                        $nb_pers_to_assign = $group['nb_pers'];
                    }

                    // find available rental units (sorted by capacity, desc; filtered on product model category)
                    $rental_units_ids = Consumption::getAvailableRentalUnits($om, $center_id, $line['product_id.product_model_id'], $date_from, $date_to);

                    // #memo - we cannot append rental units from consumptions of own booking :this leads to an edge case
                    // (use case "come and go between 'quote' and 'option'" is handled with 'realease-rentalunits' action)

                    // remove rental units that are no longer unavailable
                    $rental_units_ids = array_diff($rental_units_ids,
                            $group_assigned_rental_units_ids,               // assigned to other lines (current loop)
                            $booking_assigned_rental_units_ids              // assigned within other groups
                        );

                    // retrieve rental units with matching capacities (best match first)
                    $rental_units = self::_getRentalUnitsMatches($om, $rental_units_ids, $nb_pers_to_assign);

                    $remaining = $nb_pers_to_assign;
                    $assigned_rental_units = [];

                    // min serie for available capacity starts from max(0, i-1)
                    for($j = 0, $n = count($rental_units) ;$j < $n; ++$j) {
                        $rental_unit = $rental_units[$j];
                        $assigned = min($rental_unit['capacity'], $remaining);
                        $rental_unit['assigned'] = $assigned;
                        $assigned_rental_units[] = $rental_unit;
                        $remaining -= $assigned;
                        if($remaining <= 0) break;
                    }

                    if($remaining > 0) {
                        // no availability !
                        trigger_error("ORM::no availability", QN_REPORT_DEBUG);
                    }
                    else {
                        foreach($assigned_rental_units as $rental_unit) {
                            $assignement = [
                                'booking_id'                    => $group['booking_id'],
                                'booking_line_group_id'         => $gid,
                                'sojourn_product_model_id'      => $group_product_models_ids[$line['product_id.product_model_id']],
                                'qty'                           => $rental_unit['assigned'],
                                'rental_unit_id'                => $rental_unit['id']
                            ];
                            trigger_error("ORM::assigning {$rental_unit['assigned']} p. to {$rental_unit['id']}", QN_REPORT_DEBUG);
                            $om->create(SojournProductModelRentalUnitAssignement::getType(), $assignement);
                            // remember assigned rental units (for next lines processing)
                            $group_assigned_rental_units_ids[]= $rental_unit['id'];
                        }

                        if($qty_accounting_method == 'person' && $is_accomodation) {
                            $has_processed_accomodation_by_person = true;
                        }
                    }
                }
            }
        }

        // 2-nd pass: in any situation, if the group targets additional services (is_extra), we dispatch a notification about required assignment
        $groups = $om->read(self::getType(), $oids, [
                'booking_id',
                'is_extra',
                'booking_lines_ids'
            ]);

        $bookings_ids_map = [];
        foreach($groups as $gid => $group) {
            if($group['is_extra']) {
                // read children booking lines
                $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                        'is_rental_unit'
                    ],
                    $lang);

                // drop lines that do not relate to rental units
                $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });
                if(count($lines)) {
                    $bookings_ids_map[$group['booking_id']] = true;
                }
            }
        }

        if(count($bookings_ids_map)) {
            $cron = $om->getContainer()->get('cron');
            $bookings_ids = array_keys($bookings_ids_map);
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

    }



    /**
     * Find and set price according to group settings.
     * This only applies when group targets a Pack with own price.
     *
     * Should only be called when is_locked == true
     *
     * _updatePriceId is called upon change on: pack_id, is_locked, date_from, center_id
     */
    public static function _updatePriceId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:_updatePriceId", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, [
            'has_pack',
            'date_from',
            'pack_id',
            'booking_id',
            'booking_id.center_id.price_list_category_id'
        ]);

        foreach($groups as $gid => $group) {
            if(!$group['has_pack']) {
                continue;
            }

            // #todo - we shouldn't perform this search if pack is not marked as having its own price

            /*
                Find the Price List that matches the criteria from the booking with the shortest duration
            */
            $price_lists_ids = $om->search(
                'sale\price\PriceList', [
                    [
                        ['price_list_category_id', '=', $group['booking_id.center_id.price_list_category_id']],
                        ['date_from', '<=', $group['date_from']],
                        ['date_to', '>=', $group['date_from']],
                        ['status', 'in', ['pending', 'published']]
                    ],
                    // #todo - quick workaround for inclusion of GA pricelist
                    [
                        ['price_list_category_id', '=', 9],
                        ['date_from', '<=', $group['date_from']],
                        ['date_to', '>=', $group['date_from']],
                        ['status', 'in', ['pending', 'published']]
                    ]
                ],
                ['duration' => 'asc']
            );

            $is_tbc = false;
            $selected_price_id = 0;

            /*
                Search for a matching Price within the found Price Lists
            */
            if($price_lists_ids > 0 && count($price_lists_ids)) {
                // check status and, if 'pending', evaluate if there is a 'published' alternative
                $price_lists = $om->read(\sale\price\PriceList::getType(), $price_lists_ids, [ 'status' ]);

                foreach($price_lists as $price_list_id => $price_list) {
                    $prices_ids = $om->search(\sale\price\Price::getType(), [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $group['pack_id']] ]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        $selected_price_id = reset($prices_ids);
                        if($price_list['status'] == 'pending') {
                            $is_tbc = true;
                        }
                        else {
                            // first matching published price is always preferred: stop searching
                            break;
                        }
                        // keep on looping until we reach end of candidates or we find one with status 'published'
                    }
                }
            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no price list candidates for group pack {$group['pack_id']} for date {$date}", QN_REPORT_WARNING);
            }

            if($selected_price_id > 0) {
                // assign found Price to current group
                $om->update(self::getType(), $gid, ['price_id' => $selected_price_id]);
                if($is_tbc) {
                    // found price is TBC: mark booking as to be confirmed
                    $om->update(Booking::getType(), $group['booking_id'], ['is_price_tbc' => true]);
                }
            }
            else {
                $om->update(self::getType(), $gid, ['price_id' => null, 'vat_rate' => 0, 'unit_price' => 0]);
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching price found for group pack {$group['pack_id']} for date {$date}", QN_REPORT_WARNING);
            }
        }
    }

    /**
     * Generate one or more lines for products sold automatically.
     * We generate services groups related to autosales when the following fields are updated:
     * customer, date_from, date_to, center_id
     *
     */
    public static function _updateAutosaleProducts($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:_updateAutosaleProducts", QN_REPORT_DEBUG);

        /*
            re-create lines related to autosales
        */
        $groups = $om->read(self::getType(), $oids, [
                'is_autosale',
                'is_sojourn',
                'nb_pers',
                'nb_nights',
                'date_from',
                'date_to',
                'booking_id',
                'has_pack',
                'pack_id.product_model_id.booking_type_id.code',
                'booking_id.center_id.autosale_list_category_id',
                'booking_id.customer_id',
                'booking_id.center_id',
                'booking_id.customer_id.count_booking_12',
                'booking_lines_ids'
            ], $lang);

        // loop through groups and create lines for autosale products, if any
        foreach($groups as $group_id => $group) {

            $center = Center::id($group['booking_id.center_id'])->read(['has_citytax_school'])->first(true);

            // reset previously set autosale products
            $lines_ids_to_delete = [];
            $booking_lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['is_autosale'], $lang);
            if($booking_lines > 0) {
                foreach($booking_lines as $lid => $line) {
                    if($line['is_autosale']) {
                        $lines_ids_to_delete[] = -$lid;
                    }
                }
                $om->update(self::getType(), $group_id, ['booking_lines_ids' => $lines_ids_to_delete], $lang);
            }

            // autosale groups are handled at the Booking level
            if($group['is_autosale']) {
                continue;
            }
            // autosales only apply on sojourns
            if(!$group['is_sojourn']) {
                continue;
            }

            /*
                Find the first Autosale List that matches the booking dates
            */

            $autosale_lists_ids = $om->search('sale\autosale\AutosaleList', [
                ['autosale_list_category_id', '=', $group['booking_id.center_id.autosale_list_category_id']],
                ['date_from', '<=', $group['date_from']],
                ['date_to', '>=', $group['date_from']]
            ]);

            $autosale_lists = $om->read('sale\autosale\AutosaleList', $autosale_lists_ids, ['id', 'autosale_lines_ids']);
            $autosale_list_id = 0;
            $autosale_list = null;
            if($autosale_lists > 0 && count($autosale_lists)) {
                // use first match (there should always be only one or zero)
                $autosale_list = array_pop($autosale_lists);
                $autosale_list_id = $autosale_list['id'];
                trigger_error("ORM:: match with autosale List {$autosale_list_id}", QN_REPORT_DEBUG);
            }
            else {
                trigger_error("ORM:: no autosale List found", QN_REPORT_DEBUG);
            }
            /*
                Search for matching Autosale products within the found List
            */
            if($autosale_list_id) {
                $operands = [];

                // for now, we only support member cards for customer that haven't booked a service for more thant 12 months

                $operands['count_booking_12'] = $om->call(self::getType(), 'calcCountBooking12', $group['booking_id.customer_id'], $group['date_from']);
                $operands['nb_pers'] = $group['nb_pers'];
                $operands['nb_nights'] = $group['nb_nights'];

                $autosales = $om->read('sale\autosale\AutosaleLine', $autosale_list['autosale_lines_ids'], [
                    'product_id.id',
                    'product_id.name',
                    'product_id.sku',
                    'has_own_qty',
                    'qty',
                    'scope',
                    'conditions_ids'
                ], $lang);

                // filter discounts based on related conditions
                $products_to_apply = [];

                // pass-1: filter discounts to be applied on booking lines
                foreach($autosales as $autosale_id => $autosale) {
                    if($autosale['scope'] != 'group') {
                        continue;
                    }
                    // do not apply city tax for school sojourns
                    if( $group['has_pack']
                        && isset($group['pack_id.product_model_id.booking_type_id.code'])
                        && $group['pack_id.product_model_id.booking_type_id.code'] == 'SEJ'
                        && $autosale['product_id.sku'] == 'KA-CTaxSej-A'
                        && !$center['has_citytax_school']) {
                        continue;
                    }

                    $conditions = $om->read('sale\autosale\Condition', $autosale['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                        if(!$valid) break;
                    }
                    if($valid) {
                        trigger_error("ORM:: all conditions fullfilled", QN_REPORT_DEBUG);
                        $products_to_apply[$autosale_id] = [
                            'id'            => $autosale['product_id.id'],
                            'name'          => $autosale['product_id.name'],
                            'has_own_qty'   => $autosale['has_own_qty'],
                            'qty'           => $autosale['qty']
                        ];
                    }
                }

                // pass-2: apply all applicable products
                $count = count($products_to_apply);

                if($count) {
                    // add all applicable products at the end of the group
                    $order = 1000;
                    foreach($products_to_apply as $autosale_id => $product) {
                        $line = [
                            'order'                     => $order++,
                            'booking_id'                => $group['booking_id'],
                            'booking_line_group_id'     => $group_id,
                            'is_autosale'               => true,
                            'has_own_qty'               => $product['has_own_qty']
                        ];
                        $line_id = $om->create(BookingLine::getType(), $line, $lang);
                        // set product_id (will trigger recompute)
                        $om->update(BookingLine::getType(), $line_id, ['product_id' => $product['id']], $lang);
                        // #memo - we should do this beforehand (onupdateProductId should be split to a standalone method for checking if a product has a price in a given context)
                        // read the resulting product
                        $lines = $om->read(BookingLine::getType(), $line_id, ['price_id', 'price_id.price'], $lang);
                        // prevent adding autosale products for which a price could not be retrieved (invoices with lines without accounting rule are invalid)
                        if($lines > 0 && count($lines)) {
                            $line = reset($lines);
                            if(!isset($line['price_id']) || is_null($line['price_id']) || $line['price_id.price'] <= 0.01) {
                                $om->delete(BookingLine::getType(), $line_id, true);
                            }
                        }
                    }
                }
            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching autosale list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }


    public static function _updateMealPreferences($om, $oids, $values, $lang) {

        $groups = $om->read(self::getType(), $oids, [
                'is_sojourn',
                'is_event',
                'nb_pers',
                'meal_preferences_ids'
            ], $lang);

        if($groups > 0) {
            foreach($groups as $gid => $group) {
                if($group['is_sojourn'] || $group['is_event']) {
                    if(count($group['meal_preferences_ids']) == 0)  {
                        // create a meal preference
                        $pref = [
                            'booking_line_group_id'     => $gid,
                            'qty'                       => $group['nb_pers'],
                            'type'                      => '2_courses',
                            'pref'                      => 'regular'
                        ];
                        $om->create('sale\booking\MealPreference', $pref, $lang);
                    }
                    elseif(count($group['meal_preferences_ids']) == 1)  {
                        $om->update('sale\booking\MealPreference', $group['meal_preferences_ids'], ['qty' => $group['nb_pers']], $lang);
                    }
                }
            }
        }
    }

    protected static function _getRentalUnitsCombinations($list, $target, $start, $sum, $collect) {
        $result = [];

        // current sum matches target
        if($sum == $target) {
            return [$collect];
        }

        // try sub-combinations
        for($i = $start, $n = count($list); $i < $n; ++$i) {

            // check if the sum exceeds target
            if( ($sum + $list[$i]['capacity']) > $target ) {
                continue;
            }

            // check if it is repeated or not
            if( ($i > $start) && ($list[$i]['capacity'] == $list[$i-1]['capacity']) ) {
                continue;
            }

            // take the element into the combination
            $collect[] = $list[$i];

            // recursive call
            $res = self::_getRentalUnitsCombinations($list, $target, $i + 1, $sum + $list[$i]['capacity'], $collect);

            if(count($res)) {
                foreach($res as $r) {
                    $result[] = $r;
                }
            }

            // Remove element from the combination
            array_pop($collect);
        }

        return $result;
    }


    protected static function _getRentalUnitsMatches($om, $rental_units_ids, $nb_pers_to_assign) {
        // retrieve rental units capacities
        $rental_units = [];

        if($rental_units_ids > 0 && count($rental_units_ids)) {
            $rental_units = array_values($om->read('lodging\realestate\RentalUnit', $rental_units_ids, ['id', 'capacity']));
        }

        $found = false;
        // pass-1 - search for an exact capacity match
        for($i = 0, $n = count($rental_units); $i < $n; ++$i) {
            if($rental_units[$i]['capacity'] == $nb_pers_to_assign) {
                $rental_units = [$rental_units[$i]];
                $found = true;
                break;
            }
        }
        // pass-2 - no exact match: choose between min matching capacity and spreading pers across units
        if(!$found && count($rental_units)) {
            // handle special case : smallest rental unit has bigger capacity than nb_pers
            if($nb_pers_to_assign < $rental_units[$n-1]['capacity']) {
                $rental_units = [$rental_units[$n-1]];
            }
            else {
                $i = 0;
                while($rental_units[$i]['capacity'] > $nb_pers_to_assign) {
                    // we should reach $n-2 at maximum
                    ++$i;
                }
                $alternate_index = $i-1;
                $alternate = 0;
                if($alternate_index >= 0) {
                    $rental_unit = $rental_units[$alternate_index];
                    $alternate = $rental_unit['capacity'];
                }

                $collect = [];
                $list = array_slice($rental_units, $i);

                $combinations = self::_getRentalUnitsCombinations($list, $nb_pers_to_assign, 0, 0, $collect);

                if(count($combinations)) {
                    $min_index = -1;
                    // $D = abs($alternate - $nb_pers);
                    // favour a single accomodation
                    $D = abs($alternate - $nb_pers_to_assign) / 2;

                    foreach($combinations as $index => $combination) {
                        // $R = floor($nb_pers / count($combination));
                        $R = count($combination);

                        if($R <= $D) {
                            if($min_index >= 0) {
                                if(count($combinations[$min_index]) > count($combination)) {
                                    $min_index = $index;
                                }
                            }
                            else {
                                $min_index = $index;
                            }
                        }
                    }
                    // we found at least one combination
                    if($min_index >= 0) {
                        $rental_units = $combinations[$min_index];
                    }
                    else if($alternate_index >= 0) {
                        $rental_units = [$rental_units[$alternate_index]];
                    }
                    else {
                        $rental_units = [];
                    }
                }
                else {
                    $rental_units = [];
                }
            }
        }
        return $rental_units;
    }


    /**
     * This method is used to remove all SPM relating to 'accomodation' product model no longer present in sojourn lines.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function _updateSPM($om, $oids, $values=[], $lang='en') {
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids', 'sojourn_product_models_ids']);
        if($groups > 0 && count($groups)) {

            foreach($groups as $gid => $group) {
                $spms = $om->read(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['is_accomodation', 'product_model_id']);
                $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['id', 'is_accomodation', 'product_model_id']);

                // ignore lines that are about to be deleted, if any
                if(isset($values['deleted'])) {
                    $lines = array_filter($lines, function ($a) use($values) { return !in_array($a['id'], $values['deleted']);} );
                }

                foreach($spms as $sid => $spm) {
                    // #memo - all rental units must be handled, even non-accomodation (ex.: meeting rooms)
                    /*
                    // ignore non-accomodation spm
                    if(!$spm['is_accomodation']) {
                        continue;
                    }
                    */
                    foreach($lines as $lid => $line) {
                        if($line['product_model_id'] == $spm['product_model_id']) {
                            continue 2;
                        }
                    }
                    $om->delete(SojournProductModel::getType(), $sid, true);
                }
            }
        }
    }
}