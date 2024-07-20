<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;
use equal\orm\Model;

class SojournProductModelRentalUnitAssignement extends Model {

    public static function getName() {
        return "Rental Unit Assignment";
    }

    public static function getDescription() {
        return "Assignments are created while selecting the services for a booking.\n
        Each product line that targets a product model that is used to assign one or morea rental unit, based on capacity and capacity.\n";
    }

    public static function getColumns() {
        return [
            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'ondelete'          => 'cascade'         // delete assignment when parent booking is deleted
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
                'description'       => 'Booking lines Group the assignment relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'sojourn_product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\SojournProductModel',
                'description'       => "Product Model group of the assignment.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'qty' => [
                'type'              => 'integer',
                'description'       => 'Number of persons assigned to the rental unit for related booking line.',
                'default'           => 1,
                'onupdate'          => 'onupdateQty'
            ],

            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'description'       => 'Rental unit assigned to booking line.',
                'ondelete'          => 'null',
                'onupdate'          => 'onupdateRentalUnitId'
            ],

            'is_accomodation' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the assignment as an accommodation (from rental unit)).',
                'function'          => 'calcIsAccomodation',
                'store'             => true
            ]

        ];
    }

    public static function calcIsAccomodation($om, $ids, $lang) {
        $result = [];
        $assignments = $om->read(self::getType(), $ids, ['rental_unit_id.is_accomodation'], $lang);
        foreach($assignments as $oid => $assignment) {
            $result[$oid] = $assignment['rental_unit_id.is_accomodation'];
        }
        return $result;
    }

    public static function onupdateRentalUnitId($om, $ids, $values, $lang) {
        // reset is_accomodation flag (computed)
        $om->update(self::getType(), $ids, ['is_accomodation' => null], $lang);

        $assignments = $om->read(self::getType(), $ids, ['qty', 'rental_unit_id.capacity', 'booking_line_group_id', 'booking_id.status'], $lang);

        // pass-1 : adapt qty
        foreach($assignments as $oid => $assignment) {
            if($assignment['qty'] == 0 || $assignment['qty'] > $assignment['rental_unit_id.capacity']) {
                $om->update(self::getType(), $oid, ['qty' => $assignment['rental_unit_id.capacity']], $lang);
            }
        }

        // if qty is set as well, we're creating the object, ignore updating the consumptions
        // #memo - this has been disabled and replaced with transactional modification
        /*
        if(!isset($values['qty'])) {
            // pass-2 : recreate consumptions (instant) for impacted groups
            foreach($assignments as $aid => $assignment) {
                if($assignment['booking_id.status'] != 'quote') {
                    $om->call(BookingLineGroup::getType(), 'createConsumptions', (array) $assignment['booking_line_group_id'], []);
                }
            }
        }
        */
    }

    /**
     * This hook is used in order to reset parent SPM qty field, and to recreate consumptions relating to 'extra' groups involved in assignment update.
     */
    public static function onupdateQty($om, $ids, $values, $lang) {
        $assignments = $om->read(self::getType(), $ids, ['booking_line_group_id', 'sojourn_product_model_id', 'booking_id.status'], $lang);
        if($assignments > 0 && count($assignments)) {
            // reset qty of parents SPM
            $spm_ids = array_map(function($a) {return $a['sojourn_product_model_id'];}, $assignments);
            $om->update(SojournProductModel::getType(), $spm_ids, ['qty' => null], $lang);

            // #memo - this has been disabled and replaced with transactional modification
            /*
            // recreate consumptions (instant) for impacted groups
            foreach($assignments as $aid => $assignment) {
                if($assignment['booking_id.status'] != 'quote') {
                    $om->call(BookingLineGroup::getType(), 'createConsumptions', (array) $assignment['booking_line_group_id'], []);
                }
            }
            */
        }
    }

    public function getUnique() {
        return [
            ['sojourn_product_model_id', 'rental_unit_id']
        ];
    }

    /**
     * Check wether an object can be created.
     * These tests come in addition to the unique constraints return by method `getUnique()`.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  ObjectManager    $om         ObjectManager instance.
     * @param  array            $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string           $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {
        // #memo - for flexibility, we allow creation of SPMA at any time
        /*
        if(isset($values['booking_line_group_id'])) {
            $groups = $om->read(BookingLineGroup::getType(), $values['booking_line_group_id'], ['booking_id.status', 'is_extra'], $lang);
            $group = reset($groups);
            if($group['booking_id.status'] != 'quote' && !$group['is_extra']) {
                return ['booking_id' => ['non_editable' => 'Rental units assignments cannot be updated for non-quote bookings.']];
            }
        }
        */
        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overriden to define a more precise set of tests.
     * It prevents updating if the parent booking is not in quote.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {
        // fields that can always be changed
        $allowed_fields = ['qty', 'rental_unit_id'];

        if(count(array_diff(array_keys($values), $allowed_fields))) {
            $assignments = $om->read(self::getType(), $oids, ['booking_id.status', 'booking_line_group_id.is_extra'], $lang);
            if($assignments > 0) {
                foreach($assignments as $assignment) {
                    // #memo - extra groups can be modified at any time
                    if(!$assignment['booking_line_group_id.is_extra']) {
                        if(!in_array($assignment['booking_id.status'], ['quote', 'option', 'confirmed', 'validated', 'checkedin'])) {
                            return ['booking_id' => ['non_editable' => 'Rental units assignments cannot be updated for checked out bookings.']];
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
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @return boolean  Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $oids) {
        $assignments = $om->read(self::getType(), $oids, ['booking_id.status', 'booking_line_group_id.is_extra']);
        if($assignments > 0) {
            foreach($assignments as $assignment) {
                // #memo - extra groups can be modified at any time
                if(!$assignment['booking_line_group_id.is_extra']) {
                    if(!in_array($assignment['booking_id.status'], ['quote', 'option', 'confirmed', 'validated', 'checkedin'])) {
                        return ['booking_id' => ['non_editable' => 'Assignments can only be deleted for checked out bookings.']];
                    }
                }
            }
        }
        return parent::candelete($om, $oids);
    }

}