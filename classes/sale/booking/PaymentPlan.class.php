<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class PaymentPlan extends \sale\pay\PaymentPlan {

    public static function getColumns() {

        return [

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\SojournType',
                'description'       => "The sojourn type that applies to the payment plan."
            ]

        ];
    }

}