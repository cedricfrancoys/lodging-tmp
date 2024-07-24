<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\text\TextTransformer;
use lodging\sale\booking\Payment;
use lodging\finance\accounting\AccountingJournal;
use lodging\identity\CenterOffice;
use lodging\documents\Export;

list($params, $providers) = announce([
    'description'   => "Creates an export archive containing all emitted invoices that haven't been exported yet (for external accounting software).",
    'params'        => [
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => CenterOffice::getType(),
            'description'       => 'Management Group to which the center belongs.',
            'required'          => true
        ],
    ],
    'access' => [
        'groups'            => ['finance.default.user'],
    ],
    'response'      => [
        'charset'             => 'utf-8',
        'accept-origin'       => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

// make sure we have right on all involved objects: switch to root user
$auth->su();

/*
    This controller generates an export file related to invoices of a given center Office.
    Invoices can only be exported once, but the result of the export generation is kept as history that can be re-downloaded if necessary.

    Kaleo uses a double import of the CODA files (in Discope AND in accounting soft [BOB])

    Postulats
    * l'origine des fichiers n'a pas d'importance
    * les noms de fichiers peuvent avoir de l'importance
    * les fichiers peuvent regrouper des lignes issues de différents centres
    * les imports COMPTA se font par centre de gestion : il faut un export par centre de gestion

*/

// retrieve center_office
$office = CenterOffice::id($params['center_office_id'])->read(['id'])->first(true);

if(!$office) {
    throw new Exception("unknown_center_office", QN_ERROR_UNKNOWN_OBJECT);
}

// retrieve the journal of miscellaneous operations
$journal = AccountingJournal::search([['center_office_id', '=', $params['center_office_id']], ['type', '=', 'miscellaneous']])->read(['id', 'code', 'index'])->first(true);

if(!$journal) {
    throw new Exception("unknown_center_office", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    Retrieve non-exported payments.
*/

$payments_ids = Payment::search([
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['funding_id', '>', 0],
            ['payment_origin', '=', 'bank']
        ],
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['funding_id', '>', 0],
            ['payment_origin', '=', 'cashdesk'],
            ['payment_method', 'in', ['bank_card', 'cash']],
            ['status', '=', 'paid']
        ],
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['funding_id', '>', 0],
            ['payment_origin', '=', 'online'],
            ['has_psp', '=', true],
            ['psp_type', '=', 'stripe']
        ],
/*
// #memo - waiting to be confirmed (the teams to be ready for the accounting)
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['order_payment_id', '>', 0],
            ['payment_origin', '=', 'cashdesk'],
            ['payment_method', 'in', ['bank_card', 'cash']]
        ]
*/
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['center_office_id', '=', 5],
            ['order_payment_id', '>', 0],
            ['payment_origin', '=', 'cashdesk'],
            ['payment_method', 'in', ['bank_card', 'cash']]
        ]
    ])
    ->ids();

$payments = Payment::ids($payments_ids)
    ->read([
        'id',
        'created',
        'amount',
        'statement_line_id' => ['date'],
        'has_psp',
        'psp_type',
        'psp_fee_amount',
        'funding_id' => [
            'id',
            'type',
            'invoice_id' => [
                'partner_id' => [
                    'id',
                    'name',
                    'partner_identity_id' => [
                        'id',
                        'address_street',
                        'address_dispatch',
                        'address_city',
                        'address_zip',
                        'address_country',
                        'vat_number',
                        'phone',
                        'fax',
                        'lang_id' => [
                            'code'
                        ]
                    ]
                ]
            ],
            'booking_id' => [
                'id',
                'name',
                'customer_id' => [
                    'id',
                    'name',
                    'partner_identity_id' => [
                        'id',
                        'address_street',
                        'address_dispatch',
                        'address_city',
                        'address_zip',
                        'address_country',
                        'vat_number',
                        'phone',
                        'fax',
                        'lang_id' => [
                            'code'
                        ]
                    ]
                ]
            ]
        ],
        'order_payment_id' => [
            'order_id' => [
                'customer_id' => [
                    'id',
                    'name',
                    'partner_identity_id' => [
                        'id',
                        'address_street',
                        'address_dispatch',
                        'address_city',
                        'address_zip',
                        'address_country',
                        'vat_number',
                        'phone',
                        'fax',
                        'lang_id' => [
                            'code'
                        ]
                    ]
                ]
            ]
        ]
    ])
    ->get(true);


