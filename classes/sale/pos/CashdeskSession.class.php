<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\pos;

class CashdeskSession extends \sale\pos\CashdeskSession {

    public static function getColumns() {

        return [

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\User',
                'description'       => 'User whom performed the log entry.',
                'required'          => true
            ],

            'cashdesk_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\pos\Cashdesk',
                'description'       => 'Cash desk the log entry belongs to.',
                'required'          => true,
                'onupdate'          => 'onupdateCashdeskId'
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center the desk relates to (from cashdesk)."
            ],

            'orders_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\pos\Order',
                'foreign_field'     => 'session_id',
                'description'       => 'The orders that relate to the session.'
            ],

            'link_sheet' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'description'       => 'URL for generating the PDF version of the report.',
                'function'          => 'calcLinkSheet',
                'readonly'          => true
            ],

            'operations_ids'  => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\pos\Operation',
                'foreign_field'     => 'session_id',
                'ondetach'          => 'delete',
                'description'       => 'List of operations performed during session.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'closed'
                ],
                'description'       => 'Current status of the session.',
                'onupdate'          => 'onupdateStatus',
                'default'           => 'pending'
            ]

        ];
    }

    public static function onupdateCashdeskId($om, $ids, $values, $lang) {
        $sessions = $om->read(self::getType(), $ids, ['cashdesk_id.center_id'], $lang);

        if($sessions > 0) {
            foreach($sessions as $id => $session) {
                $om->update(self::getType(), $id, ['center_id' => $session['cashdesk_id.center_id']], $lang);
            }
        }

        $om->callonce(\sale\pos\CashdeskSession::getType(), 'onupdateCashdeskId', $ids, $values, $lang);
    }

    public static function calcLinkSheet($om, $ids, $lang) {
        $result = [];
        foreach($ids as $id) {
            $result[$id] = '/?get=lodging_sale_pos_print-cashdeskSession-day&id='.$id;
        }
        return $result;
    }

    public static function onupdateStatus($om, $oids, $values, $lang) {
        // upon session closing, create additional operation if there is a delta in cash amount
        if(isset($values['status']) && $values['status'] == 'closed') {
            $sessions = $om->read(self::getType(), $oids, ['cashdesk_id', 'user_id', 'amount_opening', 'amount_closing', 'operations_ids.amount'], $lang);
            if($sessions > 0) {
                foreach($sessions as $sid => $session) {
                    $total_cash = 0.0;
                    foreach($session['operations_ids.amount'] as $oid => $operation) {
                        $total_cash += $operation['amount'];
                    }
                    // compute the difference (if any) between expected cash and actual cash in the cashdesk
                    $expected_cash = $total_cash + $session['amount_opening'];
                    $delta = $session['amount_closing'] - $expected_cash;
                    if($delta != 0) {
                        // create a new move with the delta
                        $om->create(Operation::getType(), [
                            'cashdesk_id'   => $session['cashdesk_id'],
                            'session_id'    => $sid,
                            'user_id'       => $session['user_id'],
                            'amount'        => $delta,
                            'type'          => 'move',
                            'description'   => 'cashdesk closing'
                        ], $lang);

                        $providers = \eQual::inject(['dispatch']);

                        /** @var \equal\dispatch\Dispatcher $dispatch */
                        $dispatch = $providers['dispatch'];

                        $dispatch->dispatch('lodging.pos.close-discrepancy', 'lodging\sale\pos\CashdeskSession', $sid, 'warning');
                    }
                }
            }
        }
    }

}