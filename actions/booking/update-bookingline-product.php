<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\catalog\Product;

// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Updates a Booking Line by changed its product. This script is meant to be called by the `booking/services` UI.",
    'params' 		=>	[
        'id' =>  [
            'description'       => 'Identifier of the targeted Booking Line.',
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\BookingLine',
            'required'          => true
        ],
        'product_id' =>  [
            'type'              => 'many2one',
            'description'       => 'Identifier of the product to assign the line to.',
            'foreign_object'    => 'lodging\sale\catalog\Product',
            'default'           => false
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
list($context, $orm) = [$providers['context'], $providers['orm']];

// step-1 - make sure that there is at least one price available (published or unpublished)
$line_id = $params['id'];
$found = false;

// look for published prices
$prices = BookingLine::searchPriceId($orm, $line_id, $params['product_id']);

if(isset($prices[$line_id])) {
    $found = true;
}
// look for unpublished prices
else {
    $prices = BookingLine::searchPriceIdUnpublished($orm, $line_id, $params['product_id']);
    if(isset($prices[$line_id])) {
        $found = true;
    }
}

if(!$found) {
    throw new Exception("missing_price", QN_ERROR_INVALID_PARAM);
}

// step-2 - attempt to update line
BookingLine::id($line_id)->update(['product_id' => $params['product_id']]);

$context->httpResponse()
        ->status(204)
        ->send();
