<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\pay;

class Payment extends \sale\pay\Payment {

    public static function getColumns() {

        return [

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Funding::getType(),
                'description'       => 'The funding the payment relates to, if any.',
                'onupdate'          => 'sale\pay\Payment::onupdateFundingId'
            ],

            // #memo - required for compatibility with orderPaymentPart
            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the payment part.',
                'default'           => 'paid'
            ]

        ];
    }

}