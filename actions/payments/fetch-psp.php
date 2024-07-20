<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use lodging\sale\booking\channelmanager\Property;
use lodging\sale\booking\Payment;

list($params, $providers) = announce([
    'description'   => "Enriches a Payment entity with fee amount from PSP, if available.",
    'help'          => "This controller is meant to be scheduled after the creation of a reservation imported from Cubilis.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the Payment for which data is requested.',
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\sale\booking\Payment',
            'required'          => true
        ]
    ],
    'constants'     => ['ROOT_APP_URL', 'EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$result = [];

try {

    $payment = Payment::id($params['id'])
        ->read(['id', 'center_office_id', 'has_psp', 'psp_type', 'psp_ref', 'booking_id' => ['id', 'name', 'extref_reservation_id']])
        ->first(true);

    if(!$payment) {
        throw new Exception('unknown_payment', QN_ERROR_UNKNOWN_OBJECT);
    }

    $property = Property::search(['center_office_id', '=', $payment['center_office_id']])->read(['id'])->first(true);

    if(!$property) {
        throw new Exception('missing_parent_property', QN_ERROR_UNKNOWN_OBJECT);
    }

    if($payment['has_psp']) {
        if($payment['psp_type'] == 'stripe') {
            $psp_payment = eQual::run('get', 'lodging_stripe_payment', ['property_id' => $property['id'], 'psp_reference' => $payment['psp_ref']]);
            $result = $psp_payment;
            if($psp_payment && isset($psp_payment['fee']) && isset($psp_payment['currency']) ) {
                Payment::id($params['id'])->update([
                    'psp_fee_amount'    => $psp_payment['fee'],
                    'psp_fee_currency'  => $psp_payment['currency']
                ]);
            }
            else {
                throw new Exception('invalid_psp_response', QN_ERROR_UNKNOWN);
            }
        }
    }
}
catch(Exception $e) {
    // build email message
    $message = new Email();
    $message->setTo(constant('EMAIL_ERRORS_RECIPIENT'))
            ->setSubject('Discope - ERREUR (Stripe)')
            ->setContentType("text/html")
            ->setBody("<html>
                    <body>
                    <p>Erreur inattendue lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
                    <pre>".$e->getMessage()."</pre>
                    <p>La réservation [{$payment['booking_id']['name']} - {$payment['booking_id']['id']}] existe-t-elle dans Cubilis sous la référence [{$payment['booking_id']['extref_reservation_id']}] ?</p>
                    </body>
                </html>");

    // queue message
    Mail::queue($message);
}

$context->httpResponse()
        ->status(200)
        ->body($result)
        ->send();
