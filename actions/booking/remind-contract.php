<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use core\Mail;
use equal\email\Email;
use lodging\communication\Template;

list($params, $providers) = eQual::announce([
    'description'   => "Send an email to remind them about the signed contract.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for the send reminder.',
            'type'          => 'integer',
            'required'      => true
        ],
        'email' =>  [
            'description'   => 'Email of the person who is responsible for the contract.',
            'type'          => 'string',
            'required'      => true
        ],
        'lang' =>  [
            'description'   => 'Language of the contract which is defined by the responsible.',
            'type'          => 'string'
        ],
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
                    'center_office_id' => ['id','email', 'email_bcc'],
                    'status',
                    'date_from',
                    'date_to',
                    'center_id' => ['name', 'template_category_id'],
                    'contacts_ids' => ['partner_identity_id' => ['email' , 'lang_id' => ['code']]]
            ])
            ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$template = Template::search([
        ['category_id', '=', $booking['center_id']['template_category_id']],
        ['code', '=', 'reminder'],
        ['type', '=', 'contract']
    ])
    ->read(['parts_ids' => ['name', 'value']], $recipient['lang'])
    ->first(true);

$httpResponse = $context->httpResponse()->status(200);
$result = [];

if(!$template){
    $result = $booking['id'];
    $dispatch->dispatch('lodging.booking.contract.reminder.failed', 'lodging\sale\booking\Booking', $booking['id'], 'important', 'lodging_booking_check-contract-reminder', ['id' => $booking['id']], [], null, $booking['center_office_id']['id']);
    $httpResponse->status(qn_error_http(QN_ERROR_MISSING_PARAM));
}
else {

    $body = $title = '';
    foreach($template['parts_ids'] as $part) {
        if($part['name'] == 'subject') {
            $title = strip_tags($part['value']);
            $data = [
                'booking'   => $booking['name'],
                'center'    => $booking['center_id']['name'],
                'date_from' => date('d/m/Y', $booking['date_from']),
                'date_to'   => date('d/m/Y', $booking['date_to'])
            ];
            foreach($data as $key => $val) {
                $title = str_replace('{'.$key.'}', $val, $title);
            }
        }
        elseif($part['name'] == 'body') {
            $body = $part['value'];
        }
    }

    $signature = '';
    try {
        $data = eQual::run('get', 'lodging_identity_center-signature', [
            'center_id' => $booking['center_id']['id'],
            'lang'      => $params['lang']
        ]);
        $signature = $data['signature'] ?? '';
    }
    catch(Exception $e) {}

    $body .= $signature;
    $message = new Email();
    $message->setTo($params['email'])
        ->setReplyTo($booking['center_office_id']['email'])
        ->setSubject($title)
        ->setContentType('text/html')
        ->setBody($body);

    $bcc = isset($booking['center_office_id']['email_bcc'])?$booking['center_office_id']['email_bcc']:'';
    if(strlen($bcc)) {
        $message->addBcc($bcc);
    }

    Mail::queue($message, 'lodging\sale\booking\Booking', $booking['id']);
    $result = $booking['id'];
    $dispatch->dispatch('lodging.booking.contract.reminder.sent', 'lodging\sale\booking\Booking', $booking['id'], 'notice', null, [], [], null, $booking['center_office_id']['id']);
    $httpResponse->status(200);
}

$httpResponse->body($result)
             ->send();