$payments_count = count($payments);
if($payments_count == 0) {
    // exit with no error
    throw new Exception('no match', 0);
}


// generate file holding the schema for customers: CLIENTS_REGL.sch
ob_start();
echo "[CLIENTS_REGL]
FileType = Fixed
CharSet = ascii
Field1=CID,Char,10,00,00
Field2=CCUSTYPE,Char,01,00,10
Field3=CSUPTYPE,Char,01,00,11
Field4=CNAME1,Char,40,00,12
Field5=CNAME2,Char,40,00,52
Field6=CADDRESS1,Char,40,00,92
Field7=CADDRESS2,Char,40,00,132
Field8=CZIPCODE,Char,10,00,172
Field9=CLOCALITY,Char,40,00,182
Field10=CLANGUAGE,Char,02,00,222
Field11=CISPERS,Bool,01,00,224
Field12=CCUSCAT,Char,03,00,225
Field13=CCURRENCY,Char,03,00,228
Field14=CVATCAT,Char,01,00,231
Field15=CVATREF,Char,02,00,232
Field16=CVATNO,Char,12,00,234
Field17=CTELNO,Char,14,00,246
Field18=CFAXNO,Char,14,00,260
Field19=CCUSVNAT1,Char,03,00,274
Field20=CCUSVNAT2,Char,03,00,277
Field21=CCUSVATCMP,Float,20,02,280
Field22=CCUSCTRACC,Char,10,00,300
Field23=CCUSIMPUTA,Char,10,00,310
Field24=CCTRYCODE,Char,02,00,320
Field25=CBANKCODE,Char,06,00,322
Field26=CBANKNO,Char,19,00,328
Field27=CISWARNING,Bool,01,00,347
Field28=CISREADONL,Bool,01,00,348
Field29=CISBLOCK,Bool,01,00,349
Field30=CISSECRET,Bool,01,00,350
Field31=CCUSPAYDELAY,Char,06,00,351
Field32=CREMCAT,Char,05,00,357
Field33=CREMSTATUS,Char,01,00,362
Field34=CREATEDATE,TimeStamp,30,00,363
Field35=MODIFYDATE,TimeStamp,30,00,393
Field36=AUTHOR,Char,10,00,423
Field37=CNATREGISTRYID,Char,15,00,433
Field38=CCUSPDISCDEL,Long Integer,11,00,448
Field39=CCUSTEMPLID,Char,10,00,459
Field40=CMEMO,Char,200,00,469
";
$customers_schema = ob_get_clean();

// generate file holding the schema for payments: HOPDIV_REGL.sch
ob_start();
echo "[HOPDIV_REGL]
FileType = Fixed
Charset = ascii
Field1=TDBK,Char,04,00,00
Field2=TFYEAR,Char,05,00,04
Field3=TYEAR,Long Integer,11,00,09
Field4=TMONTH,Long Integer,11,00,20
Field5=TDOCNO,Long Integer,11,00,31
Field6=TINTMODE,Char,01,00,42
";
$payments_header_schema = ob_get_clean();

// export file holding the schema for payments lines: LOPDIV_REGL.sch
ob_start();
echo "[LOPDIV_REGL]
FileType = Fixed
Charset = ascii
Field1=TDBK,Char,04,00,00
Field2=TFYEAR,Char,05,00,04
Field3=TYEAR,Long Integer,11,00,09
Field4=TMONTH,Long Integer,11,00,20
Field5=TDOCNO,Long Integer,11,00,31
Field6=TDOCLINE,Long Integer,11,00,42
Field7=TTYPELINE,Char,01,00,53
Field8=TDOCDATE,Date,11,00,54
Field9=TACTTYPE,Char,01,00,65
Field10=TACCOUNT,Char,10,00,66
Field11=TCURAMN,Float,21,02,76
Field12=TAMOUNT,Float,21,02,97
Field13=TDC,Char,01,00,118
Field14=TREM,Char,40,00,119
Field15=COST_GITES,Char,04,00,159
";
$payments_lines_schema = ob_get_clean();


