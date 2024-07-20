<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\identity\Identity;
use lodging\identity\User;
use lodging\sale\catalog\Product;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

list($params, $providers) = eQual::announce([
    'description'   => 'Provides data for mandatory INS statistics declaration (Statbel).',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required.",
            'visible'           => ['all_centers', '=', false]
        ],
        'all_centers' => [
            'type'              => 'boolean',
            'default'           =>  false,
            'description'       => "Mark the all Center of the sojourn."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],

        /* parameters used as properties of virtual entity */
        'params' => [
            'type'              => 'array',
            'description'       => 'Name of the center.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'adapt', 'auth' ]
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\data\DataAdapter             $adapter
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $orm, $adapter, $auth) = [ $providers['context'], $providers['orm'], $providers['adapt'], $providers['auth'] ];


$user = User::id($auth->userId())->read(['id', 'login'])->first(true);

// get data from controller
$values = eQual::run('get', 'lodging_stats_stat-ins', $params['params']);

$doc = new Spreadsheet();
$doc->getProperties()
      ->setCreator($user['login'])
      ->setTitle('Export')
      ->setDescription('Exported with eQual library');

$doc->setActiveSheetIndex(0);


$sheet = $doc->getActiveSheet();
$sheet->setTitle("export");



$column = 'A';
$row = 1;


foreach($values as $index => $line) {
    $column = 'A';

    $value = $line['customer_country'];
    $sheet->setCellValue($column.$row, $value);
    ++$column;

    $value = $line['purpose_of_stay'];
    $sheet->setCellValue($column.$row, $value);
    ++$column;

    $value = date('Y-m-d', strtotime($line['date_to']));
    $sheet->setCellValue($column.$row, $value);
    ++$column;

    $value = $line['nb_nights'];
    $sheet->setCellValue($column.$row, $value);
    ++$column;

    $value = $line['nb_pers'];
    $sheet->setCellValue($column.$row, $value);
    ++$column;

    $value = $line['nb_rental_units'];
    $sheet->setCellValue($column.$row, $value);
    ++$column;

    ++$row;
}

/** @var \PhpOffice\PhpSpreadsheet\Writer\Csv */
$writer = IOFactory::createWriter($doc, "Csv");

$writer->setSheetIndex(0)
    ->setEnclosure('')
    ->setDelimiter(';')
    ->setLineEnding("\r\n")
    ->setUseBOM(false);

ob_start();
$writer->save('php://output');
$output = ob_get_clean();

$context->httpResponse()
        // #memo - charset must be specified to disable the default UTF8 (which adds a BOM header)
        ->header('Content-Type', 'text/csv;charset=us-ascii')
        ->header('Content-Disposition', 'inline; filename="export.csv"')
        ->body($output)
        ->send();
