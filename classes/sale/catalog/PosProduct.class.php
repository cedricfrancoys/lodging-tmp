<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\catalog;


class PosProduct extends Product {

    public static function getDescription() {
        return "Entity specific to PoS to allow handling of additional rights so that the center office managers can update the PoS catalog.";
    }

    public static function getColumns() {
        return [
            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\PosProductModel',
                'description'       => "Product Model of this variant.",
                'required'          => true,
                'onupdate'          => 'sale\catalog\Product::onupdateProductModelId'
            ],

            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\catalog\PosPrice',
                'foreign_field'     => 'product_id',
                'description'       => "Prices that are related to this product.",
                'ondetach'          => 'delete'
            ]

        ];
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  array                      $oids       List of objects identifiers.
     * @param  array                      $values     Associative array holding the new values to be assigned.
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return array                      Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {
        /** @var \equal\auth\AuthenticationManager $auth */
        $auth = $om->getContainer()->get('auth');
        $user_id = $auth->userId();

        $users = $om->read(\lodging\identity\User::getType(), $user_id, ['centers_ids', 'groups_ids']);
        $user = reset($users);

        // pos.default.administrator [42]
        // #todo - request the ID from core\Groups
        if(!in_array(42, $user['groups_ids'])) {
            return ['id' => ['missing_permission' => 'Only PoS admins can update a PoS Product.']];
        }

        $products = $om->read(self::getType(), $oids, ['product_model_id.groups_ids.center_id']);

        if($products > 0 && count($products)) {
            foreach($products as $id => $product) {
                $centers_ids = array_map(function ($a) {return $a['center_id']; }, $product['product_model_id.groups_ids.center_id']);
                if(array_intersect($centers_ids, $user['centers_ids']) <= 0) {
                    return ['id' => ['missing_permission' => 'PoS Products can only be updated by users assigned to the same Center.']];
                }
            }
        }
        return parent::canupdate($om, $oids, $values, $lang);
    }

    /**
     * Check wether the invoice can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $oids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $oids) {
        /** @var \equal\auth\AuthenticationManager $auth */
        $auth = $om->getContainer()->get('auth');
        $user_id = $auth->userId();

        $users = $om->read(\lodging\identity\User::getType(), $user_id, ['centers_ids', 'groups_ids']);
        $user = reset($users);

        // pos.default.administrator [42]
        // #todo - request the ID from core\Groups
        if(!in_array(42, $user['groups_ids'])) {
            return ['id' => ['missing_permission' => 'Only PoS admins can delete a PoS Product.']];
        }

        $products = $om->read(self::getType(), $oids, ['product_model_id.groups_ids.center_id']);

        if($products > 0 && count($products)) {
            foreach($products as $id => $product) {
                $centers_ids = array_map(function ($a) {return $a['center_id']; }, $product['product_model_id.groups_ids.center_id']);
                if(array_intersect($centers_ids, $user['centers_ids']) <= 0) {
                    return ['id' => ['missing_permission' => 'PoS Products can only be deelted by users assigned to the same Center.']];
                }
            }
        }

        return parent::candelete($om, $oids);
    }
}