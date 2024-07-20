<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class Composition extends \sale\booking\Composition {

    public static function getName() {
        return 'Composition';
    }

    public static function getDescription() {
        return "A Composition is an exhaustive list of persons that participate to a sojourn related to a Booking.";
    }

    public static function getColumns() {
        return [

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'The booking the composition relates to.'
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
                'description'       => 'The group the composition relates to.'
            ],

            'composition_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\CompositionItem',
                'foreign_field'     => 'composition_id',
                'description'       => "The items that refer to the composition."
            ]

        ];
    }

}