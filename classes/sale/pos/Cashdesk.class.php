<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\pos;


class Cashdesk extends \sale\pos\Cashdesk {

    public static function getColumns() {

        return [
            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => \lodging\identity\Center::getType(),
                'description'       => "The center the desk relates to.",
                'required'          => true,
                'ondelete'          => 'cascade'         // delete cashdesk when parent Center is deleted
            ],

            'establishment_id' => [
                'type'              => 'alias',
                'alias'             => 'center_id',
            ]
        ];
    }

}