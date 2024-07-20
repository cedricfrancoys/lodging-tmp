<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\season;


class Season extends \sale\season\Season {

    public static function getColumns() {

        return [

            'center_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterCategory',
                'description'       => "Center category targeted by season.",
                'required'          => true
            ]

        ];
    }
}