/*
    Generate headers: CLIENTS_REGL.txt
*/

$result = [];

foreach($payments as $payment) {
    // ignore payments that are not related to an invoice
    if(is_null($payment['funding_id']['invoice_id'])) {
        // #memo - export payments for funding not yet invoiced is allowed (we use temporary accounts to handle this situation)
        // continue;
    }

    // retrieve targeted partner
    if(isset($payment['funding_id'])) {
        if($payment['funding_id']['type'] == 'invoice' && isset($payment['funding_id']['invoice_id']['partner_id'])) {
            $partner = $payment['funding_id']['invoice_id']['partner_id'];
        }
        elseif(isset($payment['funding_id']['booking_id']['customer_id'])) {
            $partner = $payment['funding_id']['booking_id']['customer_id'];
        }
        else {
            // malformed payment : ignore
            continue;
        }
    }
    elseif(isset($payment['order_payment_id']['order_id']['customer_id'])) {
        $partner = $payment['order_payment_id']['order_id']['customer_id'];
    }
    else {
        // malformed payment : ignore
        continue;
    }

    $customer_name = substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 40);
    $customer_vat = substr(str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['vat_number']), 0, 12);
    $customer_phone = substr(str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['phone']), 0, 14);
    $customer_fax = substr(str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['fax']), 0, 14);
    $customer_address = substr(strtoupper(TextTransformer::normalize($partner['partner_identity_id']['address_street'])), 0, 40);
    $customer_zip = substr($partner['partner_identity_id']['address_zip'], 0, 10);
    $customer_city = substr(strtoupper(TextTransformer::normalize($partner['partner_identity_id']['address_city'])), 0,40);
    $customer_country = substr(strtoupper($partner['partner_identity_id']['address_country']), 0, 2);
    $customer_lang = 'F';
    if(isset($partner['partner_identity_id']['lang_id']['code']) && is_string($partner['partner_identity_id']['lang_id']['code']) && strlen($partner['partner_identity_id']['lang_id']['code']) >= 1) {
        // #memo - BOB uses a single letter
        $customer_lang = substr(strtoupper($partner['partner_identity_id']['lang_id']['code']), 0, 1);
    }

    $values = [
        // Field1=CID,Char,10,00,00
        str_pad('C'.$partner['partner_identity_id']['id'], 10, ' ', STR_PAD_RIGHT),
        // Field2=CCUSTYPE,Char,01,00,10
        str_pad('C', 1,' ',STR_PAD_LEFT),
        // Field3=CSUPTYPE,Char,01,00,11
        str_pad('U', 1,' ',STR_PAD_LEFT),
        // Field4=CNAME1,Char,40,00,12
        str_pad($customer_name, 40, ' ', STR_PAD_RIGHT),
        // Field5=CNAME2,Char,40,00,52
        str_pad('', 40, ' ', STR_PAD_RIGHT),
        // Field6=CADDRESS1,Char,40,00,92
        str_pad('', 40, ' ', STR_PAD_RIGHT),
        // Field7=CADDRESS2,Char,40,00,132
        str_pad($customer_address, 40, ' ', STR_PAD_RIGHT),
        // Field8=CZIPCODE,Char,10,00,172
        str_pad($customer_zip, 10, ' ', STR_PAD_RIGHT),
        // Field9=CLOCALITY,Char,40,00,182
        str_pad($customer_city, 40, ' ', STR_PAD_RIGHT),
        // Field10=CLANGUAGE,Char,02,00,222
        str_pad($customer_lang, 2, ' ', STR_PAD_RIGHT),
        // Field11=CISPERS,Bool,01,00,224
        str_pad('0', 1, ' ', STR_PAD_RIGHT),
        // Field12=CCUSCAT,Char,03,00,225
        str_pad('', 3, ' ', STR_PAD_RIGHT),
        // Field13=CCURRENCY,Char,03,00,228
        str_pad('EUR', 3, ' ', STR_PAD_RIGHT),
        // Field14=CVATCAT,Char,01,00,231
        str_pad('', 1, ' ', STR_PAD_RIGHT),
        // Field15=CVATREF,Char,02,00,232
        str_pad($customer_country, 2, ' ', STR_PAD_RIGHT),
        // Field16=CVATNO,Char,12,00,234
        str_pad($customer_vat, 12, ' ', STR_PAD_RIGHT),
        // Field17=CTELNO,Char,14,00,246
        str_pad($customer_phone, 14, ' ', STR_PAD_RIGHT),
        // Field18=CFAXNO,Char,14,00,260
        str_pad($customer_fax, 14, ' ', STR_PAD_RIGHT),
        // Field19=CCUSVNAT1,Char,03,00,274
        str_pad('', 3, ' ', STR_PAD_RIGHT),
        // Field20=CCUSVNAT2,Char,03,00,277
        str_pad('', 3, ' ', STR_PAD_RIGHT),
        // Field21=CCUSVATCMP,Float,20,02,280
        str_pad('', 20, ' ', STR_PAD_RIGHT),
        // Field22=CCUSCTRACC,Char,10,00,300
        str_pad('', 10, ' ', STR_PAD_RIGHT),
        // Field23=CCUSIMPUTA,Char,10,00,310
        str_pad('', 10, ' ', STR_PAD_RIGHT),
        // Field24=CCTRYCODE,Char,02,00,320
        str_pad($customer_country, 2, ' ', STR_PAD_RIGHT),
        // Field25=CBANKCODE,Char,06,00,322
        str_pad('', 6, ' ', STR_PAD_RIGHT),
        // Field26=CBANKNO,Char,19,00,328
        str_pad('', 19, ' ', STR_PAD_RIGHT),
        // Field27=CISWARNING,Bool,01,00,347
        str_pad('0', 1, ' ', STR_PAD_RIGHT),
        // Field28=CISREADONL,Bool,01,00,348
        str_pad('0', 1, ' ', STR_PAD_RIGHT),
        // Field29=CISBLOCK,Bool,01,00,349
        str_pad('0', 1, ' ', STR_PAD_RIGHT),
        // Field30=CISSECRET,Bool,01,00,350
        str_pad('0', 1, ' ', STR_PAD_RIGHT),
        // Field31=CCUSPAYDELAY,Char,06,00,351
        str_pad('', 6, ' ', STR_PAD_RIGHT),
        // Field32=CREMCAT,Char,05,00,357
        str_pad('', 5, ' ', STR_PAD_RIGHT),
        // Field33=CREMSTATUS,Char,01,00,362
        str_pad('', 1, ' ', STR_PAD_RIGHT),
        // Field34=CREATEDATE,TimeStamp,30,00,363
        str_pad('', 30, ' ', STR_PAD_RIGHT),
        // Field35=MODIFYDATE,TimeStamp,30,00,393
        str_pad('', 30, ' ', STR_PAD_RIGHT),
        // Field36=AUTHOR,Char,10,00,423
        str_pad('', 10, ' ', STR_PAD_RIGHT),
        // Field37=CNATREGISTRYID,Char,15,00,433
        str_pad('', 15, ' ', STR_PAD_RIGHT),
        // Field38=CCUSPDISCDEL,Long Integer,11,00,448
        str_pad('', 11, ' ', STR_PAD_RIGHT),
        // Field39=CCUSTEMPLID,Char,10,00,459
        str_pad('', 10, ' ', STR_PAD_RIGHT),
        // Field40=CMEMO,Char,200,00,469
        str_pad('', 200, ' ', STR_PAD_RIGHT),
    ];

    $result[] = implode('', $values);
}

