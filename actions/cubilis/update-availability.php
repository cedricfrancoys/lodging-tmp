<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use lodging\sale\booking\channelmanager\Property;

list($params, $providers) = eQual::announce([
    'description'   => "Send an acknowledgment notification to Cubilis for a given reservation, using a `OTA_HotelAvailNotifRQ` request.",
    'params'        => [
        'property_id' => [
            'description'   => 'Identifier of the property (from Cubilis).',
            'type'          => 'integer',
            'required'      => true
        ],
        'room_type_id' => [
            'type'          => 'integer',
            'description'   => 'The ID of the room type, as provided by Cubilis (ex. 48554).',
            'help'          => "Identifier of the room type is provided in the `OTA_HotelResRS`response, as `RoomID` attribute of the `RoomType` node.",
            'required'      => true
        ],
        'date' => [
            'type'          => 'date',
            'description'   => 'The date (night) for which the availability of the targeted room type must be updated.',
            'required'      => true
        ],
        'availability' => [
            'type'          => 'integer',
            'description'   => 'The new value to be set as availability for the given room type.',
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

$xml = Property::cubilis_HotelAvailNotifRQ_generateXmlPayload($params['property_id'], $property['username'], $property['password'], $property['api_id'], $params['room_type_id'], $params['date'], $params['availability']);

$entrypoint_url = "https://cubilis.eu/plugins/PMS_ota/set_availability.aspx";

$request = new HttpRequest('POST '.$entrypoint_url);
$request->header('Content-Type', 'text/xml');

$response = $request->setBody($xml, true)->send();

$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception('request_rejected', QN_ERROR_INVALID_PARAM);
}

/*
// Error response sample
<?xml version="1.0" encoding="utf-8"?>
<OTA_HotelAvailNotifRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="2.0">
    <Errors>
        <Error Code="607" ShortText="Authentication error" Type="" />
    </Errors>
</OTA_HotelAvailNotifRS>
*/

// #memo - raw body can be retrieved by using $response->getBody(true);
$envelope = $response->body();

// check response consistency
if(!isset($envelope['name']) || $envelope['name'] != 'OTA_HotelAvailNotifRS') {
    throw new Exception('Invalid response received (valid XML but unexpected format).', QN_ERROR_UNKNOWN);
}

if(isset($envelope['children']['Errors'])) {
    ob_start();
    print_r($envelope['children']['Errors']);
    $report = ob_get_clean();
    throw new Exception('Error received '.$report, QN_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->send();
