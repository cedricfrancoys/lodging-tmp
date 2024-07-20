<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;

list($params, $providers) = announce([
    'description'   => 'Linearize historic bookings data from Hestia to a csv file.',
    'help'          => "Csv files should be put in \"packages/lodging/init/history/\" folder.\n".
                       'Needed files for a center are "M_Resa.csv", "R_Client.csv", "M_Reglement.csv", "M_Resa_Compo.csv", "M_Resa_Regl.csv"',
    'params'        => [
        'center' => [
            'type'      => 'string',
            'selection' => ['Eupe', 'GiGr', 'HanL', 'Louv', 'Ovif', 'Roch', 'Vill', 'Wann'],
            'required'  => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected',
        'groups'     => ['admins']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$getCsvFileContent = function($file_path, $id_column, $columns, $multiple_items_with_same_id) {
    $data = [];

    $file = fopen($file_path, 'r');
    $headings = fgetcsv($file, null, ';');
    $headings[0] = trim($headings[0], "\xEF\xBB\xBF");
    $headings[0] = str_replace('"', '', $headings[0]);
    while($row = fgetcsv($file, null, ';')) {
        $item = [];
        foreach($headings as $index => $heading) {
            if(in_array($heading, $columns)) {
                $item[$heading] = $row[$index];
            }
        }

        if(!$multiple_items_with_same_id) {
            $data[$item[$id_column]] = $item;
        }
        else {
            if(!isset($data[$item[$id_column]])) {
                $data[$item[$id_column]] = [];
            }

            $data[$item[$id_column]][] = $item;
        }
    }

    fclose($file);

    return $data;
};

$getHistoricCsvFilesData = function($csv_files, $center, $multiple_items_with_same_id = false) use ($getCsvFileContent) {
    $historic_data = [];
    foreach($csv_files as $file => $file_config) {
        $file_path = QN_BASEDIR . '/packages/lodging/init/history/';
        if($file_config['center_specific']) {
            $file_path .= $center . '_';
        }
        $file_path .= $file . '.csv';

        $historic_data[$file] = $getCsvFileContent(
            $file_path,
            $file_config['id'],
            $file_config['columns'],
            $multiple_items_with_same_id
        );
    }

    return $historic_data;
};

$getHistoricData = function($center) use($getHistoricCsvFilesData) {
    $resa_fields = ['Code_Resa', 'Cle_Client', 'Lib_Resa', 'Comment', 'Date_Creation', 'Date_Debut', 'Date_Fin', 'MtHt', 'Tot_Paye', 'Num_Facture', 'Code_Centre', 'Nbre_Pers'];
    $client_fields = ['Cle_Client', 'Nom_Client', 'Adr3_Client', 'CP_Client', 'Ville_Client', 'Pays_Client', 'Langue_Client', 'Nature_Client'];
    $regl_fields = ['Reglt', 'Cle_Client', 'DateReg', 'Montant'];

    return array_merge(
        $getHistoricCsvFilesData(
            [
                'M_Resa'      => ['id' => 'Code_Resa', 'columns' => $resa_fields, 'center_specific' => true],
                'R_Client'    => ['id' => 'Cle_Client', 'columns' => $client_fields, 'center_specific' => false],
                'M_Reglement' => ['id' => 'Reglt', 'columns' => $regl_fields, 'center_specific' => true]
            ],
            $center
        ),
        $getHistoricCsvFilesData(
            [
                'M_Resa_Compo' => ['id' => 'Code_Resa', 'columns' => ['Code_Resa', 'Chambre'], 'center_specific' => true],
                'M_Resa_Regl'  => ['id' => 'Code_Resa', 'columns' => ['Reglt', 'Code_Resa'], 'center_specific' => true]
            ],
            $center,
            true
        )
    );
};

$getCentersMap = function() {
    $centers = Center::search()
        ->read(['name', 'code_alpha', 'center_office_id'])
        ->get();

    $centers_map = [];
    foreach($centers as $center) {
        $centers_map[$center['code_alpha']] = $center;
    }

    return $centers_map;
};

$getRateClassesMap = function() {
    // returns Hestia customer nature to sale\customer\RateClass
    return [
        'AD' => '3',
        'AC' => '4',
        'MO' => '1',
        'AA' => '3',
        'AT' => '4',
        'AN' => '3',
        'ED' => '1',
        'AS' => '4',
        'AR' => '3',
        'AL' => '4',
        'CE' => '1',
        'CH' => '2',
        'UC' => '4',
        'CS' => '2',
        'CP' => '1',
        'CC' => '1',
        'EC' => '4',
        'EM' => '7',
        'EP' => '4',
        'ES' => '5',
        'SP' => '5',
        'EG' => '4',
        'EN' => '4',
        'FA' => '3',
        'FM' => '3',
        'GA' => '1',
        'GG' => '4',
        'AM' => '4',
        'HE' => '4',
        'HA' => '4',
        'HO' => '1',
        'IN' => '4',
        'IB' => '4',
        'IR' => '4',
        'IP' => '1',
        'JE' => '1',
        'MJ' => '1',
        'M3' => '4',
        'SC' => '1',
        'MU' => '4',
        'OF' => '1',
        'OJ' => '4',
        'PR' => '1',
        'SO' => '4',
        'sj' => '5',
        'SI' => '3',
        'TO' => '6',
        'TC' => '4',
        'US' => '2'
    ];
};

$getNbRentalUnit = function($compositions) {
    $rooms = [];
    $nb_rental_units = 0;
    foreach($compositions as $composition) {
        if(isset($rooms[$composition['Chambre']])) {
            continue;
        }

        $nb_rental_units++;
        $rooms[$composition['Chambre']] = true;
    }

    return $nb_rental_units;
};

$getLinearizedFile = function($center) {
    $linearized_file_path = QN_BASEDIR . '/packages/lodging/init/history/'.$center.'_linearized.csv';
    unlink($linearized_file_path);
    return fopen($linearized_file_path, 'w');
};

$addHeaderToFile = function(&$linearized_file) {
    fputs($linearized_file, (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    $header_columns = [
        'id', 'created', 'name', 'comment', 'date_from', 'date_to', 'price_ex_vat', 'price_inc_vat', 'invoice_id',
        'center_alpha_code', 'center_name', 'center_type',
        'nb_pers', 'nb_nights', 'nb_rental_units', 'nb_pers_nights', 'nb_room_nights',
        'customer_id', 'customer_name', 'customer_language_code', 'customer_street', 'customer_zip',
        'customer_city', 'customer_country_code', 'customer_nature', 'customer_rate_class'
    ];
    for($i = 0; $i <= 10; $i++) {
        $header_columns[] = 'payment_' . $i . '_amount';
        $header_columns[] = 'payment_' . $i . '_date';
        $header_columns[] = 'payment_' . $i . '_customer_name';
    }

    fputcsv($linearized_file, $header_columns, ';');
};


$historic_data = $getHistoricData($params['center']);

$centers_map = $getCentersMap();

$rate_classes_map = $getRateClassesMap();

$linearized_file = $getLinearizedFile($params['center']);
$addHeaderToFile($linearized_file);

foreach($historic_data['M_Resa'] as $resa) {
    $client = $historic_data['R_Client'][$resa['Cle_Client']];

    $nb_pers = (int) $resa['Nbre_Pers'];
    $nb_nights = round((strtotime($resa['Date_Fin']) - strtotime($resa['Date_Debut'])) / (3600 * 24));
    $nb_rental_units = $getNbRentalUnit($historic_data['M_Resa_Compo'][$resa['Code_Resa']]);

    $booking = [
        'id'                     => $resa['Code_Resa'],
        'created'                => $resa['Date_Creation'],
        'name'                   => $resa['Lib_Resa'],
        'comment'                => str_replace(['\"'], "''", $resa['Comment']),
        'date_from'              => $resa['Date_Debut'],
        'date_to'                => $resa['Date_Fin'],
        'price_ex_vat'           => number_format(str_replace('.', '', $resa['MtHt']) / 10000, 4, ',', '.'),
        'price_inc_vat'          => number_format(str_replace('.', '', $resa['Tot_Paye']) / 10000, 4, ',', '.'),
        'invoice_id'             => $resa['Num_Facture'],
        'center_alpha_code'      => $resa['Code_Centre'],
        'center_name'            => $centers_map[$resa['Code_Centre']]['name'],
        'center_type'            => $centers_map[$resa['Code_Centre']]['center_office_id'] === 1 ? 'GG' : 'GA',
        'nb_pers'                => $nb_pers,
        'nb_nights'              => $nb_nights,
        'nb_rental_units'        => $nb_rental_units,
        'nb_pers_nights'         => $nb_pers * $nb_nights,
        'nb_room_nights'         => $nb_rental_units * $nb_nights,
        'customer_id'            => $client['Cle_Client'],
        'customer_name'          => $client['Nom_Client'],
        'customer_language_code' => $client['Langue_Client'],
        'customer_street'        => $client['Adr3_Client'],
        'customer_zip'           => $client['CP_Client'],
        'customer_city'          => $client['Ville_Client'],
        'customer_country_code'  => $client['Pays_Client'],
        'customer_nature'        => $client['Nature_Client'],
        'customer_rate_class'    => $rate_classes_map[$client['Nature_Client']]
    ];

    $regl_ids = [];
    foreach($historic_data['M_Resa_Regl'][$resa['Code_Resa']] as $resa_regl) {
        $regl_ids[] = $resa_regl['Reglt'];
    }

    foreach($regl_ids as $index => $regl_id) {
        $regl = $historic_data['M_Reglement'][$regl_id];
        $regl_client = $historic_data['R_Client'][$regl['Cle_Client']];

        $booking['payment_'.($index + 1).'_amount'] = number_format(str_replace('.', '', $regl['Montant']) / 10000, 4, ',', '.');
        $booking['payment_'.($index + 1).'_date'] = $regl['DateReg'];
        $booking['payment_'.($index + 1).'_customer_name'] = $regl_client['Nom_Client'];
    }

    fputcsv($linearized_file, $booking, ';');
}

fclose($linearized_file);

$context->httpResponse()
        ->body([
            'success'          => true,
            'handled_bookings' => count($historic_data['M_Resa'])
        ])
        ->send();