$customers_data = implode("\r\n", $result)."\r\n";


/*
    Generate headers: HOPDIV_REGL.txt
*/

$result = [];
$offset = 0;
foreach($payments as $payment) {
    // ignore payments that are not related to an invoice
    // #memo - export payments for funding not yet invoiced - we use temporary accounts to handle this situation
    if(is_null($payment['funding_id']['invoice_id'])) {
        // continue;
    }

    // retrieve targeted partner
    if(isset($payment['funding_id'])) {
        if($payment['funding_id']['type'] == 'invoice' && isset($payment['funding_id']['invoice_id']['partner_id'])) {
            $partner = $payment['funding_id']['invoice_id']['partner_id'];
        }
        elseif(isset($payment['funding_id']['booking_id']['customer_id'])) {
            $partner = $payment['funding_id']['booking_id']['customer_id'];
        }
        else {
            // malformed payment : ignore
            continue;
        }
    }
    elseif(isset($payment['order_payment_id']['order_id']['customer_id'])) {
        $partner = $payment['order_payment_id']['order_id']['customer_id'];
    }
    else {
        // malformed payment : ignore
        continue;
    }

    $date = $payment['created'];
    // if payment refers to a statement line, use the date of the latter
    if($payment['statement_line_id']) {
        $date = $payment['statement_line_id']['date'];
    }

    $values = [
        // Field1=TDBK,Char,04,00,00
        str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        // Field2=TFYEAR,Char,05,00,04
        str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
        // Field3=TYEAR,Long Integer,11,00,09
        str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
        // Field4=TMONTH,Long Integer,11,00,20
        str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
        // Field5=TDOCNO,Long Integer,11,00,31
        str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
        // Field6=TINTMODE,Char,01,00,42
        str_pad('B', 1,' ',STR_PAD_LEFT),
    ];

    $result[] = implode('', $values);
    ++$offset;
}

