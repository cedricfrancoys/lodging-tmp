<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;

use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

use equal\data\DataFormatter;

use lodging\sale\booking\Booking;
use lodging\sale\booking\Consumption;
use lodging\communication\Template;
use lodging\communication\TemplatePart;
use core\setting\Setting;

list($params, $providers) = announce([
    'description'   => "Render a booking quote as a PDF document, given its id.",
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the booking to print.',
            'type'          => 'integer',
            'required'      => true
        ],
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.default'
        ],
        'mode' =>  [
            'description'   => 'Mode in which document has to be rendered: simple or detailed.',
            'type'          => 'string',
            'selection'     => ['simple', 'grouped', 'detailed'],
            'default'       => 'grouped'
        ],
        'lang' =>  [
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ],
        'output' =>  [
            'description'   => 'Output format of the document.',
            'type'          => 'string',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]
    ],
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'orm']
]);


list($context, $orm) = [$providers['context'], $providers['orm']];

/*
    Retrieve the requested template
*/

$entity = 'lodging\sale\booking\Booking';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = QN_BASEDIR."/packages/{$package}/views/{$class_path}.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}


// read booking
$fields = [
    'name',
    'modified',
    'status',
    'date_from',
    'date_to',
    'total',
    'price',
    'is_price_tbc',
    'customer_identity_id' => [
            'id',
            'display_name',
            'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country',
            'phone',
            'mobile',
            'email'
    ],
    'customer_id' => [
        'partner_identity_id' => [
            'id',
            'display_name',
            'type',
            'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country',
            'type',
            'phone',
            'mobile',
            'email',
            'has_vat',
            'vat_number'
        ]
    ],
    'center_id' => [
        'name',
        'manager_id' => ['name'],
        'address_street',
        'address_city',
        'address_zip',
        'phone',
        'email',
        'bank_account_iban',
        'template_category_id',
        'use_office_details',
        'center_office_id' => [
            'code',
            'address_street',
            'address_city',
            'address_zip',
            'phone',
            'email',
            'signature',
            'bank_account_iban',
            'bank_account_bic'
        ],
        'organisation_id' => [
            'id',
            'legal_name',
            'address_street',
            'address_zip',
            'address_city',
            'email',
            'phone',
            'fax',
            'website',
            'registration_number',
            'has_vat',
            'vat_number',
            'bank_account_iban',
            'bank_account_bic',
            'signature'
        ]
    ],
    'contacts_ids' => [
        'type',
        'partner_identity_id' => [
            'display_name',
            'phone',
            'mobile',
            'email',
            'title'
        ]
    ],
    'booking_lines_groups_ids' => [
        'name',
        'has_pack',
        'is_locked',
        'pack_id'  => ['label'],
        'qty',
        'unit_price',
        'vat_rate',
        'total',
        'price',
        'fare_benefit',
        'rate_class_id' => ['id', 'name', 'description'],
        'date_from',
        'date_to',
        'nb_pers',
        'booking_lines_ids' => [
            'name',
            'product_id' => ['label'],
            'description',
            'qty',
            'unit_price',
            'free_qty',
            'discount',
            'total',
            'price',
            'vat_rate',
            'qty_accounting_method',
            'price_adapters_ids' => ['type', 'value', 'is_manual_discount']
        ]
    ]

];


$booking = Booking::id($params['id'])->read($fields, $params['lang'])->first();

if(!$booking) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}

$img_path = 'public/assets/img/brand/Kaleo.png';
$img_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

if($booking['center_id']['organisation_id']['id'] == 2) {
    $img_path = 'public/assets/img/brand/Villers.png';
}
elseif($booking['center_id']['organisation_id']['id'] == 3) {
    $img_path = 'public/assets/img/brand/Mozaik.png';
}
elseif($booking['center_id']['organisation_id']['id'] == 100) {
    $img_path = 'public/assets/img/brand/Fer_a_cheval.png';
}


if(file_exists($img_path)) {
    $img = file_get_contents($img_path);
    $img_url = "data:image/png;base64, ".base64_encode($img);
}

