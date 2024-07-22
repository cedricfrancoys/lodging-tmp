<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;

list($params, $providers) = announce([
    'description'   => "Check that the emails have been sent correctly.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against emptyness.',
            'type'          => 'integer',
            'required'      => true
        ],

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth','cron','dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $cron, $dispatch) = [ $providers['context'], $providers['cron'], $providers['dispatch']];


// read booking object
$booking = Booking::id($params['id'])
                  ->read(['id','center_office_id' ,'mails_ids' => ['id','status']])
                  ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$result = [];
$httpResponse = $context->httpResponse()->status(200);


if($booking['mails_ids']) {
    $last_email = end($booking['mails_ids']);
    if($last_email['status'] != 'sent') {
        $result[] = $booking['id'];
        $dispatch->dispatch('lodging.booking.email.send', 'lodging\sale\booking\Booking', $params['id'], 'warning', null, [], [], null, $booking['center_office_id']);
    }
    else{
        $dispatch->cancel('lodging.booking.email.send', 'lodging\sale\booking\Booking', $params['id']);
    }
}

$httpResponse->body($result)
             ->send();
