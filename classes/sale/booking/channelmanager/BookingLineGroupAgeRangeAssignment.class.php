<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;

class BookingLineGroupAgeRangeAssignment extends \lodging\sale\booking\BookingLineGroupAgeRangeAssignment {

    public static function getName() {
        return "Age Range Assignment";
    }

    /*
        Assignments are created while selecting the hosts details/composition for a booking group.
        Each group is assigned to one or more age ranges.
    */

    public static function getColumns() {
        return [

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\Booking',
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'ondelete'          => 'cascade'
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\BookingLineGroup',
                'description'       => 'Booking lines Group the assignment relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'qty' => [
                'type'              => 'integer',
                'description'       => 'Number of persons assigned to the age range for related booking group.',
                'default'           => 1
            ],

            'age_range_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\AgeRange',
                'description'       => 'Age range assigned to booking group.',
                'ondelete'          => 'null'
            ]

        ];
    }

    public static function ondelete($om, $ids) {
    }

    /**
     * Check wether an object can be deleted, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $ids       List of objects identifiers.
     * @return boolean  Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        // override parent
        return [];
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     * It prevents updating if the parent booking is not in quote.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $ids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {
        $assignments = $om->read(self::getType(), $ids, ['booking_id.status'], $lang);
        if($assignments > 0) {
            foreach($assignments as $assignment) {
                if($assignment['booking_id.status'] != 'quote') {
                    return ['booking_id' => ['non_editable' => 'Rental units assignments cannot be updated for non-quote bookings.']];
                }
            }
        }
        // override parent
        return [];
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
        if(isset($values['booking_line_group_id'])) {
            $groups = $om->read(BookingLineGroup::getType(), $values['booking_line_group_id'], ['booking_id.status'], $lang);
            $group = reset($groups);
            if($group['booking_id.status'] != 'quote') {
                return ['booking_id' => ['non_editable' => 'Rental units assignments cannot be updated for non-quote bookings.']];
            }
        }
        return parent::cancreate($om, $values, $lang);
    }

    public function getUnique() {
        return [
            ['booking_line_group_id', 'age_range_id']
        ];
    }

}
