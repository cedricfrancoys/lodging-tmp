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
use lodging\sale\booking\SojournType;
use sale\customer\CustomerNature;
use sale\booking\BookingType;
use lodging\sale\catalog\Product;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;
use lodging\sale\booking\SojournProductModel;
use lodging\realestate\RentalUnit;
use lodging\sale\catalog\ProductModel;
use lodging\sale\booking\Contract;



$tests = [
    '0201' => [
        'description'       =>  'Validate that the reservation cannot be deleted in the option confirm.',
        'help'              =>  "
            Create a reservation for 4 persons client for one night.
            Change the reservation status from 'devis' to 'confirm'.
            Verify that the reservation could cannot be deleted.",
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
                    'date_from'             => strtotime('2023-02-17'),
                    'date_to'               => strtotime('2023-02-19'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Reservation cannot be deleted in the confirm.'
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
                eQual::run('do', 'lodging_booking_do-confirm', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id','status'])
                ->first(true);

            return $booking['id'];
        },
        'assert'            =>  function ($booking_id) {
            try {
                Booking::id($booking_id)->delete(true);
            } catch (Exception $e) {

                $code= $e->getCode();
            }
            return ($code == -32);
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Reservation cannot be deleted in the confirm'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Booking::id($booking['id'])->delete(true);
        }
    ],
    '0202' => array(
        'description'       =>  'Verify that the contract price matches the reservation price.',
        'help'              =>  "
            Create a reservation for 1 person client for two night.
            Change the reservation status from 'devis' to 'confirm'.
            Verify that the contract price matches the reservation price.",
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
                    'date_from'             => strtotime('2023-04-21'),
                    'date_to'               => strtotime('2023-04-23'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Verify that the contract price matches the reservation price.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 1,
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

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'option'])
                ->read(['id','status'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-confirm', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id','status'])
                ->first(true);

            return $booking['id'];
        },
        'assert'            =>  function ($booking_id) {

            $booking = Booking::id($booking_id)
                ->read(['id','price', 'status'])
                ->first(true);

            $contract = Contract::search([
                    ['booking_id', '=',  $booking['id']],
                    ['status', '=',  'pending'],
                ])
                ->read(['id', 'price', 'status'])
                ->first(true);

            return $booking['price'] == $contract['price'];
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'ilike', '%'. 'Verify that the contract price matches the reservation price'.'%'])
                  ->update(['status' => 'quote'])
                  ->read(['id'])
                  ->first(true);
            Booking::id($booking['id'])->delete(true);
        }
    ),
    '0203' => array(
        'description'       =>  "Validate that a new booking line cannot be added to the reservation in confirmed status.",
        'help'              =>  "
            Create a reservation for a client for one night.
            Change the reservation status from 'devis' to 'confirm'.
            Create a new booking line with the product 'Nuit Chambre 2 pers'.
            Retrieve the error message for the new booking line.\n
            Verify the error message.
            Verify that the reservation price has not been modified.",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id'] ];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-06-11'),
                    'date_to'               => strtotime('2023-06-13'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Reservation cannot be deleted in the confirm.'
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
                eQual::run('do', 'lodging_booking_do-confirm', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id','status'])
                ->first(true);

            $new_product = Product::search(['sku','=', 'GA-NuitCh2-A' ])->read(['id'])->first(true);
            try {
                BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id']
                ])
                ->update([
                    'product_id'            => $new_product['id']
                ]);

            } catch (Exception $e) {
                $message = $e->getMessage();

            }

            $data = unserialize($message);
            $messageError = $data['status']['non_editable'];
            return [$messageError];
        },
        'assert'            =>  function ($data) {

            if ($data){
                list($messageError) = $data;
            }

            return ($messageError == "Non-extra service lines cannot be changed for non-quote bookings.");
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'ilike', '%'. 'Reservation cannot be deleted in the confirm'.'%'])
                  ->update(['status' => 'quote'])
                  ->read(['id'])
                  ->first(true);
            Booking::id($booking['id'])->delete(true);


        }
    ),
    '0204' => array(
        'description'       =>  "Validate that the reservation is not in confirmed status if the rental units are not assigned.",
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
                    'date_from'             => strtotime('2023-05-10'),
                    'date_to'               => strtotime('2023-05-12'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation is not in confirmed status if the rental units are not assigned.'
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

            return $booking['id'];
        },
        'assert'            =>  function ($booking_id) {

            $booking = Booking::id($booking_id)
                ->read(['id','status'])
                ->first(true);

            return ($booking['status'] != 'confirm');
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate that the reservation is not in confirmed status if the rental units are not assigned'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Booking::id($booking['id'])->delete(true);
        }
    )
];
