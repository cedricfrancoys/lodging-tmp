<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;

class BookingLine extends \lodging\sale\booking\BookingLine {

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
                'default'           => 1.0
            ],

            'qty_vars' => [
                'type'              => 'text',
                'description'       => 'JSON array holding qty variation deltas (for \'by person\' products), if any.',
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\BookingLineGroup',
                'description'       => 'Group the line relates to (in turn, groups relate to their booking).',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\Booking',
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.',
            ],

            'unit_price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Tax-excluded unit price (with automated discounts applied).'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price the line relates to (retrieved by price list).'
            ]

        ];
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
            if($bookings > 0) {
                $booking = reset($bookings);

                if( $booking['status'] != 'quote' ) {
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
     * @param  object   $om         ObjectManager instance.
     * @param  array    $ids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {

        // handle exceptions for fields that can always be updated
        $allowed = ['is_contractual', 'is_invoiced'];
        $count_non_allowed = 0;

        foreach($values as $field => $value) {
            if(!in_array($field, $allowed)) {
                ++$count_non_allowed;
            }
        }

        if($count_non_allowed > 0) {
            $lines = $om->read(self::getType(), $ids, ['booking_id.status'], $lang);
            if($lines > 0) {
                foreach($lines as $line) {
                    if($line['booking_id.status'] != 'quote') {
                        return ['booking_id' => ['non_editable' => 'Services cannot be updated for non-quote bookings.']];
                    }
                }
            }
        }

        return [];
        // ignore parent
        return parent::canupdate($om, $ids, $values, $lang);
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
        $lines = $om->read(self::getType(), $oids, ['booking_id.status']);

        if($lines > 0) {
            foreach($lines as $line) {
                if($line['booking_id.status'] != 'quote') {
                    return ['booking_id' => ['non_deletable' => 'Services cannot be updated for non-quote bookings.']];
                }
            }
        }

        return parent::candelete($om, $oids);
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
    }

    public static function onupdate($om, $oids, $values, $lang) {
    }

}