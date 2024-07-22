<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Contract;
use lodging\sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Mark a contract as signed (signed version has been received).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted contract.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
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
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

// read contract object
$contract = Contract::id($params['id'])
                  ->read(['id', 'name', 'status', 'booking_id', 'valid_until'])
                  ->first(true);

if(!$contract) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}

if($contract['valid_until'] < time()) {
    // #memo - we allow this in order to relieve from the paperwork
    // throw new Exception("outdated_contract", QN_ERROR_NOT_ALLOWED);
}

/*
// #memo - we allow marking a contract directly as signed, even if not previously sent
if($contract['status'] != 'sent') {
    throw new Exception("invalid_status", QN_ERROR_NOT_ALLOWED);
}
*/

// Update booking status
Contract::id($params['id'])->update(['status' => 'signed']);

// remove any existing scheduled overdue check (set up in `send-contract`)
$cron->cancel("booking.contract.overdue.{$contract['booking_id']}");

// remove any pending alert
$dispatch->cancel('lodging.booking.contract.unsigned', 'lodging\sale\booking\Booking', $contract['booking_id']);

// Check if required payment have been paid in the meantime and update booking accordingly
Booking::updateStatusFromFundings($orm, [$contract['booking_id']]);

$context->httpResponse()
        ->status(200)
        ->send();
