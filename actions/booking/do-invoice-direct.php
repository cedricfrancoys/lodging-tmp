<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
$params = eQual::announce([
    'description'   => "Generates the proforma for the balance invoice for a booking.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the booking for which the invoice has to be generated.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ]
]);

// perform do-invoice action with no alternate payer (partner_id)
eQual::run('do', 'lodging_booking_do-invoice', ['id' => $params['id']]);

$context->httpResponse()
        ->status(204)
        ->send();
