<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\email\Email;
use equal\email\EmailAttachment;

use communication\TemplateAttachment;
use documents\Document;
use lodging\sale\booking\Invoice;
use core\setting\Setting;
use core\Mail;
use core\Lang;

// announce script and fetch parameters values
list($params, $providers) = announce([
    'description'	=>	"Send an instant email with given details with an invoice as attachment.",
    'params' 		=>	[
        'invoice_id' => [
            'description'   => 'Identifier of the booking related to the sending of the email.',
            'type'          => 'integer',
            'required'      => true
        ],
        'title' =>  [
            'description'   => 'Title of the message.',
            'type'          => 'string',
            'required'      => true
        ],
        'message' => [
            'description'   => 'Body of the message.',
            'type'          => 'string',
            'required'      => true
        ],
        'sender_email' => [
            'description'   => 'Email address FROM.',
            'type'          => 'string',
            'usage'         => 'email',
            'required'      => true
        ],
        'recipient_email' => [
            'description'   => 'Email address TO.',
            'type'          => 'string',
            'usage'         => 'email',
            'required'      => true
        ],
        'recipients_emails' => [
            'description'   => 'CC email addresses.',
            'type'          => 'array',
            'usage'         => 'email'
        ],
        'attachments_ids' => [
            'description'   => 'List of identitiers of attachments to join.',
            'type'          => 'array',
            'default'       => []
        ],
        'mode' =>  [
            'description'   => 'Mode in which document has to be rendered: simple or detailed.',
            'type'          => 'string',
            'selection'     => ['simple', 'grouped', 'detailed'],
            'default'       => 'grouped'
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

$invoice = Invoice::id($params['invoice_id'])
    ->read([
        'booking_id' => ['id', 'center_id' => ['id', 'center_office_id' => ['email_bcc']]]
    ])
    ->first();

if(!$invoice) {
    throw new Exception("unknown_invoice", QN_ERROR_UNKNOWN_OBJECT);
}

$booking = $invoice['booking_id'];

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// generate attachment
$attachment = eQual::run('get', 'lodging_booking_print-invoice', [
    'id'        => $params['invoice_id'],
    'view_id'   =>'print.default',
    'lang'      => $params['lang'],
    'mode'      => $params['mode']
]);

// get 'invoice' term translation
$main_attachment_name = Lang::get_term('sale', 'invoice', 'invoice', $params['lang']);

// generate signature
$signature = '';
try {
    $data = eQual::run('get', 'lodging_identity_center-signature', [
        'center_id'     => $booking['center_id']['id'],
        'lang'          => $params['lang']
    ]);
    $signature = (isset($data['signature']))?$data['signature']:'';
}
catch(Exception $e) {
    // ignore errors
}

$params['message'] .= $signature;

/** @var EmailAttachment[] */
$attachments = [];

// push main attachment
$attachments[] = new EmailAttachment($main_attachment_name.'.pdf', (string) $attachment, 'application/pdf');

// add attachments whose ids have been received as param ($params['attachments_ids'])
if(count($params['attachments_ids'])) {
    $params['attachments_ids'] = array_unique($params['attachments_ids']);
    $template_attachments = TemplateAttachment::ids($params['attachments_ids'])->read(['name', 'document_id'])->get();
    foreach($template_attachments as $tid => $tdata) {
        $document = Document::id($tdata['document_id'])->read(['name', 'data', 'type'])->first();
        if($document) {
            $attachments[] = new EmailAttachment($document['name'], $document['data'], $document['type']);
        }
    }
}

// create message
$message = new Email();
$message->setTo($params['recipient_email'])
        ->setReplyTo($params['sender_email'])
        ->setSubject($params['title'])
        ->setContentType("text/html")
        ->setBody($params['message']);

$bcc = isset($booking['center_id']['center_office_id']['email_bcc'])?$booking['center_id']['center_office_id']['email_bcc']:'';

if(strlen($bcc)) {
    $message->addBcc($bcc);
}

if(isset($params['recipients_emails'])) {
    $recipients_emails = array_diff($params['recipients_emails'], (array) $params['recipient_email']);
    foreach($recipients_emails as $address) {
        $message->addCc($address);
    }
}

// append attachments to message
foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

// queue message
Mail::queue($message, 'lodging\sale\booking\Booking', $booking['id']);

$context->httpResponse()
        ->status(204)
        ->send();