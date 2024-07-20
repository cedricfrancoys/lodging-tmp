<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace lodging\sale\booking;

use equal\orm\Model;

class BookingHistoryEntry extends Model {

    public static function getColumns() {
        return [
            'historic_identifier' => [
                'type'           => 'string',
                'description'    => 'Historic identifier "M_Resa.Code_Resa".',
                'required'       => true
            ],

            'name' => [
                'type'           => 'string',
                'description'    => 'Historic name "M_Resa.Lib_Resa".'
            ],

            'date_create' => [
                'type'           => 'date',
                'description'    => 'Historic date_create "M_Resa.Date_Creation".'
            ],

            'date_from' => [
                'type'           => 'date',
                'description'    => 'Historic date_from "M_Resa.Date_Debut".'
            ],

            'date_to' => [
                'type'           => 'date',
                'description'    => 'Historic date_to "M_Resa.Date_Fin".'
            ],

            'total' => [
                'type'           => 'float',
                'usage'          => 'amount/money:4',
                'description'    => 'Historic price excluding vat "M_Resa.MtHt".',
                'default'        => 0
            ],

            'price' => [
                'type'           => 'float',
                'usage'          => 'amount/money:4',
                'description'    => 'Historic price including vat "M_Resa.Tot_Paye".',
                'default'        => 0
            ],

            'organisation_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'lodging\identity\Identity',
                'description'    => 'The organisation to which the booking relates to.',
                'required'       => true
            ],

            'center_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'lodging\identity\Center',
                'description'    => 'The center to which the booking relates to.',
                'required'       => true
            ],

            'center_type' => [
                'type'           => 'string',
                'selection'      => ['GA', 'GG'],
                'description'    => 'Type of the center.'
            ],

            'nb_pers' => [
                'type'           => 'integer',
                'description'    => 'Historic persons qty "M_Resa.Nbre_Pers".'
            ],

            'nb_nights' => [
                'type'           => 'integer',
                'description'    => 'Quantity of nights between "M_Resa.Date_Debut" and "M_Resa.Date_Fin".'
            ],

            'nb_rental_units' => [
                'type'           => 'integer',
                'description'    => 'Historic qty of rental units, found in historic data "M_Resa_Compo".'
            ],

            'nb_pers_nights' => [
                'type'           => 'integer',
                'description'    => 'Qty of persons times qty of nights.'
            ],

            'nb_room_nights' => [
                'type'           => 'integer',
                'description'    => 'Qty of rental units times qty of nights.'
            ],

            'customer_name' => [
                'type'           => 'string',
                'description'    => 'Historic booking customer name "R_Client.Nom_Client".'
            ],

            'customer_street' => [
                'type'           => 'string',
                'description'    => 'Historic booking customer name "R_Client.Adr3_Client".'
            ],

            'customer_zip' => [
                'type'           => 'string',
                'description'    => 'Historic booking customer name "R_Client.CP_Client".'
            ],

            'customer_city' => [
                'type'           => 'string',
                'description'    => 'Historic booking customer name "R_Client.Ville_Client".'
            ],

            'customer_country_code' => [
                'type'           => 'string',
                'description'    => 'Historic booking customer name "R_Client.Pays_Client".'
            ],

            'customer_language_code' => [
                'type'           => 'string',
                'description'    => 'Historic customer language code "R_Client.Langue_Client"'
            ],

            'customer_rate_class_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'sale\customer\RateClass',
                'description'    => 'The rate class that applies to the payment plan.'
            ],
        ];
    }
}
