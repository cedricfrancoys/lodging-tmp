<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use lodging\sale\booking\channelmanager\Property;

list($params, $providers) = eQual::announce([
    'description'   => 'Sends a request to Stripe for retrieving the payment details from a payment intent reference.',
    'params'        => [
        'property_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\channelmanager\Property',
            'description'       => 'Identifier of the targeted property.',
            'help'              => 'The (local) property_id is needed in order to retrieve the PSP account to which the reference refers to',
            'required'          => true
        ],
        'psp_reference' =>  [
            'description'   => 'Stripe payment intent identifier.',
            'type'          => 'string',
            'required'      => true,
            'example'       => 'pi_3Ng07TJkzhOGpR2F066oJxMi'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];


/*
 Retrieve the data attached to a Stripe Payment Intent.
 A Payment intent ID is returned by Cubilis as PSP reference.
*/

/*
credit card
pi_3Ng07TJkzhOGpR2F066oJxMi
pi_3Nj1yaJkzhOGpR2F0yqPo52M
bancontact
pi_3NhxKHJkzhOGpR2F15s2d7oB
*/

/*

Expected returned structure:
{
    "id": "pi_3OYoUjAC5XsYsC7U1Y5q0Xgw",
    "object": "payment_intent",
    "amount": 3020,
    "amount_capturable": 0,
    "amount_details": {
        "tip": {}
    },
    "amount_received": 0,
    "application": null,
    "application_fee_amount": null,
    "automatic_payment_methods": null,
    "canceled_at": 1705405101,
    "cancellation_reason": "automatic",
    "capture_method": "automatic",
    "client_secret": "pi_3OYoUjAC5XsYsC7U1Y5q0Xgw_secret_*************************",
    "confirmation_method": "automatic",
    "created": 1705318701,
    "currency": "eur",
    "customer": null,
    "description": "Reservation at Hostel van Gogh - Kaleo",
    "invoice": null,
    "last_payment_error": null,
    "latest_charge": null,
    "livemode": true,
    "metadata": {},
    "next_action": null,
    "on_behalf_of": null,
    "payment_method": null,
    "payment_method_configuration_details": null,
    "payment_method_options": {
        "bancontact": {
            "preferred_language": "en"
        }
    },
    "payment_method_types": [
        "bancontact"
    ],
    "processing": null,
    "receipt_email": null,
    "review": null,
    "setup_future_usage": null,
    "shipping": null,
    "source": null,
    "statement_descriptor": null,
    "statement_descriptor_suffix": null,
    "status": "canceled",
    "transfer_data": null,
    "transfer_group": null
}


`latest_charge` property should hold a structure like below:

Array
(
    [id] => txn_3Ng07TJkzhOGpR2F0u1plOZP
    [object] => balance_transaction
    [amount] => 25300
    [available_on] => 1692835200
    [created] => 1692255433
    [currency] => eur
    [description] => Reservation at Kaleo
    [exchange_rate] =>
    [fee] => 847
    [fee_details] => Array
        (
            [0] => Array
                (
                    [amount] => 847
                    [application] =>
                    [currency] => eur
                    [description] => Stripe processing fees
                    [type] => stripe_fee
                )
        )
    [net] => 24453
    [reporting_category] => charge
    [source] => ch_3Ng07TJkzhOGpR2F00******
    [status] => available
    [type] => charge
)
*/

// retrieve secret key associated with the Center Office
$property = Property::id($params['property_id'])->read(['psp_provider', 'psp_key'])->first(true);
if(!$property) {
    throw new Exception("unknown_property", QN_ERROR_UNKNOWN);
}

if(!isset($property['psp_key']) || strlen($property['psp_key']) <= 0) {
    throw new Exception("empty_psp_key", QN_ERROR_UNKNOWN);
}

$private_key = $property['psp_key'];

$request = new HttpRequest("POST https://api.stripe.com/v1/payment_intents/{$params['psp_reference']}");

$response = $request
    ->body(['expand[]' => 'latest_charge.balance_transaction'])
    ->header('Authorization', "Bearer $private_key")
    ->send();

$intent = $response->body();

if(!is_array($intent) || !isset($intent['latest_charge'])) {
    throw new Exception("error_processing_request", QN_ERROR_UNKNOWN);
}

if($intent['status'] != 'succeeded') {
    // possible values are 'canceled', 'available', 'succeeded'
    throw new Exception("invalid_status", QN_ERROR_UNKNOWN);
}

$result = [
    'amount'    => floatval($intent['latest_charge']['balance_transaction']['amount']) / 100.0,
    'fee'       => floatval($intent['latest_charge']['balance_transaction']['fee']) / 100.0,
    'currency'  => strtoupper($intent['latest_charge']['balance_transaction']['currency']),
    'net'       => floatval($intent['latest_charge']['balance_transaction']['net']) / 100.0
];

$context->httpResponse()
        ->body($result)
        ->send();
