<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use Codelicious\Coda\Parser;
use lodging\sale\booking\BankStatement;

list($params, $providers) = announce([
    'description'   => "Parse a raw CODA file and returns it as a list of statement lines.",
    'params'        => [
        'data' =>  [
            'description'   => 'Raw CODA data to parse as statements.',
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['sale.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$user_id = $auth->userId();

if($user_id <= 0) {
    // retricted to identified users
    throw new Exception('unknown_user', QN_ERROR_NOT_ALLOWED);
}

$content = $params['data'];

$content = str_replace("\r\n", "\n", $content);
$lines = explode("\n", $content);

// #memo - parser expects ASCII-compatible chars
// latin chars from non ASCII/UTF-8 charsets (e.g. ISO-8859-1) make the parser to return an empty set of statements)
$lines = array_map( function($line) {
            return mb_convert_encoding($line, "UTF-8", "ISO-8859-1");
        },
        $lines
    );

$parser = new Parser();
$statements = $parser->parse($lines);

$result = [];

foreach ($statements as $statement) {
    $line = [
        'date'          => $statement->getDate()->getTimestamp(),
        'old_balance'   => $statement->getInitialBalance(),
        'new_balance'   => $statement->getNewBalance(),
    ];

    $account = $statement->getAccount();

    $line['account']  = [
        "name"      => $account->getName(),
        "number"    => $account->getNumber(),
        "iban"      => BankStatement::convertBbanToIban($account->getNumber()),
        "bic"       => $account->getBic(),
        "country"   => $account->getCountryCode()
    ];

    $line['transactions'] = [];

    foreach ($statement->getTransactions() as $transaction) {

        $account = $transaction->getAccount();

        $line['transactions'][] = [
            'account'   => [
                "name"      => $account->getName(),
                "bic"       => $account->getBic(),
                "number"    => $account->getNumber(),
                "currency"  => $account->getCurrencyCode()
            ],
            'amount'                => $transaction->getAmount(),
            'message'               => $transaction->getMessage(),
            'structured_message'    => $transaction->getStructuredMessage()
        ];
    }

    $result[] = $line;
}

$context->httpResponse()
        ->status(200)
        ->body($result)
        ->send();


