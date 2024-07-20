<?php
use lodging\sale\booking\Consumption;
use lodging\realestate\RentalUnit;
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/


// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Mark a selection of Rental Units as cleaned.",
    'params' 		=>	[
        'ids' => [
            'description'       => 'List of rental unit consumption identifiers to check for emptiness.',
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

/**
 * @var \equal\php\Context                  $context
 */
list($context) = [ $providers['context']];

$consumptions = Consumption::ids($params['ids'])->read(['id', 'rental_unit_id'])->get(true);
foreach($consumptions as $consumption) {
    RentalUnit::id($consumption['rental_unit_id'])->update(['action_required' => 'none']);
}

$context->httpResponse()
        ->status(204)
        ->send();
