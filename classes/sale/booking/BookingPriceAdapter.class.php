<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class BookingPriceAdapter extends \sale\booking\BookingPriceAdapter {

    public static function getName() {
        return "Price Adapter";
    }

    public static function getDescription() {
        return "Adapters allow to adapt the final price of the booking lines, either by performing a direct computation, or by using a discount definition.";
    }

    public static function getColumns() {
        return [

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'Booking the adapter relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
                'description'       => 'Booking Line Group the adapter relates to, if any.',
                'ondelete'          => 'cascade'
            ],

            'booking_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BookingLine',
                'description'       => 'Booking Line the adapter relates to, if any.',
                'ondelete'          => 'cascade'
            ]

        ];
    }


/**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overriden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {
        if(isset($values['value'])) {
            $adapters = $om->read(self::getType(), $oids, [ 'type' ], $lang);
            foreach($adapters as $id => $adapter) {
                // #memo - price adapters cannot void a line. To give customer 100% discount, user must use the discount product on a distinct line (KA-Remise-A) with qty of 1 and negative value.
                if($adapter['type'] == 'percent' && $values['value'] >= 0.9999) {
                    return ['value' => ['exceeded_amount' => 'Percent discount cannot be 100%.']];
                }
            }
        }
        return parent::canupdate($om, $oids, $values, $lang);
    }

}