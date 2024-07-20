<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => 'Provides the quantities and prices of all invoiced products for a given period.',
    'params'        => [
        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => 'Center filter',
            'domain'            => ['id', 'in', [1, 2, 3, 4]]
        ],
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\CenterOffice',
            'description'       => 'Center filter'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => 'From date filter',
            'default'           => mktime(0, 0, 0, date('m')-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'To date filter',
            'default'           => mktime(0, 0, 0, date('m'), 0)
        ],
        'product_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'description'       => 'Product filter'
        ],

        /* parameters used as properties of virtual entity */
        'name' => [
            'type'              => 'string',
            'description'       => 'Name of invoice line.'
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => 'Quantity of product invoiced.'
        ],
        'total' => [
            'type'              => 'float',
            'description'       => 'Total price invoiced.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

$result = [];
if(isset($params['center_office_id']) || isset($params['organisation_id'])) {
    $date_to = date('Y-m-d', $params['date_to']);

    $invoices = Invoice::search(array_merge(
        [
            ['state', 'in', ['instance', 'archive']],
            ['date', '>=', $params['date_from']],
            ['date', '<=', strtotime($date_to.' 23:59:59')],
            ['status', '=', 'invoice']
        ],
        isset($params['center_office_id']) ? [['center_office_id', '=', $params['center_office_id']]] : [],
        isset($params['organisation_id']) ? [['organisation_id', '=', $params['organisation_id']]] : []
    ))
        ->read([
            'center_office_id' => ['id', 'name'],
            'organisation_id' => ['id', 'name'],
            'invoice_lines_ids' => [
                'id', 'product_id', 'qty', 'unit_price', 'total', 'name',
            ]
        ]);

    $organisation_map = [];
    foreach($invoices as $invoice) {
        list($org_id, $cen_id) = [$invoice['organisation_id']['id'], $invoice['center_office_id']['id']];

        if(!isset($organisation_map[$org_id])) {
            $organisation_map[$org_id] = [];
        }

        if(!isset($organisation_map[$org_id][$cen_id])) {
            $organisation_map[$org_id][$cen_id] = [];
        }

        foreach($invoice['invoice_lines_ids'] as $invoice_line) {
            $pro_id = $invoice_line['product_id'];
            if(isset($params['product_id']) && $pro_id !== $params['product_id']) {
                continue;
            }

            if(!isset($organisation_map[$org_id][$cen_id][$pro_id])) {
                $organisation_map[$org_id][$cen_id][$pro_id] = [
                    'organisation_id'  => $invoice['organisation_id'],
                    'center_office_id' => $invoice['center_office_id'],
                    'name'             => $invoice_line['name'],
                    'qty'              => 0,
                    'total'            => 0
                ];
            }

            $organisation_map[$org_id][$cen_id][$pro_id]['qty'] += $invoice_line['qty'];
            $organisation_map[$org_id][$cen_id][$pro_id]['total'] += $invoice_line['total'];
        }
    }

    foreach($organisation_map as $org_id => $org_centers) {
        foreach($org_centers as $cen_id => $center_prod_stats) {
            foreach($center_prod_stats as $pro_id => $product_stat) {
                $result[] = [
                    'organisation_id'  => $product_stat['organisation_id'],
                    'center_office_id' => $product_stat['center_office_id'],
                    'name'             => $product_stat['name'],
                    'qty'              => $product_stat['qty'],
                    'total'            => $product_stat['total']
                ];

            }
        }
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
