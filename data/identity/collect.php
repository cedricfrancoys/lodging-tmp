<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\orm\Domain;
use lodging\identity\Identity;
use lodging\identity\Contact;

list($params, $providers) = announce([
    'description'   => 'Advanced search for Identities: returns a collection of Identities according to extra paramaters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'          => 'string',
            'default'       => 'lodging\identity\Identity'
        ],
        'identifier' => [
            'type'          => 'integer',
            'description'   => "Number for querying on ID number (strict).",
        ],
        'query' => [
            'type'          => 'string',
            'description'   => "Unordered keywords for identity name.",
        ],
        'contact_identity_id' => [
            'type'              => 'many2one',
            'foreign_object'    => Identity::getType(),
            'description'       => 'Related contact identity.'
        ]
    ],
    'response'      => [
        //'content-type'  => 'application/json',
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


$domain = $params['domain'];

/*
    query : filter by non-contiguous keyword
*/

if(isset($params['query']) && strlen($params['query'])) {
    $sub_domain = [];
    $words = explode(' ', $params['query']);

    foreach($words as $word) {
        // semi-strict search: all words but order independant
        $sub_domain = Domain::conditionAdd($sub_domain, ['display_name', 'ilike', "%{$word}%"]);
        /*
        // lose search (not all words)
        $clause = [['display_name', 'ilike', "%{$word}%"]];
        $sub_domain = Domain::clauseAdd($sub_domain, $clause);
        */
    }

    $identities_ids = Identity::search($sub_domain)->ids();

    if(count($identities_ids)) {
        $domain = Domain::conditionAdd($domain, ['id', 'in', $identities_ids]);
    }
    else {
        // void result
        $domain = Domain::conditionAdd($domain, ['id', '=', 0]);
    }
}

/*
    contact_identity_id : search in contacts (customer should be in it as well)
*/
if(isset($params['contact_identity_id']) && $params['contact_identity_id']) {
    $contacts = Contact::search(['partner_identity_id', '=', $params['contact_identity_id']])->read(['owner_identity_id'])->get(true);

    $identities_ids = array_map(function ($a) {return $a['owner_identity_id'];}, $contacts);
    if(count($identities_ids)) {
        $domain = Domain::conditionAdd($domain, ['id', 'in', $identities_ids]);
    }
    else {
        // void result
        $domain = Domain::conditionAdd($domain, ['id', '=', 0]);
    }
}

if(isset($params['identifier']) && $params['identifier'] > 0) {
    $domain = Domain::conditionAdd([], ['id', '=', $params['identifier']]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
