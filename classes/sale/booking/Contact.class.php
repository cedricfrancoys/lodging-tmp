<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class Contact extends \sale\booking\Contact {

    public static function getName() {
        return "Contact";
    }

    public static function getDescription() {
        return "Booking contacts are persons involved in the organisation of a booking.";
    }

    public static function getColumns() {

        return [
            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => 'The organisation which the targeted identity is a partner of.',
                'default'           => 1
            ],

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => 'The targeted identity (the partner).',
                'onupdate'          => 'identity\Partner::onupdatePartnerIdentityId',
                'required'          => true
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'Booking the contact relates to.'
            ]

        ];
    }

}