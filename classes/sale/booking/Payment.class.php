<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

use lodging\sale\booking\Funding;
use lodging\sale\booking\Invoice;

class Payment extends \lodging\sale\pay\Payment {

    public static function getColumns() {

        return [
            'booking_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcBookingId',
                'foreign_object'    => Booking::getType(),
                'description'       => 'The booking the payment relates to, if any (computed).',
                'store'             => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Funding::getType(),
                'description'       => 'The funding the payment relates to, if any.',
                'onupdate'          => 'onupdateFundingId'
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'function'          => 'calcCenterOfficeId',
                'description'       => 'Center office related to the statement (from statement_line_id).',
                'store'             => true
            ],

            'statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BankStatementLine',
                'description'       => 'The bank statement line the payment relates to, if any.',
                'visible'           => [ ['payment_origin', '=', 'bank'] ],
                'onupdate'          => 'onupdateStatementLineId'
            ],

            'order_payment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\pos\OrderPayment',
                'description'       => 'The order payment the payment relates to, if any.',
                'visible'           => [ ['payment_origin', '=', 'cashdesk'] ]
            ],

            'payment_origin' => [
                'type'              => 'string',
                'selection'         => [
                    // money was received at the cashdesk
                    'cashdesk',
                    // money was received on a bank account
                    'bank',
                    // money was received online, through a PSP
                    'online'
                ],
                'description'       => "Origin of the received money.",
                'default'           => 'bank'
            ],

            'is_manual' => [
                'type'              => 'boolean',
                'description'       => 'Payment was created manually at the checkout directly in the booking (not through cashdesk).',
                'default'           => false
            ],

            'has_psp' => [
                'type'              => 'boolean',
                'description'       => 'Flag to tell payment was done through a Payment Service Provider.',
                'default'           => false
            ],

            'psp_fee_amount' => [
                'type'              => 'float',
                'description'       => 'Amount of the fee of the Service Provider.'
            ],

            'psp_fee_currency' => [
                'type'              => 'string',
                'description'       => 'Currency of the PSP fee amount.',
                'default'           => 'EUR'
            ],

            'psp_type' => [
                'type'              => 'string',
                'description'       => 'Identification string of the payment service provider (ex. \'stripe\').'
            ],

            'psp_ref' => [
                'type'              => 'string',
                'description'       => 'Reference allowing to retrieve the payment details from PSP.'
            ]
        ];
    }


    public static function calcBookingId($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['funding_id.booking_id']);
        foreach($payments as $id => $payment) {
            if(isset($payment['funding_id.booking_id'])) {
                $result[$id] = $payment['funding_id.booking_id'];
            }
        }
        return $result;
    }

    public static function calcCenterOfficeId($om, $ids, $lang) {
        $result = [];
        $payments = $om->read(self::getType(), $ids, ['statement_line_id.center_office_id']);
        if($payments > 0 && count($payments)) {
            foreach($payments as $id => $payment) {
                if(isset($payment['statement_line_id.center_office_id'])) {
                    $result[$id] = $payment['statement_line_id.center_office_id'];
                }
            }
        }
        return $result;
    }

    public static function onupdateStatementLineId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['statement_line_id.bank_statement_id', 'statement_line_id.remaining_amount']);
        if($payments > 0 && count($payments)) {
            foreach($payments as $id => $payment) {
                $om->update(self::getType(), $id, ['amount' => $payment['statement_line_id.remaining_amount']]);
                // #memo - status of BankStatement is computed from statement lines, and status of BankStatementLine depends on payments
                $om->update(BankStatement::getType(), $payment['statement_line_id.bank_statement_id'], ['status' => null]);
            }
        }
    }

    /**
     * Check newly assigned funding and create an invoice for long term downpayments.
     * From an accounting perspective, if a downpayment has been received and is not related to an invoice yet,
     * it must relate to a service that will be delivered within the current year.
     * If the service will be delivered the downpayment is converted into an invoice.
     *
     * #memo - This cannot be undone.
     */
    public static function onupdateFundingId($om, $ids, $values, $lang) {
        $payments = $om->read(self::getType(), $ids, ['funding_id', 'funding_id.booking_id', 'funding_id.invoice_id', 'funding_id.booking_id.date_from', 'funding_id.type']);

        if($payments > 0) {
            $map_bookings_ids = [];
            $map_invoices_ids = [];
            foreach($payments as $pid => $payment) {
                if($payment['funding_id']) {
                    if($payment['funding_id.booking_id']) {
                        $map_bookings_ids[$payment['funding_id.booking_id']] = true;
                        $current_year_last_day = mktime(0, 0, 0, 12, 31, date('Y'));
                        if($payment['funding_id.type'] != 'invoice' && $payment['funding_id.booking_id.date_from'] > $current_year_last_day) {
                            // if payment relates to a funding attached to a booking that will occur after the 31th of december of current year, convert the funding to an invoice
                            // #memo #waiting - to be confirmed
                            // $om->callonce(Funding::getType(), '_convertToInvoice', $payment['funding_id']);
                        }
                        // update booking_id
                        $om->update(self::getType(), $pid, ['booking_id' => $payment['funding_id.booking_id']]);
                    }
                    if($payment['funding_id.invoice_id']) {
                        $map_invoices_ids[$payment['funding_id.invoice_id']] = true;
                    }
                    $om->update(Funding::getType(), $payment['funding_id'], ['paid_amount' => null, 'is_paid' => null], $lang);
                }
                else {
                    // void booking_id
                    $om->update(self::getType(), $ids, ['booking_id' => 0]);
                }
            }
            $om->callonce(Booking::getType(), 'updateStatusFromFundings', array_keys($map_bookings_ids), [], $lang);
            $om->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null], $lang);
            $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['is_paid' => null]);
        }
    }

    /**
     * Signature for single object change from views.
     *
     * @param  Object   $om        Object Manager instance.
     * @param  Array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  Array    $values    Copy of the current (partial) state of the object.
     * @param  String   $lang      Language (char 2) in which multilang field are to be processed.
     * @return Array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];

        if(isset($event['funding_id'])) {
            $fundings = $om->read(Funding::getType(), $event['funding_id'], [
                    'type',
                    'due_amount',
                    'booking_id',
                    'booking_id.name',
                    'booking_id.customer_id.id',
                    'booking_id.customer_id.name',
                    'invoice_id.partner_id.id',
                    'invoice_id.partner_id.name'
                ],
                $lang
            );

            if($fundings > 0) {
                $funding = reset($fundings);
                $result['booking_id'] = [ 'id' => $funding['booking_id'], 'name' => $funding['booking_id.name'] ];
                if($funding['type'] == 'invoice')  {
                    $result['partner_id'] = [ 'id' => $funding['invoice_id.partner_id.id'], 'name' => $funding['invoice_id.partner_id.name'] ];
                }
                else {
                    $result['partner_id'] = [ 'id' => $funding['booking_id.customer_id.id'], 'name' => $funding['booking_id.customer_id.name'] ];
                }
                // set the amount according to the funding due_amount (the maximum assignable)
                $max = $funding['due_amount'];
                if(isset($values['amount']) && $values['amount'] < $max ) {
                    $max = $values['amount'];
                }
                $result['amount'] = $max;
            }
        }

        return $result;
    }


    /**
     * Check wether the payment can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  Object   $om         ObjectManager instance.
     * @param  Array    $ids        List of objects identifiers.
     * @param  Array    $values     Associative array holding the new values to be assigned.
     * @param  String   $lang       Language in which multilang fields are being updated.
     * @return Array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {
        /*
        // #memo - we cannot prevent payment assignment based on the expected amount of the funding: if amount received is higher, it will be accounted to the amount paid and regulated at the invoicing of the booking.
        if(isset($values['funding_id'])) {
            $fundings = $om->read(Funding::getType(), $values['funding_id'], ['due_amount'], $lang);
            if($fundings > 0 && count(($fundings))) {
                $funding = reset($fundings);
                if(isset($values['amount'])) {
                    if($values['amount'] > $funding['due_amount']) {
                        return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than selected funding\'s amount.']];
                    }
                }
                else {
                    $payments = $om->read(self::getType(), $ids, ['amount'], $lang);
                    foreach($payments as $pid => $payment) {
                        if($payment['amount'] > $funding['due_amount']) {
                            return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than selected funding\'s amount.']];
                        }
                    }
                }
            }
        }
        else if(isset($values['amount'])) {
            $payments = $om->read(self::getType(), $ids, ['amount', 'funding_id', 'funding_id.due_amount'], $lang);
            foreach($payments as $pid => $payment) {
                if($payment['funding_id'] && $payment['amount'] > $payment['funding_id.due_amount']) {
                    return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than selected funding\'s amount.']];
                }
            }

        }
        */

        // assigning the payment to another funding is allowed at all time
        if(count($values) == 1 && isset($values['funding_id'])) {
            return [];
        }

        return parent::canupdate($om, $ids, $values, $lang);
    }


    /**
     * Check wether the payments can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $payments = $om->read(self::getType(), $ids, ['payment_origin', 'is_manual', 'statement_line_id.status']);

        if($payments > 0) {
            foreach($payments as $id => $payment) {
                if($payment['payment_origin'] == 'bank') {
                    if($payment['statement_line_id.status'] != 'pending') {
                        return ['status' => ['non_removable' => 'Payment from reconciled line cannot be removed.']];
                    }
                }
                elseif(!$payment['is_manual']) {
                    return ['payment_origin' => ['non_removable' => 'Payment cannot be removed.']];
                }
            }
        }
        return [];
    }
}
