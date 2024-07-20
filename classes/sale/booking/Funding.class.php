<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;

use lodging\sale\catalog\Product;
use sale\price\Price;
use core\setting\Setting;


class Funding extends \lodging\sale\pay\Funding {

    public static function getColumns() {

        return [
            // override to use local calcName with booking_id
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'Booking the contract relates to.',
                'ondelete'          => 'cascade',        // delete funding when parent booking is deleted
                'required'          => true,
                'onupdate'          => 'onupdateBookingId'
            ],

            // override to use custom onupdateDueAmount
            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount expected for the funding (computed based on VAT incl. price).',
                'required'          => true,
                'onupdate'          => 'onupdateDueAmount',
                // 'dependencies'      => ['name', 'amount_share']
            ],

            // override to reference booking.paid_amount
            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Has the full payment been received?",
                'function'          => 'calcIsPaid',
                'store'             => true,
                'onupdate'          => 'onupdateIsPaid',
                // 'dependencies'      => ['booking_id.paid_amount', 'invoice_id.is_paid']
            ],

            // override to reference booking.paid_amount
            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received (can be greater than due_amount).",
                'function'          => 'calcPaidAmount',
                'store'             => true,
                // 'dependencies'      => ['booking_id.paid_amount', 'invoice_id.is_paid']
            ],

            'amount_share' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/percent',
                'function'          => 'calcAmountShare',
                'store'             => true,
                'description'       => "Share of the payment over the total due amount (booking)."
            ],

            // override to use local calcPaymentReference with booking_id
            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Message for identifying the purpose of the transaction.',
                'store'             => true
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Invoice',
                'ondelete'          => 'null',
                'description'       => 'The invoice targeted by the funding, if any.',
                'visible'           => [ ['type', '=', 'invoice'] ]
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Payment',
                'foreign_field'     => 'funding_id'
            ]

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['booking_id.name', 'due_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $oid => $funding) {
                $result[$oid] = $funding['booking_id.name'].'    '.Setting::format_number_currency($funding['due_amount']);
            }
        }
        return $result;
    }

    public static function calcAmountShare($om, $ids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $ids, ['booking_id.price', 'due_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $id => $funding) {
                $total = round($funding['booking_id.price'], 2);
                if($total == 0) {
                    $share = 1;
                }
                else {
                    $share = round(abs($funding['due_amount']) / abs($total), 2);
                }
                $sign = ($funding['due_amount'] < 0)?-1:1;
                $result[$id] = $share * $sign;
            }
        }

        return $result;
    }

    public static function calcPaymentReference($om, $ids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $ids, ['booking_id.payment_reference'], $lang);
        foreach($fundings as $id => $funding) {
            $result[$id] = $funding['booking_id.payment_reference'];
        }
        return $result;
    }

    public static function calcIsPaid($orm, $ids, $lang) {
        $result = [];
        $fundings = $orm->read(self::getType(), $ids, ['booking_id', 'due_amount', 'paid_amount', 'invoice_id'], $lang);
        $map_bookings_ids = [];
        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                $result[$fid] = false;
                $map_bookings_ids[$funding['booking_id']] = true;
                if(abs(round($funding['due_amount'], 2)) > 0) {
                    $sign_paid = intval($funding['paid_amount'] > 0) - intval($funding['paid_amount'] < 0);
                    $sign_due  = intval($funding['due_amount'] > 0) - intval($funding['due_amount'] < 0);
                    if($sign_paid == $sign_due && abs(round($funding['paid_amount'], 2)) >= abs(round($funding['due_amount'], 2))) {
                        $result[$fid] = true;
                    }
                }
            }
            // #memo - this handler can result from a payment_status computation : we need callonce to prevent infinite loops
            $orm->callonce(Booking::getType(), 'updateStatusFromFundings', array_keys($map_bookings_ids), [], $lang);
            // Booking::updateStatusFromFundings($orm, array_keys($map_bookings_ids));
            // #memo - we cannot do that in calc, since this might lead to erasing values that have just been set
            /*
            // force recompute paid_amount property for impacted bookings
            $om->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null]);
            // force recompute is_paid property for impacted invoices
            $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
            */
        }
        return $result;
    }

    /**
     * Computes the paid_amount property based on related payments.
     * In addition, also resets the related booking paid_amount computed field.
     *
     * #note - this should not be necessary since Payment::onupdateFundingId is necessarily triggered at payment creation
     */
    public static function calcPaidAmount($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(self::getType(), $oids, ['booking_id', 'invoice_id', 'payments_ids.amount'], $lang);
        if($fundings > 0) {
            $map_bookings_ids = [];
            $map_invoices_ids = [];
            foreach($fundings as $fid => $funding) {
                $map_bookings_ids[$funding['booking_id']] = true;
                $map_invoices_ids[$funding['invoice_id']] = true;
                $result[$fid] = array_reduce($funding['payments_ids.amount'], function ($c, $funding) {
                    return $c + $funding['amount'];
                }, 0);
            }
            // force recompute computed fields for impacted bookings and invoices
            $om->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null]);
            $om->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
        }
        return $result;
    }

    /**
     * When due_amount is updated (funding is assigned to a booking), we reset the amount_share of all the fundings of the booking.
     */
    public static function onupdateDueAmount($orm, $oids, $values, $lang) {
        $fundings = $orm->read(self::getType(), $oids, ['booking_id']);
        $map_bookings_ids = [];
        if($fundings > 0 && count($fundings)) {
            foreach($fundings as $fid => $funding) {
                $map_bookings_ids[$funding['booking_id']] = true;
            }
            $fundings_ids = $orm->search(self::getType(), ['booking_id', 'in', array_keys($map_bookings_ids)]);
            $orm->update(self::getType(), $fundings_ids, ['name' => null, 'amount_share' => null]);
        }
    }


    /**
     * The field is_paid is a computed fields, but it can also be set manually to arbitrary mark an invoice as paid.
     */
    public static function onupdateIsPaid($orm, $oids, $values, $lang) {
        $fundings = $orm->read(self::getType(), $oids, ['invoice_id', 'booking_id']);
        if($fundings > 0 && count($fundings)) {
            $map_bookings_ids = [];
            $map_invoices_ids = [];
            foreach($fundings as $fid => $funding) {
                if($funding['invoice_id']) {
                    $map_invoices_ids[$funding['invoice_id']] = true;
                }
                if($funding['booking_id']) {
                    $map_bookings_ids[$funding['booking_id']] = true;
                }
            }
            $orm->update(Invoice::getType(), array_keys($map_invoices_ids), ['payment_status' => null, 'is_paid' => null]);
            $orm->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null]);
            $orm->callonce(Booking::getType(), 'updateStatusFromFundings', array_keys($map_bookings_ids), [], $lang);
        }
    }

    public static function onupdateBookingId($orm, $ids, $values, $lang) {
        $fundings = $orm->read(self::getType(), $ids, ['booking_id', 'booking_id.status', 'booking_id.center_id.center_office_id']);
        if($fundings > 0 && count($fundings)) {
            $map_bookings_ids = [];
            foreach($fundings as $id => $funding) {
                if($funding['booking_id']) {
                    $map_bookings_ids[$funding['booking_id']] = true;
                    $orm->update(self::getType(), $id, ['center_office_id' => $funding['booking_id.center_id.center_office_id']]);
                    if(in_array($funding['booking_id.status'], ['invoiced', 'credit_balance', 'debit_balance', 'balanced'])) {
                        $invoices_ids = $orm->search(Invoice::getType(), [['booking_id', '=', $funding['booking_id']], ['is_deposit', '=', false], ['status', '<>', 'cancelled']], ['id' => 'desc'], 0, 1);
                        if($invoices_ids > 0 && count($invoices_ids)) {
                            $invoice_id = reset($invoices_ids);
                            $orm->update(self::getType(), $id, ['invoice_id' => $invoice_id]);
                        }
                    }
                }
            }
            $orm->update(Booking::getType(), array_keys($map_bookings_ids), ['payment_status' => null, 'paid_amount' => null]);
        }
    }

    /**
     * Check wether an object can be created.
     * These tests come in addition to the unique constraints returned by method `getUnique()`.
     * Checks whether the sum of the fundings of a booking remains lower than the price of the booking itself.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {
        if(isset($values['booking_id']) && isset($values['due_amount'])) {
            $bookings = $om->read(Booking::getType(), $values['booking_id'], ['price', 'fundings_ids.due_amount'], $lang);
            if($bookings > 0 && count($bookings)) {
                // #memo - we allow creating arbitrary fundings (to ease the handling of all possible client payment scenarios)
                /*
                $booking = reset($bookings);
                $fundings_price = (float) $values['due_amount'];
                foreach($booking['fundings_ids.due_amount'] as $fid => $funding) {
                    $fundings_price += (float) $funding['due_amount'];
                }
                if($fundings_price > $booking['price'] && abs($booking['price']-$fundings_price) >= 0.0001) {
                    return ['status' => ['exceeded_price' => "Sum of the fundings cannot be higher than the booking total ({$fundings_price}, {$booking['price']})."]];
                }
                */
            }
        }
        // #memo - idem - we allow creating arbitrary fundings (to ease the handling of all possible client payment scenarios)
        /*
        if(isset($values['due_amount']) && $values['due_amount'] < 0) {
            return ['due_amount' => ['invalid' => "Due amount of a funding cannot be negative."]];
        }
        */
        return parent::cancreate($om, $values, $lang);
    }


    /**
     * Check wether an object can be updated.
     * These tests come in addition to the unique constraints returned by method `getUnique()`.
     * Checks whether the sum of the fundings of each booking remains lower than the price of the booking itself.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang) {
        $allowed_fields = ['type', 'invoice_id'];

        // prevent setting the amount to a negative value
        // #memo - we allow creating arbitrary fundings (to ease the handling of all possible client payment scenarios)
        // #memo - this should be allowed only when made automatically
        /*
        if(isset($values['due_amount']) && $values['due_amount'] < 0) {
            return ['due_amount' => ['invalid' => "Due amount of a funding cannot be negative."]];
        }
        */

        $fundings = $om->read(self::getType(), $oids, ['is_paid', 'booking_id', 'type', 'invoice_id', 'invoice_id.status', 'invoice_id.type', 'due_amount', 'paid_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                // #memo - modifying the funding of an emitted credit note is accepted (in order to re-use previously paid fundings put on first invoice)
                if(isset($values['due_amount']) && $funding['type'] == 'invoice' && $funding['invoice_id'] && isset($funding['invoice_id.status']) && $funding['invoice_id.status'] != 'proforma' && $funding['invoice_id.type'] != 'credit_note') {
                    return ['due_amount' => ['non_editable' => "Invoiced funding cannot be updated."]];
                }
                $bookings = $om->read(Booking::getType(), $funding['booking_id'], ['price', 'fundings_ids.due_amount'], $lang);
                // #memo - we allow creating arbitrary fundings independently from related booking (to ease the handling of all possible client payment scenarios)
                if($bookings > 0 && count($bookings)) {
                    /*
                    $booking = reset($bookings);
                    $fundings_price = 0.0;
                    if(isset($values['due_amount'])) {
                        $fundings_price = (float) $values['due_amount'];
                    }
                    foreach($booking['fundings_ids.due_amount'] as $oid => $odata) {
                        if($oid != $fid) {
                            $fundings_price += (float) $odata['due_amount'];
                        }
                    }
                    if($fundings_price > $booking['price'] && abs($booking['price']-$fundings_price) >= 0.0001) {
                        return ['status' => ['exceeded_price' => "Sum of the fundings cannot be higher than the booking total ({$fundings_price}, {$booking['price']})."]];
                    }
                    */
                }
                // #memo - we allow creating arbitrary fundings independently from related booking (to ease the handling of all possible client payment scenarios)
                // #todo - some situation should probably be prevented
                /*
                if($funding['paid_amount'] > $funding['due_amount']) {
                    if( count(array_diff(array_keys($values), $allowed_fields)) > 0 ) {
                        return ['status' => ['non_editable' => 'Funding can only be updated while marked as non-paid ['.implode(',', array_keys($values)).'].']];
                    }
                }
                */
            }
        }
        return [];
        // ignore parent method
        return parent::canupdate($om, $oids, $values, $lang);
    }

    /**
     * Convert an installment to an invoice.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     */
    public static function _convertToInvoice($om, $oids, $values, $lang) {

        $fundings = $om->read(self::getType(), $oids, [
                'due_amount',
                'booking_id',
                'booking_id.customer_id',
                'booking_id.date_from',
                'booking_id.center_id.organisation_id',
                'booking_id.center_id.center_office_id',
                'booking_id.center_id.price_list_category_id'
            ],
            $lang);

        if($fundings > 0) {

            foreach($fundings as $fid => $funding) {

                // retrieve downpayment product
                $downpayment_product_id = 0;

                $downpayment_sku = Setting::get_value('sale', 'invoice', 'downpayment.sku.'.$funding['booking_id.center_id.organisation_id']);
                if($downpayment_sku) {
                    $products_ids = $om->search(Product::getType(), ['sku', '=', $downpayment_sku]);
                    if($products_ids > 0 && count($products_ids)) {
                        $downpayment_product_id = reset($products_ids);
                    }
                }
                else {
                    $downpayment_sku = 'downpayment';
                }

                $partner_id = (isset($values['partner_id']))?$values['partner_id']:$funding['booking_id.customer_id'];

                // create a new proforma deposit invoice
                $invoice_id = $om->create(Invoice::getType(), [
                        'organisation_id'   => $funding['booking_id.center_id.organisation_id'],
                        'center_office_id'  => $funding['booking_id.center_id.center_office_id'],
                        'booking_id'        => $funding['booking_id'],
                        'partner_id'        => $partner_id,
                        'funding_id'        => $fid,
                        'is_deposit'        => true
                    ], $lang);

                /*
                    Find vat rule, based on Price for product from applicable price list
                */
                $vat_rate = 0.0;

                // find suitable price list
                $price_lists_ids = $om->search('sale\price\PriceList', [
                        ['price_list_category_id', '=', $funding['booking_id.center_id.price_list_category_id']],
                        ['date_from', '<=', $funding['booking_id.date_from']],
                        ['date_to', '>=', $funding['booking_id.date_from']],
                        ['status', 'in', ['published']]
                    ],
                    ['is_active' => 'desc']
                );

                // search for a matching Price within the found Price List
                foreach($price_lists_ids as $price_list_id) {
                    // there should be one or zero matching pricelist with status 'published', if none of the found pricelist
                    $prices_ids = $om->search('sale\price\Price', [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $downpayment_product_id]]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        $prices = $om->read(Price::getType(), $prices_ids, ['vat_rate'], $lang);
                        $price = reset($prices);
                        $vat_rate = $price['vat_rate'];
                    }
                }

                // #memo - funding already includes the VAT, if any (funding due_amount cannot be changed)
                $unit_price = $funding['due_amount'];

                if($vat_rate > 0) {
                    // deduct VAT from due amount
                    $unit_price = round($unit_price / (1+$vat_rate), 4);
                }

                // create a single invoice line group
                $invoice_line_group_id = $om->create(InvoiceLineGroup::getType(), [
                        'invoice_id' => $invoice_id,
                        'name'       => $downpayment_sku
                    ]);

                // create a single invoice line related to the downpayment
                $invoice_line_id = $om->create(InvoiceLine::getType(), [
                        'invoice_id'                => $invoice_id,
                        'product_id'                => $downpayment_product_id,
                        'invoice_line_group_id'     => $invoice_line_group_id,
                    ]);

                $om->update(InvoiceLine::getType(), $invoice_line_id, [
                        'vat_rate'                  => $vat_rate,
                        'unit_price'                => $unit_price,
                        'qty'                       => 1
                    ]);

                // convert funding to 'invoice' type
                $om->update(Funding::getType(), $fid, ['type' => 'invoice', 'invoice_id' => $invoice_id]);
            }
        }
    }

}