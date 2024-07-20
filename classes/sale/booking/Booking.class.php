<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;
use core\setting\Setting;
use lodging\identity\CenterOffice;

/**
 * Virtual properties based on fields descriptors returned by getColumns()
 *
 * @property string                                    $name
 * @property \lodging\sale\customer\Customer           $customer_id
 * @property \lodging\identity\Identity                $customer_identity_id
 * @property \sale\customer\CustomerNature             $customer_nature_id
 * @property \lodging\identity\Center                  $center_id
 * @property \lodging\identity\CenterOffice            $center_office_id
 * @property \lodging\identity\Identity                $organisation_id
 * @property \lodging\sale\booking\Contact             $contacts_ids
 * @property \lodging\sale\booking\Contract            $contracts_ids
 *
 */
class Booking extends \sale\booking\Booking {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code to serve as reference (should be unique).",
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true,
                // #memo - workaround for preventing setting name at creation when coming from filtered view
                'onupdate'          => 'onupdateName',
            ],

            'display_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code booking and name client",
                'function'          => 'calcDisplayName',
                'readonly'          => true,
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\customer\Customer',
                'description'       => "The customer whom the booking relates to (depends on selected identity).",
                'onupdate'          => 'onupdateCustomerId'
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BookingType',
                'description'       => "The kind of booking it is about.",
                'default'           => 1                // default to 'general public'
            ],

            'customer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => "The identity of the customer whom the booking relates to.",
                'onupdate'          => 'onupdateCustomerIdentityId',
                'required'          => true
            ],

            'customer_nature_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerNature',
                'description'       => 'Nature of the customer (synched with customer) for views convenience.',
                'onupdate'          => 'onupdateCustomerNatureId',
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center to which the booking relates to.",
                'required'          => true,
                'onupdate'          => 'lodging\sale\booking\Booking::onupdateCenterId'
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => 'Office the invoice relates to (for center management).',
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Contact',
                'foreign_field'     => 'booking_id',
                'description'       => 'List of contacts relating to the booking, if any.',
                'ondetach'          => 'delete'
            ],

            'contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Contract',
                'foreign_field'     => 'booking_id',
                'order'             => 'created',
                'sort'              => 'desc',
                'description'       => 'List of contracts relating to the booking, if any.'
            ],

            'composition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Composition',
                'description'       => 'The composition that relates to the booking.'
            ],

            'composition_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\CompositionItem',
                'foreign_field'     => 'booking_id',
                'description'       => "The items that refer to the composition.",
                'ondetach'          => 'delete'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Consumption',
                'foreign_field'     => 'booking_id',
                'description'       => 'Consumptions relating to the booking.',
                'ondetach'          => 'delete'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLine',
                'foreign_field'     => 'booking_id',
                'description'       => 'Detailed consumptions of the booking.'
            ],

            'booking_lines_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
                'foreign_field'     => 'booking_id',
                'description'       => 'Grouped consumptions of the booking.',
                'order'             => 'order',
                'ondetach'          => 'delete',
                'onupdate'          => 'onupdateBookingLinesGroupsIds'
            ],

            'sojourn_product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\SojournProductModel',
                'foreign_field'     => 'booking_id',
                'description'       => "The product models groups assigned to the booking (from groups).",
                'ondetach'          => 'delete'
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => Funding::getType(),
                'foreign_field'     => 'booking_id',
                'description'       => 'Fundings that relate to the booking.',
                'ondetach'          => 'delete'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Invoice',
                'foreign_field'     => 'booking_id',
                'description'       => 'Invoices that relate to the booking.',
                'ondetach'          => 'delete'
            ],

            'nb_pers' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Approx. amount of persons involved in the booking.',
                'function'          => 'calcNbPers',
                'store'             => true
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\SojournType',
                'description'       => 'Default sojourn type of the booking (set according to booking center).'
            ],

            'is_invoiced' => [
                "type"              => "boolean",
                "description"       => "Marks the booking has having a non-cancelled balance invoice.",
                "default"           => false,
                'onupdate'          => 'onupdateIsInvoiced'
            ],

            'has_tour_operator' => [
                'type'              => 'boolean',
                'description'       => 'Mark the booking as completed by a Tour Operator.',
                'default'           => false
            ],

            'tour_operator_id' => [
                'type'              => 'many2one',
                'foreign_object'    => \sale\customer\TourOperator::getType(),
                'domain'            => ['is_tour_operator', '=', true],
                'description'       => 'Tour Operator that completed the booking.',
                'visible'           => ['has_tour_operator', '=', true]
            ],

            'tour_operator_ref' => [
                'type'              => 'string',
                'description'       => 'Specific reference, voucher code, or booking ID for the TO.',
                'visible'           => ['has_tour_operator', '=', true]
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', self::getType()]
            ],

            'rental_unit_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\SojournProductModelRentalUnitAssignement',
                'foreign_field'     => 'booking_id',
                'description'       => "The rental units assigned to the group (from lines)."
            ],

            'is_locked' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'A booking can be locked to prevent updating the sale price of contracted products.',
                'function'          => 'calcIsLocked'
            ],

            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'function'          => 'calcAlert'
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received so far.",
                'function'          => 'calcPaidAmount',
                'store'             => true
            ],

            'payment_status' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'selection'         => [
                    'due',
                    'paid'
                ],
                'function'          => 'calcPaymentStatus',
                'store'             => true,
                'description'       => "Current status of the payments. Depends on the status of the booking.",
                'help'              => "'Due' means we are expecting some money for the booking (at the moment, at least one due funding has not been fully received). 'Paid' means that everything expected (all payments) has been received."
            ],

            'payment_plan_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\PaymentPlan',
                'description'       => 'The payment plan that has been automatically assigned.'
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Structured reference for identifying payments relating to the Booking.',
                'store'             => true
            ],

            'display_payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcDisplayPaymentReference',
                'description'       => 'Formatted Structured reference as shown in Bank Statements.',
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'quote',                    // booking is just informative: nothing has been booked in the planning
                    'option',                   // booking has been placed in the planning for 10 days
                    'confirmed',                // booking has been placed in the planning without time limit
                    'validated',                // signed contract and first installment have been received
                    'checkedin',                // host is currently occupying the booked rental unit
                    'checkedout',               // host has left the booked rental unit
                    'invoiced',
                    'debit_balance',            // customer still has to pay something
                    'credit_balance',           // a reimbursement to customer is required
                    'balanced'                  // booking is over and balance is cleared
                ],
                'description'       => 'Status of the booking.',
                'default'           => 'quote',
                'onupdate'          => 'onupdateStatus'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => "Flag marking the booking as cancelled (impacts status).",
                'default'           => false
            ],

            'is_from_channelmanager' => [
                'type'              => 'boolean',
                'description'       => 'Used to distinguish bookings created from channel manager.',
                'default'           => false
            ],

            'extref_reservation_id' => [
                'type'              => 'string',
                'description'       => 'Identifier of the related reservation at channel manager side.',
                'visible'           => ['is_from_channelmanager', '=', true]
            ],

            'guarantee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Guarantee',
                'description'       => 'The guarantee given by the customer, if any.',
                'visible'           => ['is_from_channelmanager', '=', true]
            ],

            'date_expiry' => [
                'type'              => 'date',
                'description'       => 'Reservation expiration date in Option',
                'visible'           => [["status", "=", "option"],["is_noexpiry", "=", false]],
                'default'           => time()
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => "The organisation the establishment belongs to."
            ],

        ];
    }

    public static function onupdateBookingLinesGroupsIds($om, $oids, $values, $lang) {
        $om->callonce('sale\booking\Booking', '_resetPrices', $oids, [], $lang);
    }

    public static function onupdateIsInvoiced($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, ['is_invoiced', 'booking_lines_ids']);
        foreach($bookings as $id => $booking) {
            // update all booking lines accordingly to their parent booking
            $om->update(BookingLine::getType(), $booking['booking_lines_ids'], ['is_invoiced' => $booking['is_invoiced']]);
        }
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];

        $bookings = $om->read(self::getType(), $oids, ['center_id.center_office_id.code'], $lang);
        $format = Setting::get_value('sale', 'booking', 'booking.sequence_format', '%05d{sequence}');

        foreach($bookings as $oid => $booking) {

            $setting_name = 'booking.sequence.'.$booking['center_id.center_office_id.code'];
            $sequence = Setting::get_value('sale', 'booking', $setting_name, null, 0, 'fr');

            if($sequence) {
                Setting::set_value('sale', 'booking', $setting_name, $sequence + 1, 0, 'fr');

                $result[$oid] = Setting::parse_format($format, [
                    'center'    => $booking['center_id.center_office_id.code'],
                    'sequence'  => $sequence
                ]);
            }

        }
        return $result;
    }

    public static function calcDisplayName($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['name','customer_identity_id','customer_id.name' ], $lang);
        if($bookings > 0) {
            foreach($bookings as $id => $booking) {
                $result[$id] = $booking['name'] .' - '.$booking['customer_id.name']  .' ('.$booking['customer_identity_id'] . ')' ;
            }
        }
        return $result;
    }

    public static function calcNbPers($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['booking_lines_groups_ids']);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $result[$bid] = 0;
                $groups = $om->read(BookingLineGroup::getType(), $booking['booking_lines_groups_ids'], ['nb_pers', 'is_autosale', 'is_extra', 'is_sojourn']);
                if($groups > 0) {
                    foreach($groups as $group_id => $group) {
                        if($group['is_sojourn'] && !$group['is_autosale'] && !$group['is_extra']) {
                            $result[$bid] += $group['nb_pers'];
                        }
                    }
                }
            }
        }
        return $result;
    }

    public static function calcIsLocked($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['contracts_ids'], $lang);
        foreach($bookings as $oid => $booking) {
            $result[$oid] = false;
            if(count($booking['contracts_ids'])) {
                $contracts = $om->read(\lodging\sale\booking\Contract::getType(), $booking['contracts_ids'], ['is_locked', 'status'], $lang);
                foreach($contracts as $contract) {
                    if($contract['status'] != 'cancelled' && $contract['is_locked']) {
                        $result[$oid] = true;
                    }
                }
            }
        }
        return $result;
    }

    public static function calcAlert($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['contracts_ids'], $lang);
        foreach($bookings as $oid => $booking) {

            $messages_ids = $om->search('core\alert\Message',[ ['object_class', '=', 'lodging\sale\booking\Booking'], ['object_id', '=', $oid]]);
            if($messages_ids > 0 && count($messages_ids)) {
                $max_alert = 0;
                $map_alert = array_flip([
                        'notice',           // weight = 1, might lead to a warning
                        'warning',          // weight = 2, might be important, might require an action
                        'important',        // weight = 3, requires an action
                        'error'             // weight = 4, requires immediate action
                    ]);
                $messages = $om->read(\core\alert\Message::getType(), $messages_ids, ['severity']);
                foreach($messages as $mid => $message){
                    $weight = $map_alert[$message['severity']];
                    if($weight > $max_alert) {
                        $max_alert = $weight;
                    }
                }
                switch($max_alert) {
                    case 0:
                         $result[$oid] = 'info';
                        break;
                    case 1:
                         $result[$oid] = 'warn';
                        break;
                    case 2:
                         $result[$oid] = 'major';
                        break;
                    case 3:
                    default:
                         $result[$oid] = 'error';
                        break;
                }
            }
            else {
                $result[$oid] = 'success';
            }
        }
        return $result;
    }

    /**
     * Computes the paid_amount property based on the bookings fundings.
     *
     */
    public static function calcPaidAmount($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $oids, ['fundings_ids'], $lang);

        if($bookings > 0) {
            foreach($bookings as $oid => $booking) {
                $fundings = $om->read(Funding::getType(), $booking['fundings_ids'], ['due_amount', 'is_paid', 'paid_amount'], $lang);
                $paid_amount = 0.0;
                if($fundings > 0) {
                    foreach($fundings as $fid => $funding) {
                        if($funding['is_paid']) {
                            $paid_amount += $funding['due_amount'];
                        }
                        elseif($funding['paid_amount'] > 0) {
                            $paid_amount += $funding['paid_amount'];
                        }
                    }
                }
                $result[$oid] = $paid_amount;
            }
        }
        return $result;
    }

    /**
     * Payment status tells if a given booking is currently expecting money.
     */
    public static function calcPaymentStatus($om, $ids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $ids, ['status', 'fundings_ids', 'contracts_ids'], $lang);
        if($bookings > 0 && count($bookings)) {
            foreach($bookings as $id => $booking) {
                $result[$id] = 'paid';
                $fundings = $om->read(Funding::getType(), $booking['fundings_ids'], ['due_date', 'is_paid'], $lang);
                // if there is at least one overdue funding : a payment is 'due', otherwise booking is 'paid'
                if($fundings > 0 && count($fundings)) {
                    $has_one_paid = false;
                    foreach($fundings as $funding) {
                        if($funding['is_paid']) {
                            $has_one_paid = true;
                        }
                        if(!$funding['is_paid'] && $funding['due_date'] > time()) {
                            $result[$id] = 'due';
                            break;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * This method is used to adjust the final status of a Booking according to the payment status of its fundings.
     * #memo - this method differs from the parent one.
     */
    public static function updateStatusFromFundings($om, $ids, $values=[], $lang='en') {
        $bookings = $om->read(self::getType(), $ids, ['status', 'price', 'fundings_ids'], $lang);
        if($bookings > 0 && count($bookings)) {
            foreach($bookings as $id => $booking) {
                if($booking['status'] == 'confirmed') {
                    $contracts_ids = $om->search(Contract::getType(), [
                            ['booking_id', '=', $id],
                            ['status', '=', 'signed']
                        ]);
                    // discard booking with non-signed contract
                    if(!$contracts_ids && count($contracts_ids) <= 0) {
                        continue;
                    }
                    if(count($booking['fundings_ids']) == 0) {
                        // contract signed & no funding : booking is validated
                        $om->update(self::getType(), $id, ['status' => 'validated'], $lang);
                    }
                    // there is at least one funding
                    else {
                        $today = time();
                        $fundings_ids = $om->search(Funding::getType(), [
                                ['booking_id', '=', $id],
                                ['due_date', '<', $today]
                            ]);
                        // if there are fundings with passed due_date
                        if($fundings_ids && count($fundings_ids) > 0) {
                            $all_paid = true;
                            $fundings = $om->read(Funding::getType(), $fundings_ids, ['is_paid'], $lang);
                            foreach($fundings as $fid => $funding) {
                                if(!$funding['is_paid']) {
                                    $all_paid = false;
                                    break;
                                }
                            }
                            if($all_paid) {
                                // if all fundings with passed due date are paid : booking is validated
                                $om->update(self::getType(), $id, ['status' => 'validated'], $lang);
                            }
                        }
                        // all fundings have a due_date in the future
                        else {
                            $fundings_ids = $om->search(Funding::getType(), [
                                ['booking_id', '=', $id],
                                ['is_paid', '=', true]
                            ]);
                            // there is at least one funding marked as paid : mark booking as validated
                            if($fundings_ids && count($fundings_ids) > 0) {
                                $om->update(self::getType(), $id, ['status' => 'validated'], $lang);
                            }
                        }
                    }
                }
                elseif(in_array($booking['status'], ['invoiced', 'balanced', 'debit_balance', 'credit_balance'])) {
                    // booking is candidate to status change only if it has a non-cancelled balance invoice
                    $invoices_ids = $om->search(Invoice::getType(), [['booking_id', '=', $id], ['type', '=', 'invoice'], ['is_deposit', '=', false], ['status', '=', 'invoice']]);
                    if($invoices_ids < 0 || !count($invoices_ids)) {
                        // target booking has not yet been invoiced : ignore match
                        continue;
                    }
                    $sum = 0.0;
                    $fundings = $om->read(Funding::gettype(), $booking['fundings_ids'], ['due_amount', 'paid_amount'], $lang);
                    foreach($fundings as $fid => $funding) {
                        $sum += $funding['paid_amount'];
                    }
                    if(round($sum, 2) < round($booking['price'], 2)) {
                        // an unpaid amount remains
                        $om->update(self::getType(), $id, ['status' => 'debit_balance']);
                    }
                    elseif(round($sum, 2) > round($booking['price'], 2)) {
                        // a reimbursement is due
                        $om->update(self::getType(), $id, ['status' => 'credit_balance']);
                    }
                    else {
                        // everything has been paid : booking can be archived
                        $om->update(self::getType(), $id, ['status' => 'balanced']);
                    }
                }
            }
        }
    }

    /**
     * Maintain sync of has_contract flag and with customer count_booking_12 and count_booking_24
     */
    public static function onupdateStatus($om, $ids, $values, $lang) {
        $bookings = $om->read(self::getType(), $ids, ['status', 'customer_id'], $lang);
        if($bookings > 0) {
            foreach($bookings as $id => $booking) {
                if($booking['status'] == 'confirmed') {
                    $om->update(self::getType(), $id, ['has_contract' => true], $lang);
                }
            }
        }
    }

    /**
     * Maintain sync with Customer
     */
    public static function onupdateCustomerNatureId($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, ['customer_id', 'customer_nature_id', 'customer_nature_id.rate_class_id'], $lang);

        if($bookings > 0) {
            foreach($bookings as $oid => $odata) {
                if($odata['customer_nature_id.rate_class_id']) {
                    $om->update('sale\customer\Customer', $odata['customer_id'], [
                            'customer_nature_id'    => $odata['customer_nature_id'],
                            'rate_class_id'         => $odata['customer_nature_id.rate_class_id']
                        ]);
                }
            }
        }
    }


    /**
     * #workaround - Prevent name update by other mean than calculation.
     */
    public static function onupdateName($om, $ids, $values, $lang) {
        $res = $om->read(self::getType(), $ids, ['name'], $lang);
        if($res > 0 && count($res)) {
            foreach($res as $id => $booking) {
                // reset name set with a filter value
                if(strpos($booking['name'], '%') !== false) {
                    $om->update(self::getType(), $id, ['name' => null]);
                }
            }
        }
    }

    /**
     * Maintain sync with Customer when assigning a new customer by selecting a customer_identity_id
     * Customer is always selected by picking up an identity (there should always be only one 'customer' partner for a given identity for current organisation).
     * If the identity has a parent identity (department or subsidiary), the customer is based on that parent identity.
     *
     * @param  \equal\orm\ObjectManager     $om        Object Manager instance.
     * @param  Array                        $oids      List of objects identifiers.
     * @param  Array                        $values    Associative array mapping fields names with new values that have been assigned.
     * @param  String                       $lang      Language (char 2) in which multilang field are to be processed.
     */
    public static function onupdateCustomerIdentityId($om, $oids, $values, $lang) {

        $bookings = $om->read(self::getType(), $oids, [
                'description',
                'customer_identity_id',
                'customer_identity_id.description',
                'customer_identity_id.has_parent',
                'customer_identity_id.parent_id',
                'customer_nature_id',
                'customer_nature_id.rate_class_id'
            ]);

        if($bookings > 0) {
            foreach($bookings as $oid => $booking) {
                $partner_id = null;
                $identity_id = $booking['customer_identity_id'];
                if($booking['customer_identity_id.has_parent'] && $booking['customer_identity_id.parent_id']) {
                    $identity_id = $booking['customer_identity_id.parent_id'];
                }
                // find the partner that relates to the target identity, if any
                $partners_ids = $om->search('sale\customer\Customer', [
                    ['relationship', '=', 'customer'],
                    ['owner_identity_id', '=', 1],
                    ['partner_identity_id', '=', $identity_id]
                ]);
                if(count($partners_ids)) {
                    $partner_id = reset($partners_ids);
                }
                else {
                    // create a new customer for the selected identity
                    $identities = $om->read('lodging\identity\Identity', $identity_id, ['type_id']);
                    if($identities > 0 && count($identities)) {
                        $identity = reset($identities);
                        $partner_id = $om->create('sale\customer\Customer', [
                                'partner_identity_id'   => $identity_id,
                                'customer_type_id'      => $identity['type_id'],
                                'rate_class_id'         => $booking['customer_nature_id.rate_class_id'],
                                'customer_nature_id'    => $booking['customer_nature_id']
                            ]);
                    }
                }

                // if description is empty, replace it with assigned customer's description
                if(strlen((string) $booking['description']) == 0) {
                    $values = [
                        'description' => $booking['customer_identity_id.description']
                    ];
                }

                if($partner_id && $partner_id > 0) {
                    // will trigger an update of the rate_class for existing booking_lines
                    $values['customer_id'] = $partner_id;
                }
                $om->update(self::getType(), $oid, $values);
            }
            // import contacts from customer identity
            $om->callonce(self::getType(), 'createContacts', $oids, [], $lang);
        }
    }

    public static function createContacts($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, [
            'customer_identity_id',
            'contacts_ids'
        ], $lang);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                if(is_null($booking['customer_identity_id']) || $booking['customer_identity_id'] <= 0) {
                    continue;
                }
                $partners_ids = [];
                $existing_partners_ids = [];
                // read all contacts (to prevent importing contacts twice)
                if($booking['contacts_ids'] && count($booking['contacts_ids'] )) {
                    // #memo - we don't remove previously added contacts to keep user's work
                    // $om->delete(Contact::getType(), $booking['contacts_ids'], true);
                    $contacts = $om->read(Contact::getType(), $booking['contacts_ids'], ['partner_identity_id']);
                    $existing_partners_ids = array_map(function($a) { return $a['partner_identity_id'];}, $contacts);
                }
                // if customer has contacts assigned to its identity, import those
                $identity_contacts_ids = $om->search(\lodging\identity\Contact::getType(), [
                        ['owner_identity_id', '=', $booking['customer_identity_id']],
                        ['relationship', '=', 'contact']
                    ]);
                if($identity_contacts_ids > 0 && count($identity_contacts_ids) > 0) {
                    $contacts = $om->read(\lodging\identity\Contact::getType(), $identity_contacts_ids, ['partner_identity_id']);
                    foreach($contacts as $cid => $contact) {
                        $partners_ids[] = $contact['partner_identity_id'];
                    }
                }
                // append customer identity's own contact
                $partners_ids[] = $booking['customer_identity_id'];
                // keep only partners_ids not present yet
                $partners_ids = array_diff($partners_ids, $existing_partners_ids);
                // create booking contacts
                foreach($partners_ids as $partner_id) {
                    $om->create(Contact::getType(), [
                        'booking_id'            => $bid,
                        'owner_identity_id'     => $booking['customer_identity_id'],
                        'partner_identity_id'   => $partner_id
                    ]);
                }
            }
        }
    }

    /**
     * Handler for updating values relating the customer.
     * Customer and Identity are synced : only the identity can be changes through views, customer always derives from the selected identity.
     * This handler is always triggered by the onupdateCustomerIdentityId method.
     *
     * @param  \equal\orm\ObjectManager     $om        Object Manager instance.
     * @param  Array                        $oids      List of objects identifiers.
     * @param  Array                        $values    Associative array mapping fields names with new values tha thave been assigned.
     * @param  String                       $lang      Language (char 2) in which multilang field are to be processed.
     */
    public static function onupdateCustomerId($om, $oids, $values, $lang) {

        // update rate_class, based on customer
        $bookings = $om->read(self::getType(), $oids, [
            'booking_lines_groups_ids',
            'customer_id.rate_class_id',
        ], $lang);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                // update bookingline group rate_class_id   (triggers resetPrices and updatePriceAdapters)
                if($booking['booking_lines_groups_ids'] && count($booking['booking_lines_groups_ids'])) {
                    if($booking['customer_id.rate_class_id']) {
                        $om->update(BookingLineGroup::getType(), $booking['booking_lines_groups_ids'], ['rate_class_id' => $booking['customer_id.rate_class_id']], $lang);
                    }
                }
            }
        }

        // update auto sale products
        $om->callonce(self::getType(), '_updateAutosaleProducts', $oids, [], $lang);
    }

    public static function onupdateCenterId($om, $oids, $values, $lang) {
        $bookings = $om->read(self::getType(), $oids, ['booking_lines_ids', 'center_id.center_office_id']);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $booking_lines_ids = $booking['booking_lines_ids'];
                if($booking_lines_ids > 0 && count($booking_lines_ids)) {
                    $om->callonce('lodging\sale\booking\BookingLine', '_updatePriceId', $booking_lines_ids, [], $lang);
                }
                $center_offices = $om->read(CenterOffice::getType(), $booking['center_id.center_office_id'], ['id', 'organisation_id']);
                $center_office = reset($center_offices);
                $om->update(self::getType(), $bid, ['center_office_id' => $booking['center_id.center_office_id'],
                                                    'organisation_id' => $center_office['organisation_id'] ]);
            }
        }
    }

    /**
     * Signature for single object change from views.
     *
     * @param  \equal\orm\ObjectManager     $om        Object Manager instance.
     * @param  Array                        $event     Associative array holding changed fields as keys, and their related new values.
     * @param  Array                        $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @param  String                       $lang      Language (char 2) in which multilang field are to be processed.
     * @return Array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];

        if(isset($event['date_from'])) {
            if($values['date_to'] < $event['date_from']) {
                $result['date_to'] = $event['date_from'];
            }
        }
        // try to retrieve nature from an identity
        if(isset($event['customer_identity_id'])) {
            // search for a partner that relates to this identity, if any
            $partners_ids = $om->search('sale\customer\Customer', [
                ['relationship', '=', 'customer'],
                ['owner_identity_id', '=', 1],
                ['partner_identity_id', '=', $event['customer_identity_id']]
            ]);
            if(count($partners_ids)) {
                $partners = $om->read('sale\customer\Customer', $partners_ids, ['id', 'name', 'customer_nature_id.id', 'customer_nature_id.name']);
                if($partners > 0) {
                    $partner = reset($partners);
                    $result['customer_id'] = ['id' => $partner['id'], 'name' => $partner['name']];
                    if(isset($partner['customer_nature_id.id']) && $partner['customer_nature_id.id']) {
                        $result['customer_nature_id'] = [
                                'id'    => $partner['customer_nature_id.id'],
                                'name'  => $partner['customer_nature_id.name']
                            ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Generate one or more groups for products sold automatically.
     * We generate services groups related to autosales when the following fields are updated:
     * customer, date_from, date_to, center_id
     *
     */
    public static function _updateAutosaleProducts($om, $oids, $values, $lang) {
        /*
            remove groups related to autosales that already exist
        */

        $bookings = $om->read(self::getType(), $oids, [
                'id',
                'customer_id.rate_class_id',
                'customer_id',
                'booking_lines_groups_ids',
                'nb_pers',
                'date_from',
                'date_to',
                'center_id.autosale_list_category_id'
            ], $lang);

        // loop through bookings and create groups for autosale products, if any
        foreach($bookings as $booking_id => $booking) {

            $groups_ids_to_delete = [];
            $booking_lines_groups = $om->read('lodging\sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['is_autosale'], $lang);
            if($booking_lines_groups > 0) {
                foreach($booking_lines_groups as $gid => $group) {
                    if($group['is_autosale']) {
                        $groups_ids_to_delete[] = -$gid;
                    }
                }
                $om->update(self::getType(), $booking_id, ['booking_lines_groups_ids' => $groups_ids_to_delete], $lang);
            }

            /*
                Find the first Autosale List that matches the booking dates
            */

            $autosale_lists_ids = $om->search('sale\autosale\AutosaleList', [
                ['autosale_list_category_id', '=', $booking['center_id.autosale_list_category_id']],
                ['date_from', '<=', $booking['date_from']],
                ['date_to', '>=', $booking['date_from']]
            ]);

            $autosale_lists = $om->read('sale\autosale\AutosaleList', $autosale_lists_ids, ['id', 'autosale_lines_ids']);
            $autosale_list_id = 0;
            $autosale_list = null;
            if($autosale_lists > 0 && count($autosale_lists)) {
                // use first match (there should always be only one or zero)
                $autosale_list = array_pop($autosale_lists);
                $autosale_list_id = $autosale_list['id'];
                trigger_error("ORM:: match with autosale List {$autosale_list_id}", QN_REPORT_DEBUG);
            }
            else {
                trigger_error("ORM:: no autosale List found", QN_REPORT_DEBUG);
            }
            /*
                Search for matching Autosale products within the found List
            */
            if($autosale_list_id) {
                $operands = [];

                // for now, we only support member cards for customer that haven't booked a service for more thant 12 months
                // $operands['count_booking_12'] = $booking['customer_id.count_booking_12'];

                $bookings_ids = $om->search(self::getType(), [
                        [
                            ['is_cancelled', '=', false],
                            ['status', 'not in', ['quote', 'option']],
                            ['id', '<>', $booking_id],
                            ['customer_id', '=', $booking['customer_id']],
                            ['date_from', '>=', $booking['date_from']],
                            ['date_from', '<=', strtotime('+12 months', $booking['date_from'])]
                        ],
                        [
                            ['is_cancelled', '=', false],
                            ['status', 'not in', ['quote', 'option']],
                            ['id', '<>', $booking_id],
                            ['customer_id', '=', $booking['customer_id']],
                            ['date_from', '<=', $booking['date_from']],
                            ['date_from', '>=', strtotime('-12 months', $booking['date_from'])]
                        ]
                    ]);

                $operands['count_booking_12'] = count($bookings_ids);

                $operands['nb_pers'] = $booking['nb_pers'];

                $autosales = $om->read('sale\autosale\AutosaleLine', $autosale_list['autosale_lines_ids'], [
                    'product_id.id',
                    'product_id.name',
                    'name',
                    'has_own_qty',
                    'qty',
                    'scope',
                    'conditions_ids'
                ], $lang);

                // filter discounts based on related conditions
                $products_to_apply = [];

                // filter discounts to be applied on whole booking
                foreach($autosales as $autosale_id => $autosale) {
                    if($autosale['scope'] != 'booking') {
                        continue;
                    }
                    $conditions = $om->read('sale\autosale\Condition', $autosale['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error("Booking - testing {$autosale['name']} : {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                        if(!$valid) {
                            break;
                        }
                    }
                    if($valid) {
                        trigger_error("ORM:: all conditions fullfilled", QN_REPORT_DEBUG);
                        $products_to_apply[$autosale_id] = [
                            'id'            => $autosale['product_id.id'],
                            'name'          => $autosale['product_id.name'],
                            'has_own_qty'   => $autosale['has_own_qty'],
                            'qty'           => $autosale['qty']
                        ];
                    }
                }

                // apply all applicable products
                $count = count($products_to_apply);

                if($count) {
                    // create a new BookingLine Group dedicated to autosale products
                    $group = [
                        'name'          => 'Suppléments obligatoires',
                        'booking_id'    => $booking_id,
                        'rate_class_id' => ($booking['customer_id.rate_class_id'])?$booking['customer_id.rate_class_id']:4,
                        'date_from'     => $booking['date_from'],
                        'date_to'       => $booking['date_to'],
                        'is_autosale'   => true
                    ];
                    if($count == 1) {
                        // in case of a single line, overwrite group name
                        foreach($products_to_apply as $autosale_id => $product) {
                            $group['name'] = $product['name'];
                        }
                    }
                    $gid = $om->create('lodging\sale\booking\BookingLineGroup', $group, $lang);

                    // add all applicable products to the group
                    $order = 1;
                    foreach($products_to_apply as $autosale_id => $product) {
                        // create a line relating to the product
                        $line = [
                            'order'                     => $order++,
                            'booking_id'                => $booking_id,
                            'booking_line_group_id'     => $gid,
                            'product_id'                => $product['id'],
                            'has_own_qty'               => $product['has_own_qty'],
                            'qty'                       => $product['qty']
                        ];
                        $om->create('lodging\sale\booking\BookingLine', $line, $lang);
                    }
                }
            }
            else {
                $date = date('Y-m-d', $booking['date_from']);
                trigger_error("ORM::no matching autosale list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }


    public static function canclone($orm, $oids) {
        // prevent cloning bookings
        return ['status' => ['not_allowed' => 'Booking cannot be cloned.']];
    }


    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang) {

        $bookings = $om->read(self::getType(), $oids, ['status', 'customer_id', 'customer_identity_id', 'center_id', 'booking_lines_ids'], $lang);

        // fields that can always be updated
        $allowed_fields = ['status', 'description', 'is_invoiced', 'payment_status'];


        if(isset($values['center_id'])) {
            $has_booking_lines = false;
            foreach($bookings as $bid => $booking) {
                // if there are services and the center is already defined (otherwise this is the first assignation and some auto-services might just have been created)
                if($booking['center_id'] && count($booking['booking_lines_ids'])) {
                    $has_booking_lines = true;
                    break;
                }
            }
            if($has_booking_lines) {
                return ['center_id' => ['non_editable' => 'Center cannot be changed once services are attached to the booking.']];
            }
        }

        // identity cannot be changed once the contract has been emitted
        if(isset($values['customer_identity_id'])) {
            foreach($bookings as $bid => $booking) {
                if(!in_array($booking['status'], ['quote', 'option'])) {
                    return ['customer_identity_id' => ['non_editable' => 'Customer cannot be changed once a contract has been emitted.']];
                }
            }
        }

        // if customer nature is missing, make sure the selected customer has one already
        if(isset($values['customer_id']) && !isset($values['customer_nature_id'])) {
            // if we received a customer id, its customer_nature_id must be set
            $customers = $om->read('sale\customer\Customer', $values['customer_id'], [ 'customer_nature_id']);
            if($customers) {
                $customer = reset($customers);
                if(is_null($customer['customer_nature_id'])) {
                    return ['customer_nature_id' => ['missing_mandatory' => 'Unknown nature for this customer.']];
                }
            }
        }

        if(isset($values['booking_lines_ids'])) {
            // trying to add or remove booking lines
            // (lines cannot be assigned to more than one booking)
            $booking = reset($bookings);
            if(!in_array($booking['status'], ['quote'])) {
                $lines = $om->read('lodging\sale\booking\BookingLine', $values['booking_lines_ids'], [ 'booking_line_group_id.is_extra']);
                foreach($lines as $line) {
                    if(!$line['booking_line_group_id.is_extra']) {
                        return ['status' => ['non_editable' => 'Non-extra services cannot be changed for non-quote bookings.']];
                    }
                }
            }
        }

        if(isset($values['booking_lines_groups_ids'])) {
            // trying to add or remove booking line groups
            // groups cannot be assigned to more than one booking
            $booking = reset($bookings);
            if(!in_array($booking['status'], ['quote'])) {
                $booking_lines_groups_ids = array_map( function($id) { return abs($id); }, $values['booking_lines_groups_ids']);
                $groups = $om->read('lodging\sale\booking\BookingLineGroup', $booking_lines_groups_ids, [ 'is_extra']);
                foreach($groups as $group) {
                    if(!$group['is_extra']) {
                        return ['status' => ['non_editable' => 'Non-extra service groups cannot be changed for non-quote bookings.']];
                    }
                }
            }
        }

        // check for accepted changes based on status
        foreach($bookings as $id => $booking) {
            if(in_array($booking['status'], ['invoiced', 'debit_balance', 'credit_balance', 'balanced'])) {
                if(count(array_diff(array_keys($values), $allowed_fields))) {
                    return ['status' => ['non_editable' => 'Invoiced bookings edition is limited.']];
                }
            }
            if( !$booking['customer_id'] && !$booking['customer_identity_id'] && !isset($values['customer_id']) && !isset($values['customer_identity_id']) ) {
                return ['customer_id' => ['missing_mandatory' => 'Customer is mandatory.']];
            }
        }

        return [];
        // ignore parent method
        return parent::canupdate($om, $oids, $values, $lang);
    }

    public static function candelete($orm, $ids, $lang='fr') {
        // ignore parent method and allow all changes
        $bookings = $orm->read(self::getType(), $ids, ['is_from_channelmanager'], $lang);
        foreach($bookings as $id => $booking) {
            if($booking['is_from_channelmanager']) {
                return ['is_from_channelmanager' => ['non_removable' => 'Bookings from channel manager cannot be removed.']];
            }
        }
        return parent::candelete($orm, $ids, $lang);;
    }

    /**
     * Hook invoked after object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $orm       ObjectManager instance.
     * @param  array                        $ids       List of objects identifiers.
     * @return void
     */
    public static function onafterdelete($orm, $ids) {
        // #memo - we do this to handle case where auto products (by groups) are re-created during the delete cycle
        $groups_ids = $orm->search(BookingLineGroup::getType(), ['booking_id', 'in', $ids]);
        $orm->delete(BookingLineGroup::getType(), $groups_ids, true);
    }

    /**
     * Resets and recreates booking consumptions from booking lines and rental units assignments.
     * This method is called upon setting booking status to 'option' or 'confirmed' (#see `option.php`)
     * #memo - consumptions are used in the planning.
     *
     */
    public static function createConsumptions($om, $ids, $values, $lang) {
        $bookings = $om->read(self::getType(), $ids, ['consumptions_ids'], $lang);

        // remove consumptions
        foreach($bookings as $id => $booking) {
            $om->delete(Consumption::getType(), $booking['consumptions_ids'], true);
        }

        // get in-memory list of consumptions for all lines
        $consumptions = $om->call(self::getType(), 'getResultingConsumptions', $ids, [], $lang);

        // recreate consumptions objects
        foreach($consumptions as $consumption) {
            $om->create(Consumption::getType(), $consumption, $lang);
        }
    }


    /**
     * Process BookingLines to create an in-memory list of consumptions objects.
     *
     */
    public static function getResultingConsumptions($om, $oids, $values, $lang) {
        // resulting consumptions objects
        $consumptions = [];

        $bookings = $om->read(self::getType(), $oids, ['booking_lines_groups_ids'], $lang);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
               $consumptions = array_merge($consumptions, BookingLineGroup::getResultingConsumptions($om, $booking['booking_lines_groups_ids'], [], $lang));
            }
        }

        return $consumptions;
    }

    /**
     * Compute a Structured Reference using belgian SCOR (StructuredCommunicationReference) reference format.
     *
     * Note:
     *  format is aaa-bbbbbbb-XX
     *  where Xaaa is the prefix, bbbbbbb is the suffix, and XX is the control number, that must verify (aaa * 10000000 + bbbbbbb) % 97
     *  as 10000000 % 97 = 76
     *  we do (aaa * 76 + bbbbbbb) % 97
     */
    public static function calcPaymentReference($om, $ids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $ids, ['name'], $lang);
        foreach($bookings as $id => $booking) {
            $booking_code = intval($booking['name']);
            // #memo - arbitrary value : used in the accounting software for identifying payments with a temporary account entry counterpart
            $code_ref = 150;
            $control = ((76*$code_ref) + $booking_code) % 97;
            $control = ($control == 0)?97:$control;
            $result[$id] = sprintf("%3d%04d%03d%02d", $code_ref, $booking_code / 1000, $booking_code % 1000, $control);
        }
        return $result;
    }

    public static function calcDisplayPaymentReference($om, $ids, $lang) {
        $result = [];
        $bookings = $om->read(self::getType(), $ids, ['payment_reference'], $lang);
        foreach($bookings as $id => $booking) {
            $reference = $booking['payment_reference'];
            $result[$id] = '+++'.substr($reference, 0, 3).'/'.substr($reference, 4, 4).'/'.substr($reference, 8, 5).'+++';
        }
        return $result;
    }
}