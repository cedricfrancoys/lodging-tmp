<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\text\TextTransformer;
use lodging\sale\booking\Invoice;
use lodging\finance\accounting\AccountingJournal;
use lodging\identity\CenterOffice;
use lodging\documents\Export;
use core\setting\Setting;
use lodging\sale\catalog\Product;

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
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $om
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [$providers['context'], $providers['orm'], $providers['auth'], $providers['dispatch']];


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

// retrieve the journal of sales
$journal = AccountingJournal::search([['center_office_id', '=', $params['center_office_id']], ['type', '=', 'sales']])->read(['id', 'code'])->first(true);

if(!$journal) {
    throw new Exception("unknown_center_office", QN_ERROR_UNKNOWN_OBJECT);
}


/*
    Retrieve non-exported invoices.
*/
// #memo - there might be several kind of invoices, we only consider the ones attached either to a booking or to a list of orders
$invoices = Invoice::search(
        [
            [
                ['is_exported', '=', false],
                ['center_office_id', '=', $params['center_office_id']],
                ['booking_id', '>', 0],
                ['status', '<>', 'proforma'],
            ],
            [
                ['is_exported', '=', false],
                ['center_office_id', '=', $params['center_office_id']],
                ['has_orders', '=', true],
                ['status', '<>', 'proforma'],
            ]
        ],
        [
            'sort'  => ['number' => 'asc']
        ])
    ->read([
        'id',
        'name',
        'date',
        'due_date',
        'type',
        'status',
        // 'price',
        // #memo - accounting price is the amount to be recorded in accountancy (does not include installment payments)
        'accounting_price',
        'is_deposit',
        'organisation_id',
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
        ],
        'has_orders',
        'center_office_id' => [
            'analytic_section_id' => [
                'code'
            ]
        ],
        'booking_id' => [
            'name',
            'date_from',
            'date_to',
            'center_id' => [
                'analytic_section_id' => [
                    'code'
                ]
            ]
        ],
        'funding_id' => ['payment_reference', 'due_date'],
        'invoice_lines_ids' => [
            'id',
            'name',
            'total',
            'price',
            'product_id',
            'downpayment_invoice_id' => ['id', 'status'],
            'price_id' => [
                'id',
                'vat_rate',
                'accounting_rule_id' => [
                    'accounting_rule_line_ids' => [
                        'account_id' => [
                            'code'
                        ],
                        'share'
                    ]
                ]
            ]
        ]
    ])
    ->get(true);

if(count($invoices) == 0) {
    // exit with no error
    throw new Exception('no match', 0);
}


ob_start();
echo "[CLIENTS_FACT]
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

// export file holding the schema for invoices: HOPDIV_FACT.sch
ob_start();
echo "[HOPDIV_FACT]
FileType = Fixed
Charset = ascii
Field1=TDBK,Char,04,00,00
Field2=TFYEAR,Char,05,00,04
Field3=TYEAR,Long Integer,11,00,09
Field4=TMONTH,Long Integer,11,00,20
Field5=TDOCNO,Long Integer,11,00,31
Field6=TINTMODE,Char,01,00,42
Field7=TCOMPAN,Char,10,00,43
Field8=TDOCDATE,Date,11,00,53
Field9=TTYPCIE,Char,01,00,64
Field10=TDUEDATE,Date,11,00,65
Field11=TAMOUNT,Float,21,02,76
Field12=TREMINT,Char,40,00,97
Field13=TINVVCS,Char,10,00,137
";
$invoices_header_schema = ob_get_clean();

ob_start();
// export file holding the schema for lines: LOPDIV_FACT.sch
echo "[LOPDIV_FACT]
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
Field16=TBASVAT,Float,21,02,163
Field17=TVATTOTAMN,Float,21,02,184
Field18=TVATAMN,Float,21,02,205
Field19=TVSTORED,Char,10,00,226
";
$invoices_lines_schema = ob_get_clean();



/*
    Check invoices consistency : discard invalid invoices and emit a warning.
*/

foreach($invoices as $index => $invoice) {
    if( !isset($invoice['partner_id']) ||
        !isset($invoice['partner_id']['partner_identity_id'])
    ) {
        ob_start();
        print_r($invoice);
        $out = ob_get_clean();
        trigger_error("APP::Ignoring invalid invoice : missing partner info for invoice {$invoice['name']} [{$invoice['id']}] - $out", QN_REPORT_WARNING);
        unset($invoices[$index]);
    }
    elseif(!$invoice['has_orders'] && !isset($invoice['booking_id'])) {
        trigger_error("APP::Ignoring invalid invoice : missing booking info for invoice {$invoice['name']} [{$invoice['id']}]", QN_REPORT_WARNING);
        unset($invoices[$index]);
    }
    // #memo - for cancelled invoices and orders invoices, it is ok not to have funding
}


