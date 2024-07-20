<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\communication;

class TemplatePart extends \communication\TemplatePart {

    public static function getColumns() {

        return [
            'template_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\communication\Template',
                'description'       => "The template the part belongs to.",
                'required'          => true
            ]

        ];
    }

}