$values = [
    'header_img_url'        => $img_url,
    'quote_header_html'     => '',
    'quote_notice_html'     => '',
    'status'                => $booking['status'],

    'is_price_tbc'          => $booking['is_price_tbc'],
    'customer_name'         => substr($booking['customer_id']['partner_identity_id']['display_name'], 0, 66),

    // by default, there is no ATTN - if required, it is set below
    'attn_name'             => '',
    'attn_address1'         => '',
    'attn_address2'         => '',

    'contact_name'          => '',
    'contact_phone'         => (strlen($booking['customer_id']['partner_identity_id']['phone']))?$booking['customer_id']['partner_identity_id']['phone']:$booking['customer_id']['partner_identity_id']['mobile'],
    'contact_email'         => $booking['customer_id']['partner_identity_id']['email'],
    'customer_address1'     => $booking['customer_id']['partner_identity_id']['address_street'],
    'customer_address2'     => $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'].(($booking['customer_id']['partner_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_id']['partner_identity_id']['address_country']):''),    
    'customer_country'      => $booking['customer_id']['partner_identity_id']['address_country'],
    'customer_has_vat'      => (int) $booking['customer_id']['partner_identity_id']['has_vat'],
    'customer_vat'          => $booking['customer_id']['partner_identity_id']['vat_number'],

    'member'                => lodging_booking_print_booking_formatMember($booking),
    'date'                  => date('d/m/Y', $booking['modified']),
    'code'                  => sprintf("%03d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000),
    'center'                => $booking['center_id']['name'],
    'center_address'        => $booking['center_id']['address_street'].' - '.$booking['center_id']['address_zip'].' '.$booking['center_id']['address_city'],
    'postal_address'        => sprintf("%s - %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
    'center_contact1'       => (isset($booking['center_id']['manager_id']['name']))?$booking['center_id']['manager_id']['name']:'',
    'center_contact2'       => DataFormatter::format($booking['center_id']['phone'], 'phone').' - '.$booking['center_id']['email'],

    // by default, we use center contact details (overridden in case Center has a management Office, see below)
    'center_phone'          => DataFormatter::format($booking['center_id']['phone'], 'phone'),
    'center_email'          => $booking['center_id']['email'],
    'center_signature'      => $booking['center_id']['organisation_id']['signature'],

    'period'                => 'Du '.date('d/m/Y', $booking['date_from']).' au '.date('d/m/Y', $booking['date_to']),
    'price'                 => $booking['price'],
    'total'                 => $booking['total'],

    'company_name'          => $booking['center_id']['organisation_id']['legal_name'],
    'company_address'       => sprintf("%s %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
    'company_email'         => $booking['center_id']['organisation_id']['email'],
    'company_phone'         => DataFormatter::format($booking['center_id']['organisation_id']['phone'], 'phone'),
    'company_fax'           => DataFormatter::format($booking['center_id']['organisation_id']['fax'], 'phone'),
    'company_website'       => $booking['center_id']['organisation_id']['website'],
    'company_reg_number'    => $booking['center_id']['organisation_id']['registration_number'],
    'company_has_vat'       => $booking['center_id']['organisation_id']['has_vat'],
    'company_vat_number'    => $booking['center_id']['organisation_id']['vat_number'],


    // by default, we use organisation payment details (overridden in case Center has a management Office, see below)
    'company_iban'          => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_iban'], 'iban'),
    'company_bic'           => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_bic'], 'bic'),

    'lines'                 => [],
    'tax_lines'             => [],

    'benefit_lines'         => []
];


/*
    retrieve terms translations
*/
$values['i18n'] = [
    'invoice'           => Setting::get_value('sale', 'locale', 'terms.invoice', null, 0, $params['lang']),
    'quote'             => Setting::get_value('sale', 'locale', 'terms.quote', null, 0, $params['lang']),
    'option'            => Setting::get_value('sale', 'locale', 'terms.option', null, 0, $params['lang']),
    'contract'          => Setting::get_value('sale', 'locale', 'terms.contract', null, 0, $params['lang']),
    'booking_invoice'   => Setting::get_value('lodging', 'locale', 'i18n.booking_invoice', null, 0, $params['lang']),
    'booking_quote'     => Setting::get_value('lodging', 'locale', 'i18n.booking_quote', null, 0, $params['lang']),
    'booking_contract'  => Setting::get_value('lodging', 'locale', 'i18n.booking_contract', null, 0, $params['lang']),
    'credit_note'       => Setting::get_value('lodging', 'locale', 'i18n.credit_note', null, 0, $params['lang']),
    'company_registry'  => Setting::get_value('lodging', 'locale', 'i18n.company_registry', null, 0, $params['lang']),
    'vat_number'        => Setting::get_value('lodging', 'locale', 'i18n.vat_number', null, 0, $params['lang']),
    'vat'               => Setting::get_value('lodging', 'locale', 'i18n.vat', null, 0, $params['lang']),
    'your_stay_at'      => Setting::get_value('lodging', 'locale', 'i18n.your_stay_at', null, 0, $params['lang']),
    'contact'           => Setting::get_value('lodging', 'locale', 'i18n.contact', null, 0, $params['lang']),
    'period'            => Setting::get_value('lodging', 'locale', 'i18n.period', null, 0, $params['lang']),
    'member'            => Setting::get_value('lodging', 'locale', 'i18n.member', null, 0, $params['lang']),
    'phone'             => Setting::get_value('lodging', 'locale', 'i18n.phone', null, 0, $params['lang']),
    'email'             => Setting::get_value('lodging', 'locale', 'i18n.email', null, 0, $params['lang']),
    'booking_ref'       => Setting::get_value('lodging', 'locale', 'i18n.booking_ref', null, 0, $params['lang']),
    'your_reference'    => Setting::get_value('lodging', 'locale', 'i18n.your_reference', null, 0, $params['lang']),
    'number_short'      => Setting::get_value('lodging', 'locale', 'i18n.number_short', null, 0, $params['lang']),
    'date'              => Setting::get_value('lodging', 'locale', 'i18n.date', null, 0, $params['lang']),
    'status'            => Setting::get_value('lodging', 'locale', 'i18n.status', null, 0, $params['lang']),
    'paid'              => Setting::get_value('lodging', 'locale', 'i18n.paid', null, 0, $params['lang']),
    'to_pay'            => Setting::get_value('lodging', 'locale', 'i18n.to_pay', null, 0, $params['lang']),
    'to_refund'         => Setting::get_value('lodging', 'locale', 'i18n.to_refund', null, 0, $params['lang']),
    'product_label'     => Setting::get_value('lodging', 'locale', 'i18n.product_label', null, 0, $params['lang']),
    'quantity_short'    => Setting::get_value('lodging', 'locale', 'i18n.quantity_short', null, 0, $params['lang']),
    'freebies_short'    => Setting::get_value('lodging', 'locale', 'i18n.freebies_short', null, 0, $params['lang']),
    'unit_price'        => Setting::get_value('lodging', 'locale', 'i18n.unit_price', null, 0, $params['lang']),
    'discount_short'    => Setting::get_value('lodging', 'locale', 'i18n.discount_short', null, 0, $params['lang']),
    'taxes'             => Setting::get_value('lodging', 'locale', 'i18n.taxes', null, 0, $params['lang']),
    'price'             => Setting::get_value('lodging', 'locale', 'i18n.price', null, 0, $params['lang']),
    'total'             => Setting::get_value('lodging', 'locale', 'i18n.total', null, 0, $params['lang']),
    'price_tax_excl'    => Setting::get_value('lodging', 'locale', 'i18n.price_tax_excl', null, 0, $params['lang']),
    'total_tax_excl'    => Setting::get_value('lodging', 'locale', 'i18n.total_tax_excl', null, 0, $params['lang']),
    'total_tax_incl'    => Setting::get_value('lodging', 'locale', 'i18n.total_tax_incl', null, 0, $params['lang']),
    'stay_total_tax_incl'   => Setting::get_value('lodging', 'locale', 'i18n.stay_total_tax_incl', null, 0, $params['lang']),
    'balance_of'            => Setting::get_value('lodging', 'locale', 'i18n.balance_of', null, 0, $params['lang']),
    'to_be_paid_before'     => Setting::get_value('lodging', 'locale', 'i18n.to_be_paid_before', null, 0, $params['lang']),
    'communication'         => Setting::get_value('lodging', 'locale', 'i18n.communication', null, 0, $params['lang']),
    'amount_to_be refunded' => Setting::get_value('lodging', 'locale', 'i18n.amount_to_be refunded', null, 0, $params['lang']),
    'advantage_included'    => Setting::get_value('lodging', 'locale', 'i18n.advantage_included', null, 0, $params['lang']),
    'fare_category'         => Setting::get_value('lodging', 'locale', 'i18n.fare_category', null, 0, $params['lang']),
    'advantage'             => Setting::get_value('lodging', 'locale', 'i18n.advantage', null, 0, $params['lang']),
    'consumptions_details'  => Setting::get_value('lodging', 'locale', 'i18n.consumptions_details', null, 0, $params['lang']),
    'day'                   => Setting::get_value('lodging', 'locale', 'i18n.day', null, 0, $params['lang']),
    'meals_morning'         => Setting::get_value('lodging', 'locale', 'i18n.meals_morning', null, 0, $params['lang']),
    'meals_midday'          => Setting::get_value('lodging', 'locale', 'i18n.meals_midday', null, 0, $params['lang']),
    'meal_evening'          => Setting::get_value('lodging', 'locale', 'i18n.meal_evening', null, 0, $params['lang']),
    'nights'                => Setting::get_value('lodging', 'locale', 'i18n.nights', null, 0, $params['lang']),
    'payments_schedule'     => Setting::get_value('lodging', 'locale', 'i18n.payments_schedule', null, 0, $params['lang']),
    'payment'               => Setting::get_value('lodging', 'locale', 'i18n.payment', null, 0, $params['lang']),
    'already_paid'          => Setting::get_value('lodging', 'locale', 'i18n.already_paid', null, 0, $params['lang']),
    'amount'                => Setting::get_value('lodging', 'locale', 'i18n.amount', null, 0, $params['lang']),
    'yes'                   => Setting::get_value('lodging', 'locale', 'i18n.yes', null, 0, $params['lang']),
    'no'                    => Setting::get_value('lodging', 'locale', 'i18n.no', null, 0, $params['lang']),
    'the_amount_of'         => Setting::get_value('lodging', 'locale', 'i18n.the_amount_of', null, 0, $params['lang']),
    'must_be_paid_before'   => Setting::get_value('lodging', 'locale', 'i18n.must_be_paid_before', null, 0, $params['lang']),
    'date_and_signature'    => Setting::get_value('lodging', 'locale', 'i18n.date_and_signature', null, 0, $params['lang']),
];


/**
 * Add info for ATTN, if required.
 * If the invoice is emitted to a partner distinct from the booking customer, the latter is ATTN and the former is considered as the customer.
 */

if($booking['customer_id']['partner_identity_id']['id'] != $booking['customer_identity_id']['id']) {
    $values['attn_name'] = substr($booking['customer_identity_id']['display_name'], 0, 33);
    $values['attn_address1'] = $booking['customer_identity_id']['address_street'];
    $values['attn_address2'] = $booking['customer_identity_id']['address_zip'].' '.$booking['customer_identity_id']['address_city'].(($booking['customer_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_identity_id']['address_country']):'');
}

/*
    override contact and payment details with center's office, if set
*/
if($booking['center_id']['use_office_details']) {
    $office = $booking['center_id']['center_office_id'];
    $values['company_iban'] = DataFormatter::format($office['bank_account_iban'], 'iban');
    $values['company_bic'] = DataFormatter::format($office['bank_account_bic'], 'bic');
    $values['center_phone'] = DataFormatter::format($office['phone'], 'phone');
    $values['center_email'] = $office['email'];
    $values['center_signature'] = $office['signature'];
    $values['postal_address'] = $office['address_street'].' - '.$office['address_zip'].' '.$office['address_city'];
}


/*
    retrieve templates
*/
if($booking['center_id']['template_category_id']) {

    $template = Template::search([
                            ['category_id', '=', $booking['center_id']['template_category_id']],
                            ['code', '=', $booking['status']],
                            ['type', '=', $booking['status']]
                        ])
                        ->read(['parts_ids' => ['name', 'value']], $params['lang'])
                        ->first();

    foreach($template['parts_ids'] as $part_id => $part) {
        if($part['name'] == 'header') {
            $value = $part['value'].$values['center_signature'];
            $value = str_replace('{center}', $booking['center_id']['name'], $value);
            $value = str_replace('{date_from}', date('d/m/Y', $booking['date_from']), $value);
            $value = str_replace('{date_to}', date('d/m/Y', $booking['date_to']), $value);
            $values['quote_header_html'] = $value;
        }
        else if($part['name'] == 'notice') {
            $values['invoice_notice_html'] = $part['value'];
        }
    }

}

$template_part = TemplatePart::search(['name', '=', 'advantage_notice'])
                        ->read(['value'], $params['lang'])
                        ->first();

if($template_part) {
    $values['advantage_notice_html'] = $template_part['value'];
}

$template_part = TemplatePart::search(['name', '=', 'tbc_notice'])
                        ->read(['value'], $params['lang'])
                        ->first();

if($template_part) {
    $values['tbc_notice_html'] = $template_part['value'];
}

/*
    feed lines
*/
$lines = [];

// all lines are stored in groups
foreach($booking['booking_lines_groups_ids'] as $booking_line_group) {

    // generate group details
    $group_details = '';

    if($booking_line_group['date_from'] == $booking_line_group['date_to']) {
        $group_details .= date('d/m/y', $booking_line_group['date_from']);
    }
    else {
        $group_details .= date('d/m/y', $booking_line_group['date_from']).' - '.date('d/m/y', $booking_line_group['date_to']);
    }

    $group_details .= ' - '.$booking_line_group['nb_pers'].'p.';

    if($booking_line_group['has_pack'] && $booking_line_group['is_locked']) {
        // group is a product pack (bundle) with own price
        $group_is_pack = true;

        $line = [
            'name'          => $booking_line_group['name'],
            'details'       => $group_details,
            'description'   => $booking_line_group['pack_id']['label'],
            'price'         => $booking_line_group['price'],
            'total'         => $booking_line_group['total'],
            'unit_price'    => $booking_line_group['unit_price'],
            'vat_rate'      => $booking_line_group['vat_rate'],
            'qty'           => $booking_line_group['qty'],
            'free_qty'      => $booking_line_group['free_qty'],
            'discount'      => $booking_line_group['discount'],
            'is_group'      => true,
            'is_pack'       => true
        ];
        $lines[] = $line;

        if($params['mode'] == 'detailed') {
            foreach($booking_line_group['booking_lines_ids'] as $booking_line) {
                $line = [
                    'name'          => $booking_line['name'],
                    'qty'           => $booking_line['qty'],
                    'price'         => null,
                    'total'         => null,
                    'unit_price'    => null,
                    'vat_rate'      => null,
                    'discount'      => null,
                    'free_qty'      => null,
                    'is_group'      => false,
                    'is_pack'       => false
                ];
                $lines[] = $line;
            }
        }
    }
    else {

        // group is a pack with no own price
        $group_is_pack = false;

        if($params['mode'] == 'grouped') {
            $line = [
                'name'          => $booking_line_group['name'],
                'details'       => $group_details,
                'price'         => $booking_line_group['price'],
                'total'         => $booking_line_group['total'],
                'unit_price'    => $booking_line_group['total'],
                'vat_rate'      => (floatval($booking_line_group['price'])/floatval($booking_line_group['total']) - 1.0),
                'qty'           => 1,
                'free_qty'      => 0,
                'discount'      => 0,
                'is_group'      => true,
                'is_pack'       => false
            ];
        }
        else {
            $line = [
                'name'          => $booking_line_group['name'],
                'details'       => $group_details,
                'price'         => null,
                'total'         => null,
                'unit_price'    => null,
                'vat_rate'      => null,
                'qty'           => null,
                'free_qty'      => null,
                'discount'      => null,
                'is_group'      => true,
                'is_pack'       => false
            ];
        }
        $lines[] = $line;


        $group_lines = [];

        foreach($booking_line_group['booking_lines_ids'] as $booking_line) {

            if($params['mode'] == 'grouped') {
                $line = [
                    'name'          => (strlen($booking_line['description']) > 0)?$booking_line['description']:$booking_line['name'],
                    'price'         => null,
                    'total'         => null,
                    'unit_price'    => null,
                    'vat_rate'      => null,
                    'qty'           => $booking_line['qty'],
                    'discount'      => null,
                    'free_qty'      => null,
                    'is_group'      => false,
                    'is_pack'       => false
                ];
            }
            else {
                $line = [
                    'name'          => (strlen($booking_line['description']) > 0)?$booking_line['description']:$booking_line['name'],
                    'price'         => $booking_line['price'],
                    'total'         => $booking_line['total'],
                    'unit_price'    => $booking_line['unit_price'],
                    'vat_rate'      => $booking_line['vat_rate'],
                    'qty'           => $booking_line['qty'],
                    'discount'      => $booking_line['discount'],
                    'free_qty'      => $booking_line['free_qty'],
                    'is_group'      => false,
                    'is_pack'       => false
                ];
            }

            $group_lines[] = $line;
        }
        if($params['mode'] == 'detailed' || $params['mode'] == 'grouped') {
            foreach($group_lines as $line) {
                $lines[] = $line;
            }
        }
        // mode is 'simple' : group lines by VAT rate
        else {
            $group_tax_lines = [];
            foreach($group_lines as $line) {
                $vat_rate = strval($line['vat_rate']);
                if(!isset($group_tax_lines[$vat_rate])) {
                    $group_tax_lines[$vat_rate] = 0;
                }
                $group_tax_lines[$vat_rate] += $line['total'];
            }

            if(count(array_keys($group_tax_lines)) <= 1) {
                $pos = count($lines)-1;
                foreach($group_tax_lines as $vat_rate => $total) {
                    $lines[$pos]['qty'] = 1;
                    $lines[$pos]['vat_rate'] = $vat_rate;
                    $lines[$pos]['total'] = $total;
                    $lines[$pos]['price'] = $total * (1 + $vat_rate);
                }
            }
            else {
                foreach($group_tax_lines as $vat_rate => $total) {
                    $line = [
                        'name'      => 'Services avec TVA '.($vat_rate*100).'%',
                        'qty'       => 1,
                        'vat_rate'  => $vat_rate,
                        'total'     => $total,
                        'price'     => $total * (1 + $vat_rate)
                    ];
                    $lines[] = $line;
                }
            }
        }
    }
}

$values['lines'] = $lines;


/*
    compute fare benefit detail
*/
$values['benefit_lines'] = [];

foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['fare_benefit'] == 0) {
        continue;
    }
    $index = $group['rate_class_id']['description'];
    if(!isset($values['benefit_lines'][$index])) {
        $values['benefit_lines'][$index] = [
            'name'  => $index,
            'value' => $group['fare_benefit']
        ];
    }
    else {
        $values['benefit_lines'][$index]['value'] += $group['fare_benefit'];
    }
}



/*
    retrieve final VAT and group by rate
*/
foreach($lines as $line) {
    $vat_rate = $line['vat_rate'];
    $tax_label = 'TVA '.strval( intval($vat_rate * 100) ).'%';
    $vat = $line['price'] - $line['total'];
    if(!isset($values['tax_lines'][$tax_label])) {
        $values['tax_lines'][$tax_label] = 0;
    }
    $values['tax_lines'][$tax_label] += $vat;
}


// retrieve contact details
foreach($booking['contacts_ids'] as $contact) {
    if(strlen($values['contact_name']) == 0 || $contact['type'] == 'booking') {
        // overwrite data of customer with contact info
        $values['contact_name'] = str_replace(["Dr", "Ms", "Mrs", "Mr","Pr"], ["Dr","Melle", "Mme","Mr","Pr"], $contact['partner_identity_id']['title']).' '.$contact['partner_identity_id']['name'];
        $values['contact_phone'] = (strlen($contact['partner_identity_id']['phone']))?$contact['partner_identity_id']['phone']:$contact['partner_identity_id']['mobile'];
        $values['contact_email'] = $contact['partner_identity_id']['email'];
    }
}


/*
    Generate consumptions map
*/

$consumptions_map = [];

$consumptions = Consumption::search([ ['booking_id', '=', $booking['id']], ['type', '=', 'book'] ])
    ->read([
        'id',
        'date',
        'qty',
        'is_meal',
        'rental_unit_id',
        'is_accomodation',
        'time_slot_id',
        'schedule_to'
    ])
    ->get();

$days_names = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

// sort consumptions on dates
usort($consumptions, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});

