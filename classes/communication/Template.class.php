<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\communication;

class Template extends \communication\Template {

    public static function getColumns() {
        return [
            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\communication\TemplateCategory',
                'description'       => "The category the template belongs to.",
                'onupdate'          => 'onupdateCategoryId',
                'required'          => true
            ],

            'parts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\communication\TemplatePart',
                'foreign_field'     => 'template_id',
                'description'       => 'List of templates parts related to the template.'
            ],

            'attachments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\communication\TemplateAttachment',
                'foreign_field'     => 'template_id',
                'description'       => 'List of attachments related to the template, if any.'
            ]
        ];
    }
}