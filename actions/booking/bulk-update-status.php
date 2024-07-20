<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Booking;

// announce script and fetch parameters values
list($params, $providers) = announce([
    'description'	=>	"Mark a selection of Booking as updated.",
    'params' 		=>	[
        'ids' => [
            'description'       => 'List of Booking identifiers the check against emptyness.',
            'type'              => 'array'
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context']
]);

list($context) = [$providers['context']];

foreach($params['ids'] as $id) {
    eQual::run('do', 'lodging_booking_update-status', ['id' => $id]);
}

$context->httpResponse()
        ->status(204)
        ->send();
