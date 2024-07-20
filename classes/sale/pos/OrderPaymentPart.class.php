<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\pos;
use lodging\sale\booking\Booking;
use lodging\sale\booking\Funding;
use lodging\sale\booking\Invoice;

class OrderPaymentPart extends \sale\pos\OrderPaymentPart {

    public function getTable() {
        return 'sale_pos_orderpaymentpart';
    }

    public static function getColumns() {
        return [

            'order_payment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => OrderPayment::getType(),
                'description'       => 'The order payment the part relates to.',
                'ondelete'          => 'cascade',
                'onupdate'          => 'onupdateOrderPaymentId'
            ],

            'order_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Order::getType(),
                'description'       => 'The order the part relates to (based on payment).'
            ],

            'payment_method' => [
                'type'              => 'string',
                'selection'         => [
                    'cash',                 // cash money
                    'bank_card',            // electronic payment with bank (or credit) card
                    'booking',              // payment through addition to the final (balance) invoice of a specific booking
                    'voucher'               // gift, coupon, or tour-operator voucher
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ],
                'default'           => 'cash',
                'onupdate'          => 'onupdatePaymentMethod'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'Booking the payment part relates to.',
                'ondelete'          => 'null'
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Funding::getType(),
                'description'       => 'The funding the payment relates to, if any.',
                'onupdate'          => 'onupdateFundingId'
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => 'Center office related to the statement (from order_id).'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',      // payment part hasn't been validated yet
                    'paid'          // amount has been received (cannot be undone)
                ],
                'description'       => 'Current status of the payment part.',
                'default'           => 'pending',
                'onupdate'          => 'onupdateStatus'
            ]

        ];
    }


    /**
     * @param \equal\orm\ObjectManager  $om Instance of the ObjectManager service.
     */
    public static function onupdatePaymentMethod($om, $ids, $values, $lang) {
        // upon update of the payment method, adapt parent payment and related line
        $parts = $om->read(self::getType(), $ids, ['payment_method', 'booking_id', 'order_payment_id'], $lang);
        if($parts > 0) {
            foreach($parts as $id => $part) {
                if($part['payment_method'] == 'booking') {
                    $om->update(OrderPayment::getType(), $part['order_payment_id'], ['has_booking' => true, 'booking_id' => $part['booking_id']], $lang);
                }
                else {
                    $om->update(OrderPayment::getType(), $part['order_payment_id'], ['has_booking' => false, 'booking_id' => null], $lang);
                }
            }
        }
    }

    /**
     * Retrieve partner_id, funding_id and center_office_id from parent order.
     */
    public static function onupdateOrderPaymentId($om, $ids, $values, $lang) {
        $parts = $om->read(self::getType(), $ids, ['order_payment_id.order_id'], $lang);
        if($parts > 0) {
            foreach($parts as $id => $part) {
                $orders = $om->read(Order::getType(), $part['order_payment_id.order_id'], ['id', 'customer_id', 'funding_id', 'center_id.center_office_id']);
                if($orders > 0 && count($orders)) {
                    $order = reset($orders);
                    $om->update(self::getType(), $id, ['order_id' => $order['id'],'partner_id' => $order['customer_id'], 'funding_id' => $order['funding_id'], 'center_office_id' => $order['center_id.center_office_id']], $lang);
                }
            }
        }
    }

    public static function onupdateFundingId($om, $ids, $values, $lang) {
        $parts = $om->read(self::getType(), $ids, ['funding_id.booking_id'], $lang);
        if($parts > 0) {
            foreach($parts as $id => $part) {
                $om->update(self::getType(), $id, ['booking_id' => $part['funding_id.booking_id']], $lang);
            }
        }
    }

    /**
     * Force related funding to reset computed fields when status is updated.
     */
    public static function onupdateStatus($om, $ids, $values, $lang) {
        $parts = $om->read(self::getType(), $ids, ['order_payment_id', 'funding_id', 'funding_id.booking_id', 'funding_id.invoice_id'], $lang);
        if($parts > 0) {
            $map_bookings_ids = [];
            $map_invoices_ids = [];
            foreach($parts as $id => $part) {
                if($part['funding_id']) {
                    if($part['funding_id.booking_id']) {
                        $map_bookings_ids[$part['funding_id.booking_id']] = true;
                    }
                    if($part['funding_id.invoice_id']) {
                        $map_invoices_ids[$part['funding_id.invoice_id']] = true;
                    }
                    $om->update(Funding::getType(), $part['funding_id'], ['is_paid' => null, 'paid_amount' => null], $lang);
                }
                // not stored computed field
                $om->update(OrderPayment::getType(), $part['order_payment_id'], ['total_paid' => null], $lang);
            }
            // #todo - this should be done when whole order is marked as paid
            $om->call(Booking::getType(), 'updateStatusFromFundings', array_keys($map_bookings_ids), [], $lang);
            $om->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null], $lang);
            $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
        }
    }

    public static function candelete($om, $ids) {
        $parts = $om->read(self::getType(), $ids, [ 'order_id.status' ]);

        if($parts > 0) {
            foreach($parts as $id => $part) {
                if($part['order_id.status'] == 'paid') {
                    return ['status' => ['non_removable' => 'Payments from paid orders cannot be deleted.']];
                }
            }
        }
        // ignore parent `candelete()`
        return [];
    }
}