<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\identity\Identity;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\BookingLine;
use sale\customer\CustomerNature;
use sale\booking\BookingType;
use lodging\sale\catalog\Product;



$tests = [
    '0006' => [
        'description'       =>  'Validate that the reservation cannot be deleted in the option status.',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-03'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Reservation cannot be deleted in the option.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 2 nuitées',
                    'order'          => 1,
                    'rate_class_id'  => 4, //'general public'
                    'sojourn_type_id'=> 1 //'GA'
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 1,
                ])
                ->read(['id'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id']
                ])
                ->update([
                    'product_id'            => $product['id']
                ])
                ->read(['id','name','price'])
                ->first(true);

            $booking = Booking::id($booking['id'])
                ->update([
                    'status'                =>'option',
                ])
                ->read(['id'])
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

            $booking = Booking::search(['description', 'like', '%'. 'Reservation cannot be deleted in the option'.'%'])->read('id')->first(true);

            Booking::id($booking['id'])
                ->update([
                    'state'     =>      'archive',
                ]);

        }

    ],
    '0007' => [
        'description'       =>  "Validate the restriction of adding a new Booking Line to a in the reservation in the option status.",
        'help'              =>  "
            Create a reservation for a client for one night.
            Change the reservation status from 'devis' to 'option'.
            Create a new booking line with the product 'Nuit Chambre 2 pers'.
            Retrieve the error message for the new booking line.\n
            Verify the error message.
            Verify that the reservation price has not been modified.",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that new booking line cannot be added to the reservation.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4, //'general public'
                    'sojourn_type_id'=> 1 //'GA'
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 1,
                ])
                ->read(['id'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','label'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id']
                ])
                ->update([
                    'product_id'            => $product['id'],
                ])
                ->read(['id', 'name', 'price'])
                ->first(true);

            Booking::id($booking['id'])
                ->update([
                    'status'                =>'option',
                ])
                ->read(['id','price'])
                ->first(true);

            $new_product = Product::search(['sku','=', 'GA-NuitCh2-A' ])->read(['id'])->first(true);

            try {
                BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id']
                ])
                ->update([
                    'product_id'            => $new_product['id']
                ]);

            } catch (Exception $e) {
                $message = $e->getMessage();

            }

            $data = unserialize($message);
            $messageError = $data['status']['non_editable'];

            $booking = Booking::id($booking['id'])
                ->read(['id','price'])
                ->first(true);

            return [$messageError];
        },
        'assert'            =>  function ($data) {

            if ($data){
                list($messageError) = $data;
            }

            return ($messageError == "Non-extra service lines cannot be changed for non-quote bookings.");
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate that new booking line cannot be added to the reservation'.'%'])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);

        }

    ],
    '0008' => [
        'description'       =>  "Validate that existing reserved services cannot be modified in the reservation in the option status.",
        'help'              =>  "
            Create a reservation for a client for one night.
            Change the reservation status from 'devis' to 'option'.
            Change the product  to 'Nuit Chambre 2 pers'  in the booking line.
            Retrieve the error message for the new booking line.\n
            Verify the error message.
            Verify that the reservation price has not been modified.",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that existing reserved services cannot be modified in the reservation.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4, //'general public'
                    'sojourn_type_id'=> 1 //'GA'
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 1,
                ])
                ->read(['id'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitDort-A' ])->read(['id','label'])->first(true);

            $bookingLine = BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id']
                ])
                ->update([
                    'product_id'            => $product['id'],
                ])
                ->read(['id', 'name', 'price'])
                ->first(true);

            Booking::id($booking['id'])
                ->update([
                    'status'                =>'option',
                ])
                ->read(['id','price'])
                ->first(true);

            $new_product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id'])->first(true);

            try {
                bookingLine::id($bookingLine['id'])
                ->update([
                    'product_id'            => $new_product['id']
                ]);

            } catch (Exception $e) {
                $message = $e->getMessage();

            }

            $data = unserialize($message);
            $messageError = $data['booking_id']['non_editable'];

            $booking = Booking::id($booking['id'])
                ->read(['id','price'])
                ->first(true);

            return [$messageError];
        },
        'assert'            =>  function ($data) {

            if ($data){
                list($messageError) = $data;
            }

            return ($messageError == "Services cannot be updated for non-quote bookings.");
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate that existing reserved services cannot be modified in the reservation.'.'%'])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);

        }

    ],
];
