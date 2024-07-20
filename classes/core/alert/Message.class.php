<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\core\alert;


class Message extends \core\alert\Message {

    public static function getColumns() {
        return [

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => 'Office the message relates to (for targeting the users).',
                'store'             => true,
                'function'          => 'calcCenterOfficeId'
            ],
            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'function'          => 'calcAlert'
            ],
        ];
    }

    // We hijack the group_id to target the Center Offices.
    public static function calcCenterOfficeId($om, $oids, $lang) {
        $result = [];
        $messages = $om->read(self::getType(), $oids, ['group_id']);
        foreach($messages as $mid => $message) {
            $result[$mid] = $message['group_id'];
        }
        return $result;
    }

    public static function calcAlert($om, $oids, $lang) {
        $result = [];
        $messages = $om->read(self::getType(), $oids, ['severity']);

        foreach($messages as $oid => $message) {
            switch($message['severity']) {
                case 'notice':
                     $result[$oid] = 'info';
                    break;
                case 'warning':
                     $result[$oid] = 'warn';
                    break;
                case 'important':
                     $result[$oid] = 'major';
                    break;
                case 'error':
                default:
                     $result[$oid] = 'error';
                    break;
            }
        }
        return $result;
    }


}