/*
    Generate headers: CLIENTS_FACT.txt
*/


$result = [];

foreach($invoices as $invoice) {

    $customer_name = substr(strtoupper(TextTransformer::normalize($invoice['partner_id']['name'])), 0, 40);
    $customer_vat = substr(str_replace([' ', '.', '-', '_'], '', $invoice['partner_id']['partner_identity_id']['vat_number']), 0, 12);
    $customer_phone = substr(str_replace([' ', '.', '-', '_'], '', $invoice['partner_id']['partner_identity_id']['phone']), 0, 14);
    $customer_fax = substr(str_replace([' ', '.', '-', '_'], '', $invoice['partner_id']['partner_identity_id']['fax']), 0, 14);
    $customer_address = substr(strtoupper(TextTransformer::normalize($invoice['partner_id']['partner_identity_id']['address_street'])), 0, 40);
    $customer_zip = substr($invoice['partner_id']['partner_identity_id']['address_zip'], 0, 10);
    $customer_city = substr(strtoupper(TextTransformer::normalize($invoice['partner_id']['partner_identity_id']['address_city'])), 0, 40);
    $customer_country = substr(strtoupper($invoice['partner_id']['partner_identity_id']['address_country']), 0, 2);
    $customer_lang = 'F';
    if(isset($invoice['partner_id']['partner_identity_id']['lang_id']['code']) && is_string($invoice['partner_id']['partner_identity_id']['lang_id']['code']) && strlen($invoice['partner_id']['partner_identity_id']['lang_id']['code']) >= 1) {
        // #memo - BOB uses a single letter
        $customer_lang = strtoupper(substr($invoice['partner_id']['partner_identity_id']['lang_id']['code'], 0, 1));
    }

    $values = [
        // Field1=CID,Char,10,00,00
        str_pad('C'.$invoice['partner_id']['partner_identity_id']['id'], 10, ' ', STR_PAD_RIGHT),
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
    Generate headers: HOPDIV_FACT.txt
*/

$invoices_header_data = [];

foreach($invoices as $invoice) {
    // when invoice is a credit note, TAMOUNT must be inverted (in most cases should be negative)
    if($invoice['type'] == 'credit_note') {
        $invoice['accounting_price'] = -$invoice['accounting_price'];
    }
    // for orders, use static memo as internal comments
    if($invoice['has_orders']) {
        $comments = 'VENTES COMPTOIR';
    }
    // for bookings, use as internal comments
    else {
        $comments = $invoice['booking_id']['name'].' '.date('d/m/Y', $invoice['booking_id']['date_from']).'-'.date('d/m/Y', $invoice['booking_id']['date_to']);
    }
    $values = [
        // Field1=TDBK,Char,04,00,00
        str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        // Field2=TFYEAR,Char,05,00,04
        str_pad(date('Y', $invoice['date']), 5,' ', STR_PAD_RIGHT),
        // Field3=TYEAR,Long Integer,11,00,09
        str_pad(date('Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        // Field4=TMONTH,Long Integer,11,00,20
        str_pad(date('m', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        // Field5=TDOCNO,Long Integer,11,00,31
        str_pad(str_replace('-', '', $invoice['name']), 11,' ', STR_PAD_RIGHT),
        // Field6=TINTMODE,Char,01,00,42
        str_pad('S', 1,' ',STR_PAD_LEFT),
        // Field7=TCOMPAN,Char,10,00,43
        str_pad('C'.$invoice['partner_id']['partner_identity_id']['id'], 10, ' ', STR_PAD_RIGHT),
        // Field8=TDOCDATE,Date,11,00,53
        str_pad(date('d/m/Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        // Field9=TTYPCIE,Char,01,00,64
        str_pad('C', 1,' ',STR_PAD_LEFT),
        // Field10=TDUEDATE,Date,11,00,65
        str_pad(date('d/m/Y', $invoice['due_date']), 11,' ', STR_PAD_RIGHT),
        // Field11=TAMOUNT,Float,21,02,76
        str_pad(str_replace('.', ',', sprintf('%.02f', $invoice['accounting_price'])), 21,' ', STR_PAD_LEFT),
        // Field12=TREMINT,Char,40,00,97
        str_pad($comments, 40,' ', STR_PAD_RIGHT),
        // Field13=TINVVCS,Char,10,00,137
        str_pad(isset($invoice['funding_id']['payment_reference'])?(substr($invoice['funding_id']['payment_reference'], 0, 10)):'', 10, ' ', STR_PAD_RIGHT)
    ];

    $invoices_header_data[$invoice['id']] = implode('', $values);
}



/*
    Generate lines: LOPDIV_FACT.txt
*/

$account_sales = Setting::get_value('finance', 'invoice', 'account.sales', '7000000');
$account_downpayment = Setting::get_value('finance', 'invoice', 'downpayment.account', '4460000');
$account_discount = Setting::get_value('finance', 'invoice', 'account.discount', '7080000');

// #todo - store this value in the settings
// discount product is the same for all organisations: KA-Remise-A [65]
$discount_product_id = 65;

$invoices_lines_data = [];

foreach($invoices as $invoice) {
    $invoice_lines_accounts = [];

    // retrieve downpayment product
    $downpayment_product_id = 0;
    $downpayment_sku = Setting::get_value('sale', 'invoice', 'downpayment.sku.'.$invoice['organisation_id']);
    if($downpayment_sku) {
        $products_ids = Product::search(['sku', '=', $downpayment_sku])->ids();
        if($products_ids) {
            $downpayment_product_id = reset($products_ids);
        }
    }

    // pass-1 : group all lines by account_id
    foreach($invoice['invoice_lines_ids'] as $lid => $line) {
        $vat = $line['price'] - $line['total'];
        $amount = $line['total'];
        // when invoice is a credit note, TAMOUNT and TVATOTAMN must be inverted (#memo - not necessarily negative)
        if($invoice['type'] == 'credit_note') {
            $amount = -$amount;
            $vat = -$vat;
        }
        // #memo - Sage BOB will reject invoices with no line and lines with nul amount, so the situation of an invoice with a single nul line is invalid / should not occur (discount without distinct product ?)
        if($amount == 0.0) {
            continue;
        }
        // #memo - we don't use $line['price_id']['vat_rate'] since VAT rate can be set manually
        // #memo - this might lead to incorrect values if distinct VAT rates have been applied on distinct products relating to a same account id (in such case, the accounting software will mark the import as invalid)
        $vat_rate = ($amount != 0.0)?abs(round($vat / $amount, 2)):0.0;
        $accounting_rule_lines_ids = [];
        if($line['product_id'] == $downpayment_product_id) {
            // deposit invoice or credit note
            if($invoice['is_deposit'] || $invoice['type'] == 'credit_note') {
                $accounting_rule_lines_ids = [
                        ['account_id' => ['code' => $account_downpayment], 'share' => 1.0]
                    ];
            }
            // balance invoice
            elseif($invoice['type'] == 'invoice') {
                // if the line refers to an invoiced downpayment and if the related downpayment invoice hasn't been cancelled
                // #memo - we perform this test because some invoices include lines that incorrectly state non-deposit downpayments (that shouldn't be on the invoice)
                if(isset($line['downpayment_invoice_id']) && $line['downpayment_invoice_id'] && isset($line['downpayment_invoice_id']['status']) && $line['downpayment_invoice_id']['status'] == 'invoice') {
                    // add a writing symmetrical to the deposit invoice
                    // #memo - price should be a negative value
                    $accounting_rule_lines_ids = [
                            ['account_id' => ['code' => $account_downpayment], 'share' => 1.0]
                        ];
                }
            }
        }
        elseif($line['product_id'] == $discount_product_id) {
            $accounting_rule_lines_ids = [
                    ['account_id' => ['code' => $account_discount], 'share' => 1.0]
                ];
        }
        elseif(isset($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])) {
            $accounting_rule_lines_ids = $line['price_id']['accounting_rule_id']['accounting_rule_line_ids'];
        }
        elseif($line['price'] != 0.0) {
            // #memo - this should not occur! - products shouldn't be embedded to invoices if there is no accounting rule
            trigger_error("APP::No related price found for non-null amount for line {$line['name']} [{$line['product_id']}] with price [{$line['price_id']}] of invoice {$invoice['name']} [{$invoice['id']}]", QN_REPORT_WARNING);
            // remove invoice from processed invoices, and skip all lines
            unset($invoices_header_data[$invoice['id']]);
            // #todo - dispatch an alert that relates to a dedicated controller
            $dispatch->dispatch('lodging.accounting.invoice.invalid', 'lodging\sale\booking\Invoice', $invoice['id'], 'important', null, [], [], null, $params['center_office_id']);
            continue 2;
        }

        $amount_remaining = $amount;
        $vat_remaining = $vat;

        $i = 0;
        $n = count($accounting_rule_lines_ids);

        foreach($accounting_rule_lines_ids as $rlid => $rline) {
            // if there is no entry yet, create one
            if(!isset($invoice_lines_accounts[$rline['account_id']['code']])) {
                $invoice_lines_accounts[$rline['account_id']['code']] = [
                        'vat_rate'  => $vat_rate,
                        'vat'       => 0.0,
                        'amount'    => 0.0
                    ];
            }

            if($i == $n-1) {
                $line_amount = round($amount_remaining, 2);
                $line_vat = round($vat_remaining, 2);
            }
            else {
                $line_amount = round($amount * $rline['share'], 2);
                $line_vat = round($line_amount * $vat_rate, 2);
            }

            // add vat and amount to the entry, according to share
            $invoice_lines_accounts[$rline['account_id']['code']]['vat'] += $line_vat;
            $invoice_lines_accounts[$rline['account_id']['code']]['amount'] += $line_amount;

            $amount_remaining -= $line_amount;
            $vat_remaining -= $line_vat;

            ++$i;
        }
    }

    // pass-2 : generate lines based on account entries
    $index = 1;
    foreach($invoice_lines_accounts as $account_code => $account_values) {
        // #memo - Sage BOB will reject lines with an amount of 0.0
        if($account_values['amount'] == 0.0) {
            // skip line
            continue;
        }

        $analytic_section = '';

        // downpayments are not part of the analytic accounting
        if($account_code != $account_downpayment) {
            if($invoice['has_orders']) {
                $analytic_section = $invoice['center_office_id']['analytic_section_id']['code'];
            }
            else {
                $analytic_section = $invoice['booking_id']['center_id']['analytic_section_id']['code'];
            }
        }

        // #memo - remove any '_' and trailing chars (since that notation is not supported by Sage BOB)
	    $pos = strpos($account_code, '_');
	    $account_code = ($pos !== false)?substr($account_code, 0, $pos):$account_code;
        // for orders, use static memo as internal comments
        if($invoice['has_orders']) {
            $comments = (($invoice['type'] == 'invoice')?'F. ':'NC.').'VENTES COMPTOIR';
        }
        // for bookings, use invoice type, customer name and booking number
        else {
            $comments = (($invoice['type'] == 'invoice')?'F. ':'NC.').strtoupper( substr(TextTransformer::normalize($invoice['partner_id']['name']), 0, 30) ).'/'.$invoice['booking_id']['name'];
        }

        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $invoice['date']), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad(str_replace('-', '', $invoice['name']), 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad($index, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('S', 1,' ',STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('A', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad($account_code, 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('C', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad( $comments, 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad($analytic_section, 4,' ', STR_PAD_RIGHT),
            // Field16=TBASVAT,Float,21,02,163
            str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['amount'])), 21,' ', STR_PAD_LEFT),
            // Field17=TVATTOTAMN,Float,21,02,184
            str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['vat'])), 21,' ', STR_PAD_LEFT),
            // Field18=TVATAMN,Float,21,02,205
            str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['vat'])), 21,' ', STR_PAD_LEFT),
            // Field19=TVSTORED,Char,10,00,226
            str_pad('NSS  '.intval($account_values['vat_rate'] * 100), 10,' ', STR_PAD_RIGHT),
        ];

        ++$index;
        $invoices_lines_data[] = implode('', $values);
    }

}


// generate the zip archive
$tmpfile = tempnam(sys_get_temp_dir(), "zip");
$zip = new ZipArchive();
if($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    // could not create the ZIP archive
    throw new Exception('Unable to create a ZIP file.', QN_ERROR_UNKNOWN);
}

// embed schema files
$zip->addFromString('CLIENTS_FACT.sch', $customers_schema);
$zip->addFromString('HOPDIV_FACT.sch', $invoices_header_schema);
$zip->addFromString('LOPDIV_FACT.sch', $invoices_lines_schema);
// embed data files
$zip->addFromString('CLIENTS_FACT.txt', $customers_data);
$zip->addFromString('HOPDIV_FACT.txt', implode("\r\n", array_values($invoices_header_data))."\r\n");
$zip->addFromString('LOPDIV_FACT.txt', implode("\r\n", $invoices_lines_data)."\r\n");

$zip->close();

// read raw data
$data = file_get_contents($tmpfile);
unlink($tmpfile);

if($data === false) {
    throw new Exception('Unable to retrieve ZIP file content.', QN_ERROR_UNKNOWN);
}

// switch to root user
$auth->su();

// create the export archive
Export::create([
    'center_office_id'      => $params['center_office_id'],
    'export_type'           => 'invoices',
    'data'                  => $data
]);

// mark processed invoices as exported
Invoice::ids(array_keys($invoices_header_data))->update(['is_exported' => true]);

$context->httpResponse()
        ->status(201)
        ->send();