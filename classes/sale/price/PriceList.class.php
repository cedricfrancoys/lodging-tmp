<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\price;

class PriceList extends \sale\price\PriceList {

    public static function getColumns() {
        return [
            // #memo - once published, a pricelist shouldn't be editable
            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',              // list is "under construction" (to be confirmed)
                    'published',            // completed and ready to be used
                    'paused',               // (temporarily) on hold (not to be used)
                    'closed'                // can no longer be used (similar to archive)
                ],
                'description'       => 'Status of the list.',
                'onupdate'          => 'onupdateStatus',
                'default'           => 'pending'
            ]
        ];
    }

    public static function onupdateStatus($om, $ids, $values, $lang) {
        $pricelists = $om->read(self::getType(), $ids, ['status', 'prices_ids']);
        $om->update(self::getType(), $ids, ['is_active' => null]);
        // immediate re-compute (required by subsequent re-computations of prices is_active flag)
        $om->read(self::getType(), $ids, ['is_active']);

        if($pricelists > 0) {
            $providers = \eQual::inject(['cron']);
            $cron = $providers['cron'];

            foreach($pricelists as $pid => $pricelist) {
                if($pricelist['status'] == 'published') {
                    // add a task to the CRON for updating status of bookings waiting for the pricelist
                    $cron->schedule(
                        "booking.is_tbc.confirm",
                        time() + 60,
                        'lodging_pricelist_check-bookings',
                        [ 'id' => $pid ]
                    );
                }
                // immediate re-compute prices is_active flag
                $om->update('sale\price\Price', $pricelist['prices_ids'], ['is_active' => null]);
                $om->read('sale\price\Price', $pricelist['prices_ids'], ['is_active']);
            }
        }
    }

}