<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

class BookingType extends  \sale\booking\BookingType {

    public static function getColumns() {

        return [
            'days_expiry_option' =>  [
                'description'   => 'The number of days for the option to expire.',
                'type'          => 'integer'
            ],
        ];
    }

}