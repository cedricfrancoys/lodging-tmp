<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\pay;

class Funding extends \sale\pay\Funding {

    public static function getColumns() {

        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\finance\accounting\Invoice',
                'description'       => 'The invoice targeted by the funding, if any.',
                'visible'           => [ ['type', '=', 'invoice'] ]
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => "The center office the booking relates to.",
                'required'          => true
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => Payment::getType(),
                'foreign_field'     => 'funding_id'
            ]

        ];
    }

    /**
     * Hook invoked before object update for performing object-specific additional operations.
     * Update the scheduled tasks related to the fundings.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @param  array                       $values     Associative array holding the new values that have been assigned.
     * @param  string                      $lang       Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdate($om, $ids, $values, $lang) {
        $cron = $om->getContainer()->get('cron');

        if(isset($values['due_date'])) {
            foreach($ids as $fid) {
                // remove any previously scheduled task
                $cron->cancel("booking.funding.overdue.{$fid}");
                // setup a scheduled job upon funding overdue
                $cron->schedule(
                    // assign a reproducible unique name
                    "booking.funding.overdue.{$fid}",
                    // remind on day following due_date
                    $values['due_date'] + 86400,
                    'lodging_funding_check-payment',
                    [ 'id' => $fid ]
                );
            }
        }
        parent::onupdate($om, $ids, $values, $lang);
    }


    /**
     * Check wether the identity can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $fundings = $om->read(self::getType(), $ids, [ 'is_paid', 'due_amount', 'paid_amount', 'type', 'invoice_id', 'invoice_id.status', 'invoice_id.type', 'payments_ids' ]);

        if($fundings > 0) {
            foreach($fundings as $id => $funding) {
                if( $funding['is_paid'] || $funding['paid_amount'] != 0 || ($funding['payments_ids'] && count($funding['payments_ids']) > 0) ) {
                    return ['payments_ids' => ['non_removable_funding' => 'Funding paid or partially paid cannot be deleted.']];
                }
                if( $funding['due_amount'] > 0 && $funding['type'] == 'invoice' && is_null($funding['invoice_id']) && $funding['invoice_id.status'] == 'invoice' && $funding['invoice_id.type'] == 'invoice') {
                    return ['invoice_id' => ['non_removable_funding' => 'Funding relating to an invoice cannot be deleted.']];
                }
            }
        }
        return [];
        // ignore parent
        // return parent::candelete($om, $ids);
    }
}