<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;

class Booking extends \lodging\sale\booking\Booking {

    public static function getDescription() {
        return "This class is essentially a mimic of lodging Booking, but all events are disabled so that it allows arbitrary changes are values.
        It also contains specific values for mapping booking with OTA reservations.";
    }

    public static function getColumns() {
        return [

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'quote',                    // booking is just informative: nothing has been booked in the planning
                    'option',                   // booking has been placed in the planning for 10 days
                    'confirmed',                // booking has been placed in the planning without time limit
                    'validated',                // signed contract and first installment have been received
                    'checkedin',                // host is currently occupying the booked rental unit
                    'checkedout',               // host has left the booked rental unit
                    'invoiced',
                    'debit_balance',            // customer still has to pay something
                    'credit_balance',           // a reimbursement to customer is required
                    'balanced'                  // booking is over and balance is cleared
                ],
                'description'       => 'Status of the booking.',
                'default'           => 'quote'
            ],

            'is_invoiced' => [
                "type"              => "boolean",
                "description"       => "Marks the booking has having a non-cancelled balance invoice.",
                "default"           => false
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\customer\Customer',
                'description'       => "The customer whom the booking relates to (depends on selected identity)."
            ],

            'customer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => "The identity of the customer whom the booking relates to.",
                'required'          => true
            ],

            'customer_nature_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerNature',
                'description'       => 'Nature of the customer for views convenience.',
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center to which the booking relates to.",
                'required'          => true
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => 'Office the invoice relates to (for center management).',
            ],


            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\BookingLine',
                'foreign_field'     => 'booking_id',
                'description'       => 'Detailed consumptions of the booking.'
            ],

            'booking_lines_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\BookingLineGroup',
                'foreign_field'     => 'booking_id',
                'description'       => 'Grouped consumptions of the booking.',
                'order'             => 'order',
                'ondetach'          => 'delete'
            ],

            'sojourn_product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\SojournProductModel',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The product models groups assigned to the booking (from groups).",
                'ondetach'          => 'delete'
            ],

            'extref_reservation_id' => [
                'type'              => 'string',
                'description'       => 'Identifier of the related reservation at channel manager side.'
            ],

            'is_from_channelmanager' => [
                'type'              => 'boolean',
                'description'       => 'Used to distinguish bookings created from channel manager.',
                'default'           => true
            ]

            /*
                // to be used according to partner_id from Reservation (0 means Cubilis)
                'has_tour_operator'
                'tour_operator_id'
                'tour_operator_ref'
            */

        ];
    }

    public static function canupdate($orm, $ids, $values, $lang) {
        // ignore parent method and allow all changes
        return [];
    }

    public static function candelete($orm, $ids, $lang='fr') {
        // ignore parent method and allow all changes
        return [];
    }

}