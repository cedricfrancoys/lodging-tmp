<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\channelmanager\Identity;
use lodging\sale\customer\Customer;
use core\Lang;

list($params, $providers) = eQual::announce([
    'description'   => "Resolve a customer based on its contact details. \
                        If the customer exists (exact match, case non-sensitive), its ID is returned, otherwise a new customer is created and related ID is returned.",
    'help'          => "No validation is made on received parameters, but in case of the creation of a new entry, all values are sanitized beforehand.",
    'params'        => [
        'firstname' => [
            'type'              => 'string',
            'description'       => 'Customer given name.'
        ],
        'lastname' => [
            'type'              => 'string',
            'description'       => 'Customer surname.'
        ],
        'address_street' => [
            'type'              => 'string',
            'description'       => 'Street and number.'
        ],
        'address_zip' => [
            'type'              => 'string',
            'description'       => 'Postal code.'
        ],
        'address_city' => [
            'type'              => 'string',
            'description'       => 'City.'
        ],
        'address_country' => [
            'type'              => 'string',
            'description'       => 'Country.'
        ],
        'phone' => [
            'type'              => 'string',
            'description'       => 'Phone number.'
        ],
        'email' => [
            'type'              => 'string',
            'description'       => 'Email address.'
        ],
        'lang' => [
            'type'              => 'string',
            'description'       => "Preferred language."
        ],
    ],
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

$valid = true;

if(strlen($params['firstname']) <= 0 || strlen($params['lastname']) <= 0 || strlen($params['address_street']) <= 0 || strlen($params['address_city']) <= 0 || strlen($params['address_zip']) <= 0 || strlen($params['address_country']) <= 0) {
    $valid = false;
}

$identity = Identity::search([
        ['firstname', 'ilike', $params['firstname']],
        ['lastname', 'ilike', $params['lastname']],
        ['address_street', 'ilike', $params['address_street']],
        ['address_city', 'ilike', $params['address_city']],
        ['address_zip', 'ilike', $params['address_zip']],
        ['address_country', 'ilike', $params['address_country']]
    ])
    ->read(['id'])
    ->first(true);

$language = Lang::search(['code', '=', $params['lang']])->read(['id'])->first(true);
// #memo - id 2 is for French
$lang_id = $language['id'] ?? 2;

if($valid && $identity) {
    // lookup for a customer associated with the found identity
    $customer = Customer::search([
            ['owner_identity_id', '=', 1],
            ['partner_identity_id', '=', $identity['id']],
            ['relationship', '=', 'customer']
        ])
        ->read(['id'])
        ->first(true);
}
else {
    // customer does not exist yet
    $customer = null;
    $values = [
            'firstname'         => $params['firstname'],
            'lastname'          => $params['lastname'],
            'address_street'    => $params['address_street'],
            'address_city'      => $params['address_city'],
            'address_zip'       => $params['address_zip'],
            'address_country'   => $params['address_country'],
            'phone'             => str_replace(['.', '/', ' ', '-'], '', $params['phone']),
            'email'             => $params['email'],
            'lang_id'           => $lang_id,
            'is_ota'            => true
        ];

    // sanitize / exclude fields
    // #memo - sanitizing has been removed and any values is accepted by specific entity channelmanager\Identity
    /*
    if(!(preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $values['firstname'])) || strlen($values['firstname']) <= 0) {
        $values['firstname'] = "prénom-invalide";
    }
    if(!(preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $values['lastname'])) || strlen($values['lastname']) <= 0) {
        $values['lastname'] = "nom-invalide";
    }
    if(!(preg_match('/^[A-Z]{2}$/u', $values['address_country']))) {
       $values['address_country'] = 'BE';
    }
    $sanitized_phone = str_replace(['.', '/', ' ', '-'], '', $values['phone']);
    if(!preg_match('/^[\+]?[0-9]{6,13}$/', $sanitized_phone)) {
        unset($values['phone']);
    }
    if(!preg_match('/^[a-zA-Z0-9+_.-]+@[a-zA-Z0-9.-]+$/', $values['email'])) {
        unset($values['email']);
    }
    */
    // create a new identity
    $identity = Identity::create($values)
        ->read(['id'])
        ->first(true);
}

// if no customer was found, create the related customer
if(!$customer) {
    $customer = Customer::create([
            'owner_identity_id'     => 1,
            'partner_identity_id'   => $identity['id'],
            'rate_class_id'         => 4,
            'lang_id'               => $lang_id
        ])
        ->read(['id'])
        ->first(true);
}

$result = [
    'id'                    => $customer['id'],
    'customer_identity_id'  => $identity['id']
];

$context->httpResponse()
        ->body($result)
        ->send();