foreach($consumptions as $cid => $consumption) {
    // #memo - we only count overnight accommodations (a sojourn always has nb_nights+1 days)
    // ignore accommodation consumptions that do not end at midnight (24:00:00)
    if($consumption['is_accomodation'] && $consumption['schedule_to'] != 86400) {
        continue;
    }

    $date = date('d/m/Y', $consumption['date']).' ('.$days_names[date('w', $consumption['date'])].')';
    if(!isset($consumptions_map[$date])) {
        $consumptions_map[$date] = [];
    }
    if(!isset($consumptions_map['total'])) {
        $consumptions_map['total'] = [];
    }

    if($consumption['is_meal']) {
        if(!isset($consumptions_map[$date][$consumption['time_slot_id']])) {
            $consumptions_map[$date][$consumption['time_slot_id']] = 0;
        }
        if(!isset($consumptions_map['total'][$consumption['time_slot_id']])) {
            $consumptions_map['total'][$consumption['time_slot_id']] = 0;
        }
        $consumptions_map[$date][$consumption['time_slot_id']] += $consumption['qty'];
        $consumptions_map['total'][$consumption['time_slot_id']] += $consumption['qty'];
    }
    else if($consumption['is_accomodation']) {
        if(!isset($consumptions_map[$date]['night'])) {
            $consumptions_map[$date]['night'] = 0;
        }
        if(!isset($consumptions_map['total']['night'])) {
            $consumptions_map['total']['night'] = 0;
        }
        $consumptions_map[$date]['night'] += $consumption['qty'];
        $consumptions_map['total']['night'] += $consumption['qty'];
    }
}

