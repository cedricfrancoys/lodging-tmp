<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;
use equal\orm\Model;

class SojournType extends Model {

    public static function getName() {
        return 'Sojourn Type';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Sojourn type name.',
                'required'          => true
            ],

            'season_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\season\SeasonCategory',
                'description'       => "Category of seasons used by the center.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Comments detailing the use cases of the type.',
                'multilang'         => true
            ],

            'centers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\identity\Center',
                'foreign_field'     => 'sojourn_type_id',
                'description'       => 'List of centers using the sojourn type by default.'
            ],

            'rental_units_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'foreign_field'     => 'sojourn_type_id',
                'description'       => 'List of rental units using the sojourn type by default.'
            ],

            'bookings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'foreign_field'     => 'sojourn_type_id',
                'description'       => 'List of bookings set to the sojourn type.'
            ]

        ];
    }
}