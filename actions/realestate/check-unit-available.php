<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\realestate\RentalUnit;
use lodging\sale\booking\Consumption;

list($params, $providers) = announce([
    'description'   => "Verify that the rental unit is not assigned to a booking. This check is meant to be called by plan-repair (called from Planning).",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the rental unit the check against emptyness.',
            'type'              => 'integer',
            'required'          => true
        ],
        'date_from' =>  [
            'description'       => 'First date of the time interval.',
            'type'              => 'date',
            'required'          => true
        ],

        'date_to' =>  [
            'description'       => 'End date of the time interval.',
            'type'              => 'date',
            'required'          => true
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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
list($context) = [ $providers['context']];


$rental_unit = RentalUnit::id($params['id']) ->read(['id'])->first();

if(!$rental_unit) {
    throw new Exception("unknown_rental_unit", QN_ERROR_UNKNOWN_OBJECT);
}

$result = Consumption::search([
        ['date', '>=', $params['date_from']],
        ['date', '<=', $params['date_to']] ,
        ['rental_unit_id' , '=' , $rental_unit['id']],
        ['is_rental_unit' , '=', true]
    ])->get(true);

$context->httpResponse()
    ->status(200)
    ->body($result)
    ->send();
