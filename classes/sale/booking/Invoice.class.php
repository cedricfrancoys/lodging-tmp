<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

use identity\Partner;
use lodging\identity\Identity;

class Invoice extends \lodging\finance\accounting\Invoice {

    public static function getLink() {
        return "/booking/#/booking/object.booking_id/invoice/object.id";
    }

    public static function getColumns() {

        return [

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => InvoiceLine::getType(),
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateInvoiceLinesIds'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Booking::getType(),
                'description'       => 'Booking the invoice relates to.',
                'required'          => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Funding::getType(),
                'description'       => 'The funding the invoice originates from, if any.'
            ],

            // override to use booking_id in `calcPaymentReference`
            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Message for identifying payments related to the invoice.',
                'store'             => true
            ],

            'reversed_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => self::getType(),
                'description'       => "Symetrical link between credit note and cancelled invoice, if any.",
                'visible'           => [[['status', '=', 'cancelled']], [['type', '=', 'credit_note']]]
            ],

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Partner::getType(),
                'description'       => "The counter party organization the invoice relates to.",
                'required'          => true,
                'onupdate'          => 'onupdatePartnerId'
            ],

            'customer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Identity::getType(),
                'description'       => 'Identity of the customer (from partner).'
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Indicator of the invoice payment status.",
                'visible'           => ['status', '=', 'invoice'],
                'function'          => 'calcIsPaid',
                'store'             => true
            ]

        ];
    }

    public static function calcIsPaid($om, $ids, $lang) {
        $result = [];
        // #memo - fundings_ids targets all fundings relating to invoice: this includes the installments
        // we need to limit the check to the direct funding, if any
        $invoices = $om->read(self::getType(), $ids, ['status', 'fundings_ids', 'type', 'price', 'funding_id.is_paid'], $lang);
        if($invoices > 0) {
            foreach($invoices as $id => $invoice) {
                $result[$id] = false;
                if($invoice['status'] != 'invoice') {
                    // proforma invoices cannot be marked as paid
                    continue;
                }
                if($invoice['price'] == 0) {
                    // mark the invoice as paid, whatever its funding
                    $result[$id] = true;
                    continue;
                }
                if($invoice['type'] == 'invoice') {
                    $fundings = $om->read(Funding::getType(), $invoice['fundings_ids'], ['paid_amount'], $lang);
                    if($fundings > 0) {
                        $total_paid = 0;
                        foreach($fundings as $funding) {
                            $total_paid += $funding['paid_amount'];
                        }
                        if(round($total_paid, 2) >= round($invoice['price'], 2)) {
                            $result[$id] = true;
                        }
                    }
                }
                elseif($invoice['type'] == 'credit_note') {
                    // #memo - marking arbitrary a funding as paid is accepted for an emitted credit note
                    if($invoice['funding_id.is_paid']) {
                        $result[$id] = true;
                    }
                }
            }
        }
        return $result;
    }

    public static function calcPaymentReference($om, $ids, $lang) {
        $result = [];
        $invoices = $om->read(self::getType(), $ids, ['booking_id.payment_reference']);
        foreach($invoices as $id => $invoice) {
            $result[$id] = $invoice['booking_id.payment_reference'];
        }
        return $result;
    }

    public static function onupdatePartnerId($orm, $ids, $values, $lang) {
        $invoices = $orm->read(self::getType(), $ids, ['partner_id.partner_identity_id'], $lang);

        if($invoices > 0 && count($invoices)) {
            foreach($invoices as $id => $invoice) {
                $orm->update(self::getType(), $id, ['customer_identity_id' => $invoice['partner_id.partner_identity_id']]);
            }
        }
    }
}