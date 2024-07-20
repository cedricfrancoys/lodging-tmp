<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;


/**
 * This class overrides the fields on which specific usage constraints are applied, in order to remove those and allow arbitrary values.
 */
class Identity extends \identity\Identity {

    public static function getColumns() {
        return [

            'firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role)."
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.'
            ],

            'email' => [
                'type'              => 'string',
                'description'       => "Identity main email address."
            ],

            'phone' => [
                'type'              => 'string',
                'description'       => "Identity main phone number (mobile or landline)."
            ],

            'address_country' => [
                'type'              => 'string',
                'description'       => 'Country.',
                'onupdate'          => 'onupdateAddressCountry'
            ],

        ];
    }

    public static function onupdateAddressCountry($orm, $ids, $values, $lang) {
        if(isset($values['address_country'])) {
            if(in_array($values['address_country'], ['be', 'Belgium', 'belgium', 'belgique', 'Belgique', 'Belgie', 'België', 'belgie', 'belgië'])) {
                $orm->update(self::getType(), $ids, ['address_country' => 'BE'], $lang);
            }
        }
        elseif(isset($values['address_country'])) {
            if(in_array($values['address_country'], ['nl', 'The Netherlands', 'the netherlands', 'netherlands', 'Netherlands'])) {
                $orm->update(self::getType(), $ids, ['address_country' => 'NL'], $lang);
            }
        }
        elseif(isset($values['address_country'])) {
            if(in_array($values['address_country'], ['fr', 'France', 'france'])) {
                $orm->update(self::getType(), $ids, ['address_country' => 'FR'], $lang);
            }
        }
    }

    // remove all constraints
    public static function getConstraints() {
        return [];
    }

}
