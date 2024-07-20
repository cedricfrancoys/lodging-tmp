<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\Contract;

list($params, $providers) = eQual::announce([
    'description'   => "Check if the contract sent has been signed by the client 10 days after sending it.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for the reminder.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$booking = Booking::id($params['id'])
            ->read(['id',
                    'name',
                    'center_office_id' => ['id'],
                    'status',
                    'has_contract',
                    'contracts_ids',
                    'center_id' => ['name', 'template_category_id'],
                    'contacts_ids' => ['partner_identity_id' => ['email' , 'lang_id' => ['code']]]
            ])
            ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$booking['has_contract'] || empty($booking['contracts_ids'])) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}

$recipient = [
    'email' => '',
    'lang' => ''
];

$contract_id = array_shift($booking['contracts_ids']);
$contract = Contract::id($contract_id)->read(['status'])->first();

$httpResponse = $context->httpResponse()->status(200);

$result = $booking['id'];
if ($booking['status'] == 'confirmed' && $contract['status'] != 'signed'){
    foreach($booking['contacts_ids'] as $contact) {
        if(isset($contact['partner_identity_id']['email']) && strlen($contact['partner_identity_id']['email'])) {
            $recipient['email'] = $contact['partner_identity_id']['email'];
            $recipient['lang'] = $contact['partner_identity_id']['lang_id']['code'];
            break;
        }
    }
    if(empty($recipient['email'])) {
        $dispatch->dispatch('lodging.booking.contract.reminder.failed', 'lodging\sale\booking\Booking', $booking['id'], 'important', 'lodging_booking_check-contract-reminder', ['id' => $booking['id']], [], null, $booking['center_office_id']['id']);
        $httpResponse->status(qn_error_http(QN_ERROR_MISSING_PARAM));
    }
    else {
        $dispatch->cancel('lodging.booking.contract.reminder.failed', 'lodging\sale\booking\Booking', $booking['id']);
        $result = $booking['id'];
        eQual::run('do', 'lodging_booking_remind-contract', [
                'id'    => $booking['id'],
                'email' => $recipient['email'],
                'lang'  => $recipient['lang']
            ]);
    }

}
else {
    $dispatch->cancel('lodging.booking.contract.reminder.failed', 'lodging\sale\booking\Booking', $booking['id']);
    $dispatch->cancel('lodging.booking.contract.reminder.sent', 'lodging\sale\booking\Booking', $booking['id']);
}

$httpResponse->body($result)
             ->send();
