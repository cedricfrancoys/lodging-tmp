<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\catalog\PosProduct;
use sale\catalog\Category;
use lodging\identity\Center;
use lodging\sale\catalog\PosPrice;
use lodging\sale\catalog\PosPriceList;


list($params, $providers) = announce([
    'description'   => 'Retrieves all products that are currently sellable at POS for a given center.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'              => 'string',
            'default'           => 'lodging\sale\catalog\Product'
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'lodging\identity\Center',
            'description'       => "The center to which the booking relates to.",
            'required'          => true
        ],
        'filter' => [
            'type'              => 'string',
            'default'           => ''
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
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

$result = [];

/*
    Retrieve all models belonging to the POS category
*/
$category = Category::search([['code', '=', 'POS']])->read(['id', 'code', 'product_models_ids'])->first(true);

/*
    Fetch all products belonging to the targeted models
*/
$candidate_products_ids = PosProduct::search([
        ['name', 'ilike', '%'.$params['filter'].'%'],
        ['can_sell', '=', true],
        ['product_model_id', 'in', $category['product_models_ids'] ]
    ])
    ->ids();

/*
    Keep only products for which a price can be retrieved
*/
// retrieve pricelist category from center
$center = Center::id($params['center_id'])->read(['price_list_category_id'])->first(true);

// find all Price Lists that matches the criteria from the order with (shortest duration first)
$price_lists_ids = PosPriceList::search([
        ['price_list_category_id', '=', $center['price_list_category_id']],
        ['date_from', '<=', time()],
        ['date_to', '>=', time()],
        ['status', '=', 'published'],
        ['is_active', '=', true]
    ],
    ['sort' => ['date_from' => 'desc', 'duration' => 'asc']])
    ->ids();

$map_products = [];
// fetch all applicable products present in any of the retrieved price lists
if(count($price_lists_ids)) {
    $products_ids = [];
    foreach($price_lists_ids as $price_list_id) {
        // get all prices for first found price list
        $prices = PosPrice::search([
                ['price_list_id', '=', $price_list_id],
                ['product_id', 'in', $candidate_products_ids]
            ])
            ->read(['product_id', 'price', 'vat_rate'])
            ->get();

        // map prices on products
        foreach($prices as $price) {
            // pricelists are order by ascending length, and only first match is considered
            if(!isset($map_products[$price['product_id']])) {
                $map_products[$price['product_id']] = $price;
            }
        }
    }

    $result = PosProduct::ids(array_keys($map_products))
        ->read(['id', 'sku', 'label', 'product_model_id' => ['name']])
        ->get(true);

    foreach($result as $i => $product) {
        $result[$i]['price'] = $map_products[$product['id']];
    }

}

$context->httpResponse()
        ->body($result)
        ->send();
