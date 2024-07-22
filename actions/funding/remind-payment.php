<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use lodging\communication\Template;
use lodging\sale\booking\Funding;
use lodging\sale\booking\Contract;

list($params, $providers) = eQual::announce([
    'description'   => "Send a funding payment reminder email to booking \"contract\" contact.",
    'help'          => "A communication template must exist for {category}.{code}.{type} :"
                        ." category is the center (establishment) field template_category_id, code is \"reminder\" and type is \"funding\"."
                        ." If a booking contact of type contract is not found then the email is sent to the first contact found with an email address set.",
    'params'        => [
        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the funding to remind payment.",
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'dispatch' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch'] ];

$funding = Funding::id($params['id'])
    ->read([
        'id',
        'is_paid',
        'due_date',
        'booking_id' => [
            'id',
            'date_from',
            'date_to',
            'contacts_ids' => ['type', 'partner_identity_id' => ['email', 'lang_id' => ['code']]],
            'center_id' => ['name', 'template_category_id'],
            'center_office_id' => ['id', 'email_bcc'],
            'contracts_ids'
        ]
    ])
    ->first(true);

if($funding['is_paid']) {
    $dispatch->cancel('lodging.booking.funding.payment_reminder_failed', 'lodging\sale\booking\Booking', $funding['booking_id']['id']);

    throw new Exception('funding_already_paid', QN_ERROR_INVALID_PARAM);
}

$contact = null;
foreach($funding['booking_id']['contacts_ids'] as $c) {
    if(strlen($c['partner_identity_id']['email'] ?? '') > 0) {
        if($c['type'] === 'contract') {
            $contact = $c;
            break;
        }
        if(is_null($contact)) {
            $contact = $c;
        }
    }
}

if(is_null($contact)) {
    $dispatch->dispatch('lodging.booking.funding.payment_reminder_failed', 'lodging\sale\booking\Booking', $funding['booking_id']['id'], 'important', 'lodging_funding_remind-payment', ['id' => $params['id']], [],null, $funding['booking_id']['center_office_id']['id']);
    throw new Exception('missing_contact_email', QN_ERROR_UNKNOWN_OBJECT);
}

$last_contract_id = array_shift($funding['booking_id']['contracts_ids']);
$contract = Contract::id($last_contract_id)->read(['status'])->first(true);

if(in_array($contract['status'], ['pending', 'cancelled']) || $funding['booking_id']['date_from'] >= time()) {
    throw new Exception("sending_skipped", 0);
}

$template = Template::search([
        ['category_id', '=', $funding['booking_id']['center_id']['template_category_id']],
        ['code', '=', 'reminder'],
        ['type', '=', 'funding']
    ])
    ->read(['parts_ids' => ['name', 'value']], $contact['partner_identity_id']['lang_id']['code'])
    ->first(true);

if(is_null($template)) {
    $dispatch->dispatch('lodging.booking.funding.payment_reminder_failed', 'lodging\sale\booking\Booking', $funding['booking_id']['id'], 'important', 'lodging_funding_remind-payment', ['id' => $params['id']], [], null, $funding['booking_id']['center_office_id']['id']);

    throw new Exception('missing_template', QN_ERROR_UNKNOWN_OBJECT);
}

$body = $title = '';
foreach($template['parts_ids'] as $part) {
    if($part['name'] == 'subject') {
        $title = strip_tags($part['value']);
        $data = [
            'booking'   => $funding['booking_id']['name'],
            'center'    => $funding['booking_id']['center_id']['name'],
            'date_from' => date('d/m/Y', $funding['booking_id']['date_from']),
            'date_to'   => date('d/m/Y', $funding['booking_id']['date_to'])
        ];
        foreach($data as $key => $val) {
            $title = str_replace('{'.$key.'}', $val, $title);
        }
    }
    elseif($part['name'] == 'body') {
        $body = $part['value'];
    }
}

// generate signature
$signature = '';
try {
    $data = eQual::run('get', 'lodging_identity_center-signature', [
        'center_id' => $funding['booking_id']['center_id']['id'],
        'lang'      => $contact['partner_identity_id']['lang_id']['code']
    ]);
    $signature = $data['signature'] ?? '';
}
catch(Exception $e) {
    // ignore errors
}

$body .= $signature;

$message = new Email();
$message->setTo($contact['partner_identity_id']['email'])
    ->setSubject($title)
    ->setContentType('text/html')
    ->setBody($body);

if(isset($funding['booking_id']['center_office_id']['email_bcc'])) {
    $message->addBcc($funding['booking_id']['center_office_id']['email_bcc']);
}

Mail::queue($message, 'lodging\sale\booking\Booking', $funding['booking_id']['id']);

$dispatch->dispatch('lodging.booking.funding.payment_reminder_sent', 'lodging\sale\booking\Booking', $funding['booking_id']['id'], 'notice', null, [], [], null, $funding['booking_id']['center_office_id']['id']);
$dispatch->cancel('lodging.booking.funding.payment_reminder_failed', 'lodging\sale\booking\Booking', $funding['booking_id']['id']);

$context->httpResponse()
        ->status(200)
        ->send();
