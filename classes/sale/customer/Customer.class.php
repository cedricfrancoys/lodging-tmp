<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\customer;

class Customer extends \sale\customer\Customer {

    public static function getColumns() {

        return [

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => 'The targeted identity (the partner).',
                'onupdate'          => 'onupdatePartnerIdentityId',
                'required'          => true
            ],

            'is_tour_operator' => [
                'type'              => 'boolean',
                'description'       => 'Mark the customer as a Tour Operator.',
                'default'           => false
            ],

            // #memo  count must be relative to booking not customer
            'count_booking_12' => [
                'type'              => 'computed',
                'deprecated'        => true,
                'result_type'       => 'integer',
                'function'          => 'calcCountBooking12',
                'description'       => 'Number of bookings made during last 12 months (one year).'
            ],

            'count_booking_24' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCountBooking24',
                'description'       => 'Number of bookings made during last 24 months (2 years).'
            ],

            'bookings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'foreign_field'     => 'customer_id',
                'description'       => "The bookings history of the customer.",
            ],

            'email_secondary' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'email',
                'description'       => "Identity secondary email address.",
                'function'          => 'calcEmailSecondary'
            ]


        ];
    }

    /**
     * Check wether the customer can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $customers = $om->read(self::getType(), $ids, [ 'bookings_ids' ]);

        if($customers > 0) {
            foreach($customers as $id => $customer) {
                if($customer['bookings_ids'] && count($customer['bookings_ids']) > 0) {
                    return ['bookings_ids' => ['non_removable_customer' => 'Customers relating to one or more bookings cannot be deleted.']];
                }
            }
        }
        return parent::candelete($om, $ids);
    }

    /**
     * Computes the number of bookings made by the customer during the last 12 months.
     *
     */
    public static function calcCountBooking12($om, $ids, $lang) {
        $result = [];
        $time = time();
        $from = mktime(0, 0, 0, date('m', $time)-12, date('d', $time), date('Y', $time));
        foreach($ids as $id) {
            $bookings_ids = $om->search('sale\booking\Booking', [
                ['customer_id', '=', $id],
                ['date_from', '>=', $from],
                ['is_cancelled', '=', false],
                ['status', 'not in', ['quote', 'option']]
            ]);
            $result[$id] = count($bookings_ids);
        }
        return $result;
    }

    /**
     * Computes the number of bookings made by the customer during the last two years.
     *
     */
    public static function calcCountBooking24($om, $ids, $lang) {
        $result = [];
        $time = time();
        $from = mktime(0, 0, 0, date('m', $time)-24, date('d', $time), date('Y', $time));
        foreach($ids as $id) {
            $bookings_ids = $om->search('sale\booking\Booking', [
                ['customer_id', '=', $id],
                ['date_from', '>=', $from],
                ['is_cancelled', '=', false],
                ['status', 'not in', ['quote', 'option']]
            ]);
            $result[$id] = count($bookings_ids);
        }
        return $result;
    }

    public static function calcEmailSecondary($om, $ids, $lang) {
        $result = [];
        $customers = $om->read(self::getType(), $ids, ['partner_identity_id.email_secondary']);
        foreach($customers as $id => $customer) {
            $result[$id] = $customer['partner_identity_id.email_secondary'];
        }
        return $result;
    }
}