<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Contract;

list($params, $providers) = announce([
    'description'   => "Sets contract as cancelled.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the contract to cancel.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];


/*
    This controller should not be called.
    Instead booking cancellation controller should be used.
*/

// Contract::id($params['id'])->update(['status' => 'cancelled']);


$context->httpResponse()
        ->status(204)
        ->send();