$values['consumptions_map'] = $consumptions_map;


/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(QN_BASEDIR."/packages/{$package}/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);
    // #todo - temp workaround against LOCALE mixups
    $filter = new \Twig\TwigFilter('format_money', function ($value) {
        return number_format((float)($value),2,",",".").' €';
    });
    $twig->addFilter($filter);

    $template = $twig->load("{$class_path}.{$params['view_id']}.html");

    // #todo - use localization prefs for rendering (independent from locale)
    // setlocale(LC_ALL, constant('L10N_LOCALE'));
    // render template
    $html = $template->render($values);
    // restore original locale
    // setlocale(LC_ALL, 0);
}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), QN_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", QN_ERROR_INVALID_CONFIG);
}

if($params['output'] == 'html') {
    $context->httpResponse()
        ->header('Content-Type', 'text/html')
        ->body($html)
        ->send();
    exit(0);
}

/*
    Convert HTML to PDF
*/

// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml((string) $html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
$canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
// $canvas->page_text(40, $canvas->get_height() - 35, "Export", $font, 9, array(0,0,0));


// get generated PDF raw binary
$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();



function lodging_booking_print_booking_formatMember($booking) {
    $id = $booking['customer_id']['partner_identity_id']['id'];
    $code = ltrim(sprintf("%3d.%03d.%03d", intval($id) / 1000000, (intval($id) / 1000) % 1000, intval($id)% 1000), '0');
    return $code.' - '.$booking['customer_id']['partner_identity_id']['display_name'];
}