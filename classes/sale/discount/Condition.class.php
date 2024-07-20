<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\discount;


class Condition extends \sale\discount\Condition {

    public static function getColumns() {

        return [

            'operand' => [
                'type'              => 'string',
                'selection'         => [
                        'season',
                        'nb_pers',
                        'nb_children',
                        'nb_adults',
                        'duration',
                        'count_booking_24'
                    ],
                'required'          => true
            ],

            'discount_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\discount\Discount',
                'description'       => 'The discount list the discount belongs to.',
                'required'          => true
            ]

        ];
    }

}