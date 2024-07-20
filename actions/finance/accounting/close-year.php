<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use lodging\identity\CenterOffice;

list($params, $providers) = eQual::announce([
    'description'   => "Cette action clôturera l'année comptable. Les nouvelles factures seront désormais sur l'année en cours et il ne sera plus possible d'émettre des factures sur l'année précédente.\n
                        ATTENTION: cette opération en peut pas être annulée.",
    'params'        => [],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 */
list($context, $orm, $auth, $dispatch) = [ $providers['context'], $providers['orm'] ];


$year = date('Y');
$fiscal_year = Setting::get_value('finance', 'invoice', 'fiscal_year');

if(!$fiscal_year) {
    throw new Exception('missing_fiscal_year', EQ_ERROR_INVALID_CONFIG);
}

if(intval($year) <= intval($fiscal_year)) {
    throw new Exception('fiscal_year_mismatch', EQ_ERROR_CONFLICT_OBJECT);
}

// update fiscal year to current year
Setting::set_value('finance', 'invoice', 'fiscal_year', $year);

// reset invoice sequences for all Center Offices
$center_offices = CenterOffice::search()->read(['id', 'code'])->get(true);
foreach($center_offices as $center_office) {
    Setting::set_value('lodging', 'invoice', 'sequence.'.$center_office['code'], 1);
}

$context->httpResponse()
        ->status(204)
        ->send();