$payments_header_data = implode("\r\n", $result)."\r\n";


/*
    Generate lines: LOPDIV_REGL.txt
*/

$result = [];
// we use offset + journal index as virtual document ref. for payments
$offset = 0;
foreach($payments as $payment) {
    // ignore payments that are not related to an invoice
    // #memo - export payments for funding not yet invoiced - we use temporary accounts to handle this situation
    if(is_null($payment['funding_id']['invoice_id'])) {
        // continue;
    }

    $remark = '';

    // retrieve targeted partner
    if(isset($payment['funding_id'])) {
        $remark = ($payment['funding_id']['booking_id']['name']).' - IMPORT REGLT : '.($offset + $journal['index']);
        if($payment['funding_id']['type'] == 'invoice' && isset($payment['funding_id']['invoice_id']['partner_id'])) {
            $partner = $payment['funding_id']['invoice_id']['partner_id'];
        }
        elseif(isset($payment['funding_id']['booking_id']['customer_id'])) {
            $partner = $payment['funding_id']['booking_id']['customer_id'];
        }
        else {
            // malformed payment : ignore
            continue;
        }
    }
    elseif(isset($payment['order_payment_id']['order_id']['customer_id'])) {
        $remark = 'VENTE COMPTOIR : '.($offset + $journal['index']);
        $partner = $payment['order_payment_id']['order_id']['customer_id'];
    }
    else {
        // malformed payment : ignore
        continue;
    }

    $date = $payment['created'];
    // if payment refers to a statement line, use the date of the latter
    if($payment['statement_line_id']) {
        $date = $payment['statement_line_id']['date'];
    }

    // create lines for accounting entries (2 or 3)

    // first line : credit for the customer account
    $values = [
        // Field1=TDBK,Char,04,00,00
        str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        // Field2=TFYEAR,Char,05,00,04
        str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
        // Field3=TYEAR,Long Integer,11,00,09
        str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
        // Field4=TMONTH,Long Integer,11,00,20
        str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
        // Field5=TDOCNO,Long Integer,11,00,31
        str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
        // Field6=TDOCLINE,Long Integer,11,00,42
        str_pad(0, 11,' ', STR_PAD_RIGHT),
        // Field7=TTYPELINE,Char,01,00,53
        str_pad('B', 1,' ', STR_PAD_LEFT),
        // Field8=TDOCDATE,Date,11,00,54
        str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
        // Field9=TACTTYPE,Char,01,00,65
        str_pad('C', 1,' ', STR_PAD_RIGHT),
        // Field10=TACCOUNT,Char,10,00,66
        str_pad('C'.$partner['partner_identity_id']['id'], 10,' ', STR_PAD_RIGHT),
        // Field11=TCURAMN,Float,21,02,76
        str_pad('0,00', 21,' ', STR_PAD_LEFT),
        // Field12=TAMOUNT,Float,21,02,97
        str_pad(str_replace('.', ',', sprintf('%.02f', $payment['amount'])), 21,' ', STR_PAD_LEFT),
        // Field13=TDC,Char,01,00,118
        str_pad('C', 1,' ', STR_PAD_RIGHT),
        // Field14=TREM,Char,40,00,119
        str_pad($remark, 40,' ', STR_PAD_RIGHT),
        // Field15=COST_GITES,Char,04,00,159
        str_pad('', 4,' ', STR_PAD_RIGHT),
    ];
    $result[] = implode('', $values);

    // second line : debit for the temporary account ("compte d'attente")

    $map_temp_accounts = [
        1   => '4990200',        // GG
        2   => '4990210',        // Eupen
        3   => '4990220',        // Han
        4   => '4990290',        // LLN
        5   => '4990230',        // Ovifat
        6   => '4990240',        // Rochefort
        7   => '4990270',        // VSG
        8   => '4990250',        // Wanne
        9   => '4990260'         // HVG
    ];

    $temp_account = $map_temp_accounts[$office['id']];

    if(isset($payment['funding_id'])) {
        $remark = ($payment['funding_id']['booking_id']['name']).' - '.substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 15).' Virement IMPORT';
    }
    else {
        $remark = 'VENTE COMPTOIR - IMPORT';
    }

    // scenario 1 : regular payment (1 entry)
    if(!$payment['has_psp']) {
        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad(1, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('B', 1,' ', STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('A', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad($temp_account, 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $payment['amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('D', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad($remark, 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad('', 4,' ', STR_PAD_RIGHT),
        ];
        $result[] = implode('', $values);
    }
    // scenario 2 : online payment involving PSP (2 entries)
    else {
        if($payment['psp_type'] != 'stripe') {
            // #todo - send an email to admin
            throw new Exception('non_supported_psp', QN_ERROR_UNKNOWN);
        }
        if(is_null($payment['psp_fee_amount']) || $payment['psp_fee_amount'] <= 0) {
            // #todo - send an email to admin
            // throw new Exception('invalid_psp_fee', QN_ERROR_UNKNOWN);
        }

        // entry 1 : amount minus fees to temp account
        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad(1, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('B', 1,' ', STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('A', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad($temp_account, 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $payment['amount']-$payment['psp_fee_amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('D', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad( ($payment['funding_id']['booking_id']['name']).' - '.substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 15).' Virement IMPORT', 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad('', 4,' ', STR_PAD_RIGHT),
        ];
        $result[] = implode('', $values);

        // entry 2 : PSP fees to Stripe account
        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad(2, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('B', 1,' ', STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('S', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad('STRIPE', 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $payment['psp_fee_amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('D', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad( ($payment['funding_id']['booking_id']['name']).' - '.substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 15).' Commission PSP', 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad('', 4,' ', STR_PAD_RIGHT),
        ];
        if($payment['psp_fee_amount'] > 0) {
            $result[] = implode('', $values);
        }
    }

    ++$offset;
}

$payments_lines_data = implode("\r\n", $result)."\r\n";


// generate the zip archive
$tmpfile = tempnam(sys_get_temp_dir(), "zip");
$zip = new ZipArchive();
if($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    // could not create the ZIP archive
    throw new Exception('Unable to create a ZIP file.', QN_ERROR_UNKNOWN);
}

// embed schema files
$zip->addFromString('CLIENTS_REGL.sch', $customers_schema);
$zip->addFromString('HOPDIV_REGL.sch', $payments_header_schema);
$zip->addFromString('LOPDIV_REGL.sch', $payments_lines_schema);
// embed data files
$zip->addFromString('CLIENTS_REGL.txt', $customers_data);
$zip->addFromString('HOPDIV_REGL.txt', $payments_header_data);
$zip->addFromString('LOPDIV_REGL.txt', $payments_lines_data);

$zip->close();

// read raw data
$data = file_get_contents($tmpfile);
unlink($tmpfile);

if($data === false) {
    throw new Exception('Unable to retrieve ZIP file content.', QN_ERROR_UNKNOWN);
}

// create the export archive
Export::create([
    'center_office_id'      => $params['center_office_id'],
    'export_type'           => 'payments',
    'data'                  => $data
]);


// update journal index according to the number of payemnts
AccountingJournal::id($journal['id'])->update(['index' => $journal['index']+$payments_count]);

// mark processed payements as exported and
Payment::ids($payments_ids)->update(['is_exported' => true]);

$context->httpResponse()
        ->status(201)
        ->send();