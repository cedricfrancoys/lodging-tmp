<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\email\Email;
use equal\email\EmailAttachment;

use communication\Template;
use lodging\sale\booking\Booking;
use core\setting\Setting;
use core\Mail;

// announce script and fetch parameters values
list($params, $providers) = announce([
    'description'	=>	"Send an instant email with given details with a booking quote as attachment.",
    'params' 		=>	[
        'id' => [
            'description'   => 'Identifier of the booking related to the sending of the email.',
            'type'          => 'integer',
            'required'      => true
        ],
        'lang' =>  [
            'description'   => 'Language to use for multilang contents.',
            'type'          => 'string',
            'usage'         => 'language/iso-639',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'constants'             => ['DEFAULT_LANG'],
    'access' => [
        'groups'            => ['booking.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'cron']
]);


// init local vars with inputs
list($context, $cron) = [ $providers['context'], $providers['cron'] ];

$booking = Booking::id($params['id'])->read(['center_id', 'contacts_ids' => ['partner_identity_id' => ['email']]])->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// retrieve body and title

$title = '';
$body = '';

$template = Template::search([
                        ['category_id', '=', $booking['center_id']['template_category_id']],
                        ['code', '=', 'expired'],
                        ['type', '=', 'quote']
                    ])
                    ->read(['parts_ids' => ['name', 'value']], $params['lang'])
                    ->first(true);

if(!$template) {
    throw new Exception("missing_template", QN_ERROR_UNKNOWN_OBJECT);
}

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'header') {
        $title = $part['value'];
    }
    else if($part['name'] == 'body') {
        $body = $part['value'];
    }
}

foreach($booking['contacts_ids'] as $contact) {
    foreach($contact['partner_identity_id'] as $identity) {
        if(isset($identity['email']) && strlen($identity['email'])) {
            $recipient_email = $identity['email'];
            break;
        }
    }
}

// generate signature
$signature = '';
try {
    $data = eQual::run('get', 'lodging_identity_center-signature', [
        'center_id'     => $booking['center_id'],
        'lang'          => $params['lang']
    ]);
    $signature = (isset($data['signature']))?$data['signature']:'';
}
catch(Exception $e) {
    // ignore errors
}

$body .= $signature;

// create message
$message = new Email();
$message->setTo($recipient_email)
        ->setSubject($title)
        ->setContentType("text/html")
        ->setBody($body);

// queue message
Mail::queue($message, 'lodging\sale\booking\Booking', $params['id']);

$context->httpResponse()
        ->status(204)
        ->send();
