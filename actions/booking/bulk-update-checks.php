<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

list($params, $providers) = eQual::announce([
    'description'	=>	"Runs a checks update on a selection of Booking.",
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
    'providers' => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

foreach($params['ids'] as $id) {
    eQual::run('do', 'lodging_booking_do-update-check', ['id' => $id]);
}

$context->httpResponse()
        ->status(204)
        ->send();
