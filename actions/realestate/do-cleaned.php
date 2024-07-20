<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\realestate\RentalUnit;

use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => "Rental unit will marked as none the action required.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the rental unit the check against emptyness.',
            'type'          => 'integer',
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
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [ $providers['context'], $providers['orm'], $providers['auth'], $providers['dispatch']];


$rental_unit = RentalUnit::id($params['id'])
                    ->read(['id', 'status','action_required'])
                    ->first(true);

if(!$rental_unit) {
    throw new Exception("unknown_rental_unit", QN_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($rental_unit['action_required'], ["cleanup_daily","cleanup_full"])){
    throw new Exception("invalid_action_required_rental_unit", QN_ERROR_INVALID_PARAM);
}

// #memo - 'users' group only has read permission on realestate package (so we don't use a Collection)
$orm->update(RentalUnit::getType(), $rental_unit['id'], ['action_required' => 'none']);

$context->httpResponse()
        ->status(204)
        ->send();
