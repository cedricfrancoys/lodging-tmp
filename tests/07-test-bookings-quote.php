<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\identity\Identity;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\Contract;
use lodging\sale\booking\SojournType;
use lodging\sale\catalog\Product;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;
use lodging\sale\booking\SojournProductModel;
use lodging\realestate\RentalUnit;
use lodging\sale\catalog\ProductModel;
use sale\customer\CustomerNature;
use sale\booking\BookingType;
use sale\customer\RateClass;



$tests = [
    '0701' => [
        'description'       =>  "Validate that the reservation can be changed from an option to a quote without locative units being free.",
        'help'              =>  "
            Booking for 2 persons for 2 nights
            Create a reservation for a client for one night.
            Change the reservation status from 'quote' to 'option'
            Change the reservation status from 'option' to 'quote'",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);


            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-25'),
                    'date_to'               => strtotime('2023-02-29'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate changing the reservation to a quote without free units.'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack = Product::search(['sku','like','%'. 'GA-SejScoPri-A'. '%' ])
                    ->read(['id','label'])
                    ->first(true);


            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'name'           => $pack['label'],
                    'order'          => 1,
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack['id'],
                    'rate_class_id'  => $rate_class_id,
                    'sojourn_type_id'=> $sojourn_type_id
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'       => 2
                ])
                ->read(['id', 'price', 'booking_lines_ids'])
                ->first(true);

            $product_model = ProductModel::search([
                    ['can_sell','=', true],
                    ['is_rental_unit','=', true],
                    ['is_accomodation','=', true],
                    ['name', 'like' , '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model  =   SojournProductModel::search([
                    ['booking_id' , "=" , $booking['id']],
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_unit = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['sojourn_type_id', '=' , $sojourn_type_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','sojourn_type_id','capacity','room_types_ids'])
                ->first(true);

            $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                    'booking_id'                => $booking['id'],
                    'booking_line_group_id'     => $booking_line_group['id'],
                    'sojourn_product_model_id'  => $sojourn_product_model['id'],
                    'rental_unit_id'            => $rental_unit['id'],
                    'qty'                       => $rental_unit['capacity'],
                    'is_accomodation'           => true
                ])
                ->read(['id','qty', 'booking_id', 'rental_unit_id'])
                ->first(true);

            $booking = Booking::id($booking['id'])
                ->read(['id','price', 'nb_pers', 'booking_lines_ids'])
                ->first();

            try {
                eQual::run('do', 'lodging_booking_do-option', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'lodging_booking_do-quote', ['id' => $booking['id'], 'free_rental_units' => false]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $spm_rental_unit_assignement;

        },
        'assert'            =>  function ($spm_rental_unit_assignment) {

            $booking = Booking::search(['id','=', $spm_rental_unit_assignment['booking_id']])
                ->read(['id','status','nb_pers','rental_unit_assignments_ids'])
                ->first(true);

            $rental_unit_assignments_ids = $booking['rental_unit_assignments_ids'];

            foreach ($rental_unit_assignments_ids as $rental_unit_assignments_id){
                $b_spm_rental_unit_assignment = SojournProductModelRentalUnitAssignement::id($rental_unit_assignments_id)
                    ->read(['id'])
                    ->first(true);
            }
            return (
                    $booking['status'] == 'quote' &&
                    $booking['id'] == $spm_rental_unit_assignment['booking_id'] &&
                    $booking['nb_pers'] == $spm_rental_unit_assignment['qty'] &&
                    $b_spm_rental_unit_assignment['id'] == $spm_rental_unit_assignment['id']
            );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Validate changing the reservation to a quote without free units.'.'%'])
                        ->delete(true);
        }
    ],
    '0702' => [
        'description'       =>  "Validate that the reservation can be changed from an option to a quote with locative units being free.",
        'help'              =>  "
            Booking for 4 persons for 2 nights
            Create a reservation for a client for one night.
            Change the reservation status from 'quote' to 'option'
            Change the reservation status from 'option' to 'quote'",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-25'),
                    'date_to'               => strtotime('2023-02-27'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate changing the reservation to a quote with free units.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 4 personne pendant 2 nuitées',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 4,
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id']
                ])
                ->update([
                    'product_id'            => $product['id']
                ])
                ->read(['id','name','price'])
                ->first(true);

            $product_model = ProductModel::id($product['product_model_id'])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model  =   SojournProductModel::search([
                    ['booking_id' , "=" , $booking['id']],
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['sojourn_type_id', '=' , $sojourn_type_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','name','sojourn_type_id','capacity','room_types_ids']);

            $num_rua = 0;
            foreach ($rental_units as $rental_unit) {

                if ($num_rua >= $booking_line_group['nb_pers']) {
                    break;
                }

                $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                    'booking_id' => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'sojourn_product_model_id' => $sojourn_product_model['id'],
                    'rental_unit_id' => $rental_unit['id'],
                    'qty' => $rental_unit['capacity'],
                    'is_accomodation' => true
                ])
                ->read(['id','qty'])
                ->first(true);

                $num_rua+= $spm_rental_unit_assignement['qty'];

            };


            try {
                eQual::run('do', 'lodging_booking_do-option', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'lodging_booking_do-quote', ['id' => $booking['id'], 'free_rental_units' => true]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $booking['id'];

        },
        'assert'            =>  function ($booking_id) {

            $booking = Booking::id($booking_id)
                ->read(['status', 'nb_pers', 'consumptions_ids' => ['is_accomodation', 'rental_unit_id']])
                ->first(true);

            return ($booking['status'] == 'quote' &&
                    count($booking['consumptions_ids']) ==  0 );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Validate changing the reservation to a quote with free units'.'%'])
                        ->delete(true);
        }
    ],
    '0703' => [
        'description'       =>  'Validate that the contract has been cancelled and the funding unit has been deleted if the reservation changes from confirmed to quote',
        'help'              =>  "
            Create a reservation for 4 persons client for one night.
            Change the reservation status from 'quote' to 'confirm'.
            Change the reservation status from 'confirm' to 'quote'.
            Verify that the contract has been cancelled.
            Verify that the unpaid funding has been deleted.
            Verify that the reservation is in the quote status.",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-28'),
                    'date_to'               => strtotime('2023-02-29'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate contract and funding cancellation on reservation shift from confirmed to quoted.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 4 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 4,
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id']
                ])
                ->update([
                    'product_id'            => $product['id']
                ])
                ->read(['id','name','price'])
                ->first(true);

            $product_model = ProductModel::id($product['product_model_id'])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model  =   SojournProductModel::search([
                    ['booking_id' , "=" , $booking['id']],
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['sojourn_type_id', '=' , $sojourn_type_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','name','sojourn_type_id','capacity','room_types_ids']);

            $num_rua = 0;
            foreach ($rental_units as $rental_unit) {

                if ($num_rua >= $booking_line_group['nb_pers']) {
                    break;
                }

                $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                    'booking_id' => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'sojourn_product_model_id' => $sojourn_product_model['id'],
                    'rental_unit_id' => $rental_unit['id'],
                    'qty' => $rental_unit['capacity'],
                    'is_accomodation' => true
                ])
                ->read(['id','qty'])
                ->first(true);

                $num_rua+= $spm_rental_unit_assignement['qty'];

            };


            try {
                eQual::run('do', 'lodging_booking_do-option', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'lodging_booking_do-confirm', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            Booking::id($booking['id'])->update(['status' => 'confirmed']);

            try {
                eQual::run('do', 'lodging_booking_do-quote', ['id' => $booking['id'], 'free_rental_units' => true]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return Booking::id($booking['id'])
                ->read(['status',
                        'contracts_ids',
                        'fundings_ids'])
                ->first(true);
        },
        'assert'            =>  function ($booking) {

            $contract = Contract::id($booking['contracts_ids'][0])
                ->read(['status'])
                ->first();

            return $booking['status'] == 'quote' &&
                $contract['status'] == 'cancelled' &&
                count($booking['fundings_ids']) == 0;
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate contract and funding cancellation on reservation shift from confirmed to quoted'.'%'])
                ->read(['contracts_ids'])
                ->first();

            Contract::id($booking['contracts_ids'][0])->delete(true);

            Booking::id($booking['id'])->delete(true);
        }
    ]
];
