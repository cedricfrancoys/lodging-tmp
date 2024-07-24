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

use lodging\identity\Center;
use lodging\identity\User;
use lodging\sale\pos\CashdeskSession;
use lodging\sale\pos\Cashdesk;
use lodging\sale\pos\Order;
use sale\pos\Operation;
use lodging\sale\catalog\Product;
use finance\tax\VatRule;

/*
    We use this controller as a "print" controller, for printing the result of a "Checkin" Consumption search.
    We ignore domain, ids and controller and entity : we deal with Booking entity and fetch relevant booking without additional "data" controller.
*/
list($params, $providers) = announce([
    'description'   => "Generates a list of arrival data as a PDF document, given a center and a date range.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the cashdeskSession the check against emptiness.',
            'type'          => 'integer',
            'required'      => true
        ],
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.day'
        ],
        'lang' =>  [
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['pos.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

$entity = 'lodging\sale\pos\CashdeskSession';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = QN_BASEDIR."/packages/{$package}/views/{$class_path}.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}

$cashdeskSession = CashdeskSession::id($params['id'])
        ->read([
            'id',
            'name',
            'status',
            'created',
            'user_id' => [
                'id',
                'name',
            ],
            'modified',
            'center_id'=> [
                'id',
                'name',
            ],
            'cashdesk_id'=> [
                'id',
                'name',
            ],
            'amount_closing',
            'amount_opening',
            'orders_ids',
            'operations_ids' => [
                'id',
                'created',
                'type',
                'name',
                'amount'
            ],
        ])
        ->first(true);

if(!$cashdeskSession) {
    throw new Exception("unknown_cashdeskSession", QN_ERROR_UNKNOWN_OBJECT);
}

// #todo - fetch from settings
$tz = new \DateTimeZone("Europe/Brussels");

$cashdeskSession['created'] += $tz->getOffset(new \DateTime('@'.$cashdeskSession['created']));
$cashdeskSession['modified'] += $tz->getOffset(new \DateTime('@'.$cashdeskSession['modified']));

$time = time();
$tz_offset = $tz->getOffset(new \DateTime('@'.$time));
$time += $tz_offset;

$orders_ids = Order::search(['session_id', '=' , $cashdeskSession['id']])->ids();

if(!count($orders_ids)) {
    throw new Exception("no_match", QN_ERROR_UNKNOWN_OBJECT);
}


$values = [
    'date'                     => $time,
    'cashdesksession'          => $cashdeskSession,
    'total_paid'               => 0.0,
    'total_received'           => 0.0,
    'total_cash'               => 0.0,
    'total_bank_card'          => 0.0,
    'total_booking'            => 0.0,
    'total_voucher'            => 0.0,
    'total_expected'           => 0.0,
    'total_remaining'          => 0.0,
    'total_operations'         => 0.0,
    'total_operations_in'      => 0.0,
    'total_operations_out'     => 0.0,
    'order_payment_parts'      => [],
    'total_vat_rules'          => [],
];

$total_paid = 0.0;
$total_cash = 0.0;
$total_bank_card = 0.0;
$total_voucher = 0.0;
$total_operations_in = 0.0;
$total_operations_out = 0.0;
$total_operations = 0.0;

$vat_rules = VatRule::search()->read(['id','name','rate'])->get();

$result_vat=[];

foreach ($vat_rules as $vat_rule) {
    $result_vat[] = [
        'name'  => $vat_rule['name'],
        'rate'  => $vat_rule['rate'],
        'total' => 0
    ];
}

$total_returned = 0.0;

foreach($orders_ids as $order_id) {
    $order = Order::id($order_id)
        ->read([
            'id',
            'name',
            'status',
            'booking_id',
            'created',
            'total',
            'price',
            'total_paid',
            'funding_id'=> ['id','name'],
            'customer_id'=> ['id','name'],
            'order_lines_ids' => ['id', 'product_id' => ['id','name'], 'vat_rate', 'price'],
            'order_payments_ids' => [
                'id',
                'total_change',
                'order_payment_parts_ids' => ['id','status','payment_method','amount']
            ]
        ])
        ->first(true);

    if(!$order) {
        continue;
    }

    if($order['booking_id'] > 0) {
        continue;
    }

    $order['created'] += $tz->getOffset(new \DateTime('@'.$order['created']));

    foreach($order['order_lines_ids'] as $order_line) {
        foreach ($result_vat as &$vat_rule) {
            if ($order_line['vat_rate'] == $vat_rule['rate']) {
                $vat_rule['total'] += $order_line['price'];
            }
        }
    }

    $order_totals = [
            'cash'      => 0,
            'bank_card' => 0,
            'voucher'   => 0
        ];

    foreach($order['order_payments_ids'] as $order_payment) {

        foreach($order_payment['order_payment_parts_ids'] as $order_payment_part) {

            $order_totals[$order_payment_part['payment_method']] += $order_payment_part['amount'];
            $total_paid += $order_payment_part['amount'];

            $item = [
                'order'             => $order['name'],
                'type'              => 'C',
                'created'           => $order['created'],
                'mode'              => ($order_payment_part['payment_method'] == 'cash') ? 'espèces' : 'carte bancaire',
                'funding'           => $order['funding_id']['name'],
                'customer'          => $order['customer_id']['name'],
                'status'            => $order_payment_part['status'],
                'amount'            => $order_payment_part['amount']
            ];

            $values['cashdesk_log_entries'][] = $item;

        }
    }

    $order_returned = round($order['total_paid'] - $order['price'], 2);
    $total_returned += $order_returned;

    // #memo - this seems incorrect but I don't know why it was computed this way
    // $total_cash += $order['price'] - $order_totals['bank_card'] - $order_totals['voucher'];
    $total_cash += $order_totals['cash'];
    $total_bank_card += $order_totals['bank_card'];
    $total_voucher += $order_totals['voucher'];

    if($order_returned > 0) {
            $item = [
                'order'             => $order['name'],
                'type'              => 'C',
                'created'           => $order['created'],
                'mode'              => 'espèces',
                'amount'            => -$order_returned
            ];

        $values['cashdesk_log_entries'][] = $item;
    }

}

foreach($cashdeskSession['operations_ids'] as $operation) {

    $operation['created'] += $tz->getOffset(new \DateTime('@'.$operation['created']));

    if($operation['amount'] > 0) {
        $total_operations_in += $operation['amount'];
    }
    else {
        $total_operations_out += $operation['amount'];
    }
    $total_operations += $operation['amount'];

    if($operation['type'] == 'move') {
        $item = [
                'order'             => $order['name'],
                'type'              => 'M',
                'created'           => $order['created'],
                'mode'              => 'espèces',
                'amount'            => $operation['amount']
            ];

        $values['cashdesk_log_entries'][] = $item;
    }

}


$values['total_vat_rules'] = $result_vat;
$values['total_operations_in'] = round($total_operations_in, 2);
$values['total_operations_out'] = round($total_operations_out, 2);
$values['total_operations'] = round($total_operations, 2);

$values['total_cash'] = round($total_cash, 2);
$values['total_received'] = round($total_cash,2);
$values['total_bank_card'] = round($total_bank_card, 2);
$values['total_booking'] = round($total_booking, 2);
$values['total_voucher'] = round($total_voucher, 2);
$values['total_expected'] = round($cashdeskSession['amount_opening'] + $total_cash - $total_returned, 2);
$values['total_remaining'] = round($cashdeskSession['amount_closing'] - $values['total_expected'] , 2);

usort($values['cashdesk_log_entries'], function ($a, $b) {
    return strcmp($a['created'], $b['created']);
});


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
    $html = $template->render($values);

}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), QN_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", QN_ERROR_INVALID_CONFIG);
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
