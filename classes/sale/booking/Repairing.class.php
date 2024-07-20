<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

use lodging\sale\booking\Consumption;

class Repairing extends \sale\booking\Repairing {


    public static function getColumns() {
        return [

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => 'The center the repairing relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'         // delete repairing when parent center is deleted
            ],

            'repairs_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Repair',
                'foreign_field'     => 'repairing_id',
                'description'       => 'Consumptions related to the booking.',
                'ondetach'          => 'delete'
            ],

            'rental_units_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'lodging\realestate\RentalUnit',
                'foreign_field'     => 'repairings_ids',
                'rel_table'         => 'sale_rel_repairing_rentalunit',
                'rel_foreign_key'   => 'rental_unit_id',
                'rel_local_key'     => 'repairing_id',
                'description'       => 'List of rental units assigned to the scheduled repairing.',
                'onupdate'          => 'onupdateRentalUnitsIds'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "",
                'default'           => time(),
                'onupdate'          => 'onupdateDateFrom'
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "",
                'default'           => time(),
                'onupdate'          => 'onupdateDateTo'
            ]

        ];
    }

    public static function onupdateRentalUnitsIds($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), '_updateRepairs', $ids, [], $lang);
    }

    public static function onupdateDateFrom($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), '_updateRepairs', $ids, [], $lang);
    }

    public static function onupdateDateTo($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), '_updateRepairs', $ids, [], $lang);
    }

    public static function _updateRepairs($om, $ids, $values, $lang) {
        // generate consumptions
        $repairings = $om->read(self::getType(), $ids, ['repairs_ids', 'center_id', 'date_from', 'date_to', 'rental_units_ids'], $lang);
        // reset time_from and time_to
        $om->update(self::getType(), $ids, ['time_from' => null, 'time_to' => null], $lang);
        if($repairings > 0) {
            foreach($repairings as $id => $repairing) {
                // remove existing repairs
                $repairs_ids = array_map(function($a) { return "-$a";}, $repairing['repairs_ids']);
                $om->update(self::getType(), $id, ['repairs_ids' => $repairs_ids]);
                $nb_days = floor( ($repairing['date_to'] - $repairing['date_from']) / (60*60*24) ) + 1;
                list($day, $month, $year) = [ date('j', $repairing['date_from']), date('n', $repairing['date_from']), date('Y', $repairing['date_from']) ];
                for($i = 0; $i < $nb_days; ++$i) {
                    $c_date = mktime(0, 0, 0, $month, $day+$i, $year);
                    foreach($repairing['rental_units_ids'] as $rental_unit_id) {
                        $fields = [
                            'repairing_id'          => $id,
                            'center_id'             => $repairing['center_id'],
                            'date'                  => $c_date,
                            'rental_unit_id'        => $rental_unit_id
                        ];
                        $om->create('lodging\sale\booking\Repair', $fields, $lang);
                    }
                }
                // #todo - check-contingencies (! at this stage, we don't know the previously assigned rental units)
            }
        }
    }

    public static function canupdate($om, $ids, $values, $lang) {
        if(isset($values['center_id']) || isset($values['date_from'])  ||  isset($values['date_to']) || isset($values['rental_units_ids']) ) {
            $repairings = $om->read(self::getType(), $ids, ['id','center_id', 'date','date_from', 'date_to', 'rental_units_ids'], $lang);

            if($repairings > 0) {
                foreach($repairings as $id => $repairing) {
                    $center_id = (isset($values['center_id']))?$values['center_id']:$repairing['center_id'];
                    $date_from = (isset($values['date_from']))?$values['date_from']:$repairing['date_from'];
                    $date_to = (isset($values['date_to']))?$values['date_to']:$repairing['date_to'];
                    $rental_units_ids = (isset($values['rental_units_ids']))?$values['rental_units_ids']:$repairing['rental_units_ids'];

                    foreach($rental_units_ids as $rental_unit_id) {
                        $result = Consumption::search([
                                [
                                    ['date', '>=', $date_from],
                                    ['date', '<=', $date_to] ,
                                    ['rental_unit_id' , '=' , $rental_unit_id],
                                    ['center_id', '=', $center_id],
                                    ['repairing_id', '<>', $repairing['id'] ]

                                ],
                                [
                                    ['date', '>=', $date_from],
                                    ['date', '<=', $date_to] ,
                                    ['rental_unit_id' , '=' , $rental_unit_id],
                                    ['center_id', '=', $center_id],
                                    ['booking_id', '>', 0 ]
                                ]
                            ])
                            ->get(true);

                        if(count($result)) {
                            return ['id' => ['non_editable' => 'The change is not allowed because there is another consumption.']];
                        }
                    }
                }
            }
        }

        return parent::canupdate($om, $ids, $values, $lang);
    }
}