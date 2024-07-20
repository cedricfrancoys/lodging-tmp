<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use lodging\sale\booking\channelmanager\Booking;
use lodging\sale\booking\channelmanager\Property;

list($params, $providers) = eQual::announce([
    'description'   => "Send an acknowledgment notification to Cubilis for a given reservation, using a `OTA_NotifReportRQ` request.",
    'params'        => [
        'property_id' => [
            'description'   => 'Identifier of the property (from Cubilis).',
            'type'          => 'integer',
            'required'      => true
        ],
        'reservations_ids' => [
            'type'          => 'array',
            'description'   => 'List of reservation IDs, as provided by Cubilis (ex. 44641530).',
            'help'          => "Identifier of the reservation is provided in the `OTA_HotelResRS` response, as `ResID_Value` attribute of the `HotelReservationID` node.",
            'required'      => true
        ]
    ],
    'constants' => ['ROOT_APP_URL'],
    'access' => [
        'visibility'    => 'protected',
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $auth) = [ $providers['context'], $providers['auth'] ];

// #memo - temporary solution to prevent calls from non-production server
if(constant('ROOT_APP_URL') != 'https://discope.yb.run') {
    throw new Exception('wrong_host', QN_ERROR_INVALID_CONFIG);
}

// #memo - each we need the credentials from the Center
$property = Property::search(['extref_property_id', '=', $params['property_id']])->read(['id', 'username', 'password', 'api_id'])->first(true);

if(!$property) {
    throw new Exception('unknown_property', QN_ERROR_UNKNOWN_OBJECT);
}

$xml = Property::cubilis_NotifReportRQ_generateXmlPayload($params['property_id'], $property['username'], $property['password'], $property['api_id'], $params['reservations_ids']);

$entrypoint_url = "https://cubilis.eu/plugins/PMS_ota/confirmreservations.aspx";

$request = new HttpRequest('POST '.$entrypoint_url);
$request->header('Content-Type', 'text/xml');

$response = $request->setBody($xml, true)->send();

$status = $response->getStatusCode();

if($status != 200) {
    throw new Exception('request_rejected', QN_ERROR_INVALID_PARAM);
}

$context->httpResponse()
        ->body($result)
        ->send();
