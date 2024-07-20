<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\PaymentPlan;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingType;
use lodging\sale\booking\Contract;
use lodging\sale\booking\ContractLine;
use lodging\sale\booking\ContractLineGroup;
use lodging\sale\booking\Funding;


list($params, $providers) = announce([
    'description'   => "Sets booking as confirmed, creates contract and generates payment plan.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking to mark as confirmed.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        'instant_payment' =>  [
            'description'   => 'No funding plan will be generated.',
            'type'          => 'boolean',
            'default'       => false
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch', 'report']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 * @var \equal\error\Reporter               $reporter
 */
list($context, $orm, $cron, $dispatch, $reporter) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch'], $providers['report']];

// read booking object
$booking = Booking::id($params['id'])
    ->read([
        'id',
        'status',
        'is_price_tbc',
        'type_id',
        'date_from',
        'date_to',
        'price',                                  // total price VAT incl.
        'contracts_ids',
        'center_id' => [
            'center_office_id',
            'sojourn_type_id'
        ],
        'customer_id' => [
            'id',
            'rate_class_id'
        ],
        'fundings_ids' => [
            'type',
            'is_paid',
            'due_amount',
            'paid_amount'
        ],
        'booking_lines_groups_ids' => [
            'name',
            'date_from',
            'date_to',
            'has_pack',
            'is_locked',
            'pack_id' => ['id', 'name'],
            'vat_rate',
            'unit_price',
            'fare_benefit',
            'rate_class_id',
            'qty',
            'total',
            'price',
            'nb_nights',
            'nb_pers',
            'booking_lines_ids' => [
                'product_id',
                'description',
                'price_id',
                'unit_price',
                'vat_rate',
                'qty',
                'free_qty',
                'discount',
                'price',
                'total'
            ]
        ]
    ])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'option') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

// #deprecated #memo - we allow setting a booking to 'confirmed' even if is has is_price_tbc set to true, but contracts will not be generated for these
// #memo - setting a booking to 'confirmed' while having `is_price_tbc` set to true breaks the booking workflow and is not allowed.
if($booking['is_price_tbc']) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

// check contact consistency
$data = eQual::run('do', 'lodging_booking_check-contacts', ['id' => $booking['id']]);
if(is_array($data) && count($data)) {
    throw new Exception('missing_contact_phone', QN_ERROR_INVALID_PARAM);
}


/*
    Check consistency
*/


$data = eQual::run('do', 'lodging_booking_check-prices-assignments', ['id' => $booking['id']]);
// raise an exception with first error (alerts should have been issued in the check controllers)
if(is_array($data) && count($data)) {
    throw new Exception('missing_price_assignment', QN_ERROR_INVALID_PARAM);
}

// check customer details completeness
$data = eQual::run('do', 'lodging_booking_check-customer', ['id' => $booking['id']]);


// #memo - check customer might fail if some info is missing for a customer created via the channel manager, but current controller cannot be interrupted
/*
$errors = [];
if(is_array($data) && count($data)) {
    $errors[] = 'uncomplete_customer';
}

// raise an exception with first error (alerts should have been issued in the check controllers)
foreach($errors as $error) {
    throw new Exception($error, QN_ERROR_INVALID_PARAM);
}
*/


/*
    Update alerts and scheduled tasks
*/

// all fundings are renewed / updated : remove any pending alert related to due payments
$dispatch->cancel('lodging.booking.payments', 'lodging\sale\booking\Booking', $booking['id']);

// remove messages about readiness for this booking, if any
$dispatch->cancel('lodging.booking.ready', 'lodging\sale\booking\Booking', $booking['id']);

// remove any existing CRON tasks for reverting the booking to quote
$cron->cancel("booking.option.deprecation.{$booking['id']}");


/*
    Generate the contract
*/


// remember all booking lines involved
$booking_lines_ids = [];

// #memo - generated contracts are kept for history (we never delete them)
// mark existing contracts as expired
Contract::ids($booking['contracts_ids'])->update(['status' => 'cancelled']);


// create contract and contract lines
$contract = Contract::create([
        'date'          => time(),
        'booking_id'    => $params['id'],
        'status'        => 'pending',
        'valid_until'   => time() + (30 * 86400),
        'customer_id'   => $booking['customer_id']['id']
    ])
    ->read(['id'])
    ->first();

foreach($booking['booking_lines_groups_ids'] as $group) {
    $group_id = $group['id'];
    $group_label = $group['name'].' : ';

    if($group['date_from'] == $group['date_to']) {
        $group_label .= date('d/m/y', $group['date_from']);
    }
    else {
        $group_label .= date('d/m/y', $group['date_from']).' - '.date('d/m/y', $group['date_to']);
    }

    $group_label .= ' - '.$group['nb_pers'].'p.';
    $group_rate_class_id = (isset($group['rate_class_id']) && $group['rate_class_id'])?$group['rate_class_id']:$booking['customer_id']['rate_class_id'];

    if($group['has_pack'] && $group['is_locked'] ) {
        // create a contract group based on the booking group
        $contract_line_group = ContractLineGroup::create([
                'name'              => $group_label,
                'is_pack'           => true,
                'contract_id'       => $contract['id'],
                'fare_benefit'      => $group['fare_benefit'],
                'rate_class_id'     => $group_rate_class_id
            ])
            ->first();

        // create a line based on the group
        $c_line = [
                'contract_id'               => $contract['id'],
                'contract_line_group_id'    => $contract_line_group['id'],
                'product_id'                => $group['pack_id']['id'],
                'vat_rate'                  => $group['vat_rate'],
                'unit_price'                => $group['unit_price'],
                'qty'                       => $group['qty']
            ];

        $contract_line = ContractLine::create($c_line)
            ->update(['total' => $group['total']])
            ->update(['price' => $group['price']])
            ->first();

        ContractLineGroup::ids($contract_line_group['id'])->update([ 'contract_line_id' => $contract_line['id'] ]);
    }
    else {
        $contract_line_group = ContractLineGroup::create([
                'name'              => $group_label,
                'is_pack'           => false,
                'contract_id'       => $contract['id'],
                'fare_benefit'      => $group['fare_benefit'],
                'rate_class_id'     => $group_rate_class_id
            ])
            ->first();
    }

    // create as many lines as the group booking_lines
    foreach($group['booking_lines_ids'] as $line) {
        $lid = $line['id'];
        $booking_lines_ids[] = $lid;

        // create line in two steps (not to overwrite price details from the line - that might have been manually adapted)
        $c_line = [
                'contract_id'               => $contract['id'],
                'contract_line_group_id'    => $contract_line_group['id'],
                'product_id'                => $line['product_id'],
                'description'               => $line['description'],
                // #memo - for now, there is no onupdate handler for price_id (if so we should mind the alteration of vat_rate and unit_price)
                'price_id'                  => $line['price_id'],
                'vat_rate'                  => $line['vat_rate'],
                'unit_price'                => $line['unit_price'],
                'qty'                       => $line['qty'],
                'free_qty'                  => $line['free_qty'],
                'discount'                  => $line['discount']
            ];

        // for pack with own price, we rely on the line created for the group, other lines don't have price
        if($group['has_pack'] && $group['is_locked'] ) {
            $c_line['vat_rate'] = 0;
            $c_line['unit_price'] = 0;
            $c_line['free_qty'] = 0;
            $c_line['discount'] = 0;
        }

        $contractLine = ContractLine::create($c_line);

        // for pack with own price, we rely on the line created for the group, other lines don't have price
        if($group['has_pack'] && $group['is_locked'] ) {
            $contractLine
                ->update([
                    'total'      => 0
                ])
                ->update([
                    'price'      => 0
                ]);
        }
        else {
            $contractLine
                ->update([
                    'total'       => $line['total']
                ])
                ->update([
                    'price'       => $line['price']
                ]);
        }
    }
}

Contract::id($contract['id'])->update(['price' => null, 'total' => null]);

// mark all booking lines as contractual
BookingLine::ids($booking_lines_ids)->update(['is_contractual' => true]);

// update booking status
Booking::id($params['id'])->update(['status' => 'confirmed']);


/*
    Pre-fill composition with customer details as first line (ease for single booking)
*/
try {
    eQual::run('do', 'lodging_composition_generate', ['booking_id' => $params['id']]);
}
catch(Exception $e) {
    // ignore errors at this stage
}


/*
    Generate the payment plan
    (expected fundings of the booking)
*/

// set rate class default to 'general public'
$rate_class_id = 4;

if($booking['customer_id']['rate_class_id']) {
    $rate_class_id = $booking['customer_id']['rate_class_id'];
}

if($params['instant_payment']) {
    $on_time = true;
    // force selection of 'instant payment' plan (id = 1 by convention)
    $payment_plan = PaymentPlan::id(1)->read([
            'id', 'name', 'rate_class_id', 'booking_type_id', 'sojourn_type_id',
            'payment_deadlines_ids' => [
                'name', 'delay_from_event', 'delay_from_event_offset', 'delay_count', 'type', 'is_balance_invoice', 'amount_share'
            ]
        ])
        ->first();
}
else {
    // retrieve existing payment plans
    $payment_plans = PaymentPlan::search([])->read([
            'id', 'name', 'rate_class_id', 'booking_type_id', 'sojourn_type_id',
            'payment_deadlines_ids' => [
                'id', 'name', 'delay_from_event', 'delay_from_event_offset', 'delay_count', 'type', 'is_balance_invoice', 'amount_share'
            ]
        ])
        ->get();

    if(!$payment_plans) {
        throw new Exception("missing_payment_plan", QN_ERROR_INVALID_CONFIG);
    }

    // #memo - we assume that there is always one payment plan that matches the booking
    $payment_plan = -1;
    $fulfilled_criteria_count = 0;
    // payment plan assignment is based on booking type and customer's rate class
    foreach($payment_plans as $plan) {
        $pid = $plan['id'];
        // double match: keep plan and stop
        if($plan['rate_class_id'] == $rate_class_id && $plan['booking_type_id'] == $booking['type_id'] && $plan['sojourn_type_id'] == $booking['center_id']['sojourn_type_id']) {
            $payment_plan = $plan;
            break;
        }
        // match for either rate class, booking type or sojourn type
        if($plan['rate_class_id'] == $rate_class_id || $plan['booking_type_id'] == $booking['type_id'] || $plan['sojourn_type_id'] == $booking['center_id']['sojourn_type_id']) {
            $match_criteria_count = 0;

            if($plan['rate_class_id'] == $rate_class_id) {
                ++$match_criteria_count;
            }
            if($plan['booking_type_id'] == $booking['type_id']) {
                ++$match_criteria_count;
            }
            if($plan['sojourn_type_id'] == $booking['center_id']['sojourn_type_id']) {
                ++$match_criteria_count;
            }

            // match GA OTA payment plan only if booking is of OTA type
            $ota_booking_type = BookingType::search(['code', '=', 'OTA'])
                ->read(['id'])
                ->first(true);

            if(!$ota_booking_type) {
                throw new Exception('missing_ota_booking_type', QN_ERROR_INVALID_CONFIG);
            }

            if($plan['booking_type_id'] === $ota_booking_type['id']
                && $booking['type_id'] !== $ota_booking_type['id']) {
                $match_criteria_count = 0;
            }

            if($payment_plan < 0 || $match_criteria_count > $fulfilled_criteria_count) {
                $reporter->debug("Match for plan: {$plan['name']}: class {$plan['rate_class_id']}, booking {$plan['booking_type_id']}, sojourn {$plan['sojourn_type_id']}");
                $payment_plan = $plan;
                $fulfilled_criteria_count = $match_criteria_count;
            }
        }
    }

    if($payment_plan < 0) {
        throw new Exception("cannot_read_object", QN_ERROR_UNKNOWN_OBJECT);
    }

    $reporter->debug("Selected payment plan: {$payment_plan['name']}");

    // check that remaining days to checkin is more than planned delay
    $on_time = true;
    foreach($payment_plan['payment_deadlines_ids'] as $deadline) {
        $deadline_id = $deadline['id'];
        $date = time();         // default delay is starting today (at confirmation time / equivalent to 'booking')
        switch($deadline['delay_from_event']) {
            case 'booking':
                $date = time();
                break;
            case 'checkin':
                $date = $booking['date_from'];
                break;
            case 'checkout':
                $date = $booking['date_to'];
                break;
        }

        $issue_date = $date + ($deadline['delay_from_event_offset'] * 86400);
        if($issue_date < time()) {
            $on_time = false;
            break;
        }
    }
}

// update booking payment plan
Booking::id($params['id'])->update(['payment_plan_id' => $payment_plan['id']]);


// compute total due amount from fetched existing fundings (paid fundings and invoices are not removed when booking is reverted to quote)
$fundings_handled_sum = 0.0;
foreach($booking['fundings_ids'] as $funding) {
    $fid = $funding['id'];
    // we're about to generate a new payment plan : remove unpaid fundings
    if(round($funding['paid_amount'], 2) == 0 && !$funding['is_paid']) {
        Funding::id($fid)->delete(true);
        // remove any existing CRON tasks for funding overdue
        $cron->cancel("booking.funding.overdue.{$fid}");
        // #memo - there are no alerts specific to fundings (only bookings)
    }
    else {
        Funding::id($fid)
            ->update(['due_amount' => $funding['paid_amount']])
            ->update(['is_paid' => true]);
        $fundings_handled_sum += $funding['paid_amount'];
    }
}

// retrieve the remaining unpaid amount
$remaining_amount = $booking['price'] - $fundings_handled_sum;

// more fundings are necessary (remaining is greater than 10%, if less: will be put on balance invoice)
try {
    if($booking['price'] > 0 && ($remaining_amount/$booking['price']) > 0.1) {
        // special case: remaining days to checkin is less than planned delay (or manual request for instant payment)
        if(!$on_time) {
            $reporter->debug("Delay too short: due {$date}, from {$booking['date_from']}");
            // create a single funding with 100% of due amount
            $funding = [
                    'booking_id'            => $params['id'],
                    'center_office_id'      => $booking['center_id']['center_office_id'],
                    'due_amount'            => $remaining_amount,
                    'is_paid'               => false,
                    'type'                  => 'installment',
                    'order'                 => 1,
                    'due_date'              => $booking['date_from'],
                    'description'           => 'Full'
                ];
            Funding::create($funding)->read(['name']);
        }
        else {
            $funding_order = 0;
            // pass-2 : create fundings accordingly to PaymentPlan
            foreach($payment_plan['payment_deadlines_ids'] as $deadline) {
                $deadline_id = $deadline['id'];
                // special case: immediate creation of balance invoice with no funding
                if($deadline['type'] == 'invoice' && $deadline['is_balance_invoice']) {
                    // create proforma balance invoice and do not create funding (raise Exception on failure)
                    eQual::run('do', 'lodging_invoice_generate', ['id' => $params['id']]);
                    break;
                }

                $funding_amount = min($remaining_amount, round($booking['price'] * $deadline['amount_share'], 2));
                if($funding_amount <= 0) {
                    break;
                }
                $remaining_amount -= $funding_amount;
                $funding = [
                    'payment_deadline_id'   => $deadline_id,
                    'booking_id'            => $params['id'],
                    'center_office_id'      => $booking['center_id']['center_office_id'],
                    'due_amount'            => $funding_amount,
                    'is_paid'               => false,
                    'type'                  => 'installment',
                    'order'                 => $funding_order,
                    'description'           => $deadline['name']
                ];

                $date = time();         // default delay is starting today (at confirmation time / equivalent to 'booking')
                switch($deadline['delay_from_event']) {
                    case 'booking':
                        $date = time();
                        break;
                    case 'checkin':
                        $date = $booking['date_from'];
                        break;
                    case 'checkout':
                        $date = $booking['date_to'];
                        break;
                }
                $funding['issue_date'] = $date + ($deadline['delay_from_event_offset'] * 86400);
                $funding['due_date'] = $funding['issue_date'] + ($deadline['delay_count'] * 86400);

                // request funding creation
                try {
                    $new_funding = Funding::create($funding)->read(['id', 'name'])->first();
                    if($deadline['type'] == 'invoice') {
                        // an invoice was requested: convert the installment to an invoice
                        eQual::run('do', 'lodging_funding_convert', ['id' => $new_funding['id'], 'partner_id' => $booking['customer_id']['id']]);
                    }
                }
                catch(Exception $e) {
                    // ignore duplicates (not created)
                }

                ++$funding_order;
            }
        }
    }
}
catch(Exception $e) {
    // ignore funding creation errors
}

// perform additional checks to ensure Booking is in a consistent state

// check booking rental units assignment
eQual::run('do', 'lodging_booking_check-consistency', ['id' => $booking['id']]);

$context->httpResponse()
        ->status(204)
        ->send();
