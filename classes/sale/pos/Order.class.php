<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\pos;

class Order extends \sale\pos\Order {

    public static function getColumns() {

        return [

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Funding',
                'description'       => 'The booking funding that relates to the order, if any.',
                'visible'           => ['has_funding', '=', true],
                'onupdate'          => 'onupdateFundingId'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'Booking the order relates to.',
                'ondelete'          => 'null'
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\finance\accounting\Invoice',
                'description'       => 'The invoice that relates to the order, if any.',
                'visible'           => ['has_invoice', '=', true],
                'ondelete'          => 'null',
                'onupdate'          => 'onupdateInvoiceId'
            ],

            'session_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\pos\CashdeskSession',
                'description'       => 'The session the order belongs to.',
                'onupdate'          => 'onupdateSessionId',
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center the desk relates to (from session)."
            ],

            'order_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\pos\OrderLine',
                'foreign_field'     => 'order_id',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateOrderLinesIds',
                'description'       => 'The lines that relate to the order.'
            ],

            'order_payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\pos\OrderPayment',
                'foreign_field'     => 'order_id',
                'ondetach'          => 'delete',
                'description'       => 'The payments that relate to the order.'
            ],

            'order_payment_parts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\pos\OrderPaymentPart',
                'foreign_field'     => 'order_id',
                'ondetach'          => 'delete',
                'description'       => 'The payments parts that relate to the order.'
            ],

            // override onupdate event (uses local onupdateStatus)
            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',           // consumptions (lines) are being added to the order
                    'payment',           // a waiter is proceeding to the payment
                    'paid'               // order is closed and payment has been received
                ],
                'description'       => 'Current status of the order.',
                'onupdate'          => 'onupdateStatus',
                'default'           => 'pending'
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the order as exported (invoiced + payment exported).',
                'default'           => false
            ]
        ];
    }

    /**
     * Handler called after each status update.
     * Upon payment of the order, update related funding and invoice, if any.
     *
     * @param \equal\orm\ObjectManager  $om Instance of the ObjectManager service.
     */
    public static function onupdateStatus($om, $ids, $values, $lang) {
        if(!isset($values['status'])) {
            return;
        }

        switch($values['status']) {
            case 'paid':
                $orders = $om->read(self::getType(), $ids, ['has_invoice', 'has_funding', 'funding_id.type', 'funding_id.invoice_id', 'center_id.center_office_id'], $lang);
                if($orders > 0) {
                    foreach($orders as $oid => $order) {
                        if($order['has_funding']) {
                            if($order['funding_id.type'] == 'invoice') {
                                // #memo - status of the related invoice/proforma must be changed
                                // $om->update(\lodging\finance\accounting\Invoice::getType(), $order['funding_id.invoice_id'], ['status' => 'invoice', 'is_paid' => null], $lang);
                                $om->update(\lodging\finance\accounting\Invoice::getType(), $order['funding_id.invoice_id'], ['is_paid' => null], $lang);
                            }
                        }
                        // no funding and no invoice: generate stand alone accounting entries
                        elseif(!$order['has_invoice']) {

                            // filter lines that do not relate to a booking (added as 'extra' services)
                            $order_lines_ids = $om->search(
                                OrderLine::getType(),
                                [['order_id', '=', $oid], ['has_booking', '=', false]]
                            );

                            // generate accounting entries
                            $orders_accounting_entries = self::_generateAccountingEntries($om, [$oid], $order_lines_ids, $lang);
                            if(!isset($orders_accounting_entries[$oid]) || count($orders_accounting_entries[$oid]) === 0) {
                                continue;
                            }

                            $order_accounting_entries = $orders_accounting_entries[$oid];

                            $res = $om->search(
                                \lodging\finance\accounting\AccountingJournal::getType(),
                                [['center_office_id', '=', $order['center_id.center_office_id']], ['type', '=', 'bank_cash']]
                            );
                            $journal_id = reset($res);
                            if(!$journal_id) {
                                continue;
                            }

                            // create new entries objects and assign to the sale journal relating to the center_office_id
                            foreach($order_accounting_entries as $entry) {
                                $entry['journal_id'] = $journal_id;
                                $om->create(\finance\accounting\AccountingEntry::getType(), $entry);
                            }
                        }
                    }
                }
                break;
            case 'pending':
            case 'payment':
                $orders = $om->read(self::getType(), $ids, ['has_invoice', 'has_funding'], $lang);
                if($orders > 0) {
                    foreach($orders as $oid => $order) {
                        if(!$order['has_funding'] && !$order['has_invoice']) {
                            $account_entry_ids = $om->search(\finance\accounting\AccountingEntry::getType(), ['order_id', '=', $oid]);
                            if(!empty($account_entry_ids)) {
                                $om->delete(\finance\accounting\AccountingEntry::getType(), $account_entry_ids, true);
                            }
                        }
                    }
                }
                break;
        }
    }

    /**
     * Assign default customer_id based on the center that the session relates to.
     */
    public static function onupdateSessionId($om, $ids, $values, $lang) {
        // retrieve default customers assigned to centers
        $orders = $om->read(self::getType(), $ids, ['session_id.center_id', 'session_id.center_id.pos_default_customer_id'], $lang);

        if($orders > 0) {
            foreach($orders as $id => $order) {
                $om->update(self::getType(), $id, ['center_id' => $order['session_id.center_id'], 'customer_id' => $order['session_id.center_id.pos_default_customer_id'] ], $lang);
            }
        }

        $om->callonce(parent::getType(), 'onupdateSessionId', $ids, $values, $lang);
    }

    public static function onupdateFundingId($om, $ids, $values, $lang) {
        $orders = $om->read(self::getType(), $ids, ['funding_id'], $lang);
        if($orders > 0) {
            foreach($orders as $id => $order) {
                $om->update(self::getType(), $id, ['has_funding' => ($order['funding_id'] > 0)], $lang);
            }
        }
    }

    public static function onupdateInvoiceId($om, $ids, $values, $lang) {
        $orders = $om->read(self::getType(), $ids, ['invoice_id'], $lang);
        if($orders > 0) {
            foreach($orders as $id => $order) {
                $om->update(self::getType(), $id, ['has_invoice' => ($order['invoice_id'] > 0)], $lang);
            }
        }
    }

    /**
     * Check wether an object can be deleted.
     *
     * @param  ObjectManager    $om         ObjectManager instance.
     * @param  array            $ids       List of objects identifiers.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $orders = $om->read(self::getType(), $ids, [ 'status' ]);

        if($orders > 0) {
            foreach($orders as $oid => $order) {
                if($order['status'] == 'paid') {
                    return ['status' => ['non_removable' => 'Paid orders cannot be deleted.']];
                }
            }
        }
        // ignore parent `candelete()`
        return [];
    }
}