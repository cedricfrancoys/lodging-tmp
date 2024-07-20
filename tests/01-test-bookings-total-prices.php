<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLineGroup;
use sale\customer\CustomerNature;
use sale\booking\BookingType;
use sale\customer\RateClass;
use lodging\sale\booking\SojournType;
use lodging\sale\catalog\Product;
use lodging\sale\booking\BookingLine;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [
    //0xxx : calls related to QN methods
    '0101 Lewyllie' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Rochefort%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                'date_from'             => strtotime('2023-11-07'),
                'date_to'               => strtotime('2023-11-08'),
                'type_id'               => $booking_type_id,
                'center_id'             => $center_id,
                'customer_nature_id'    => $customer_nature_id,
                'customer_identity_id'  => 15002420,
                'description'           => 'Booking test Lewyllie verify for matching total prices'
            ])
                ->first(true);

            BookingLineGroup::create([
                'booking_id'        => $booking['id'],
                'name'              => 'Séjour Rochefort',
                'order'             => 1,
                'rate_class_id'     => 4,
                'sojourn_type_id'   => 1,
                'is_sojourn'        => true,
                'group_type'        => 'sojourn'
            ])
                ->update([
                    'has_pack'  => true,
                    'pack_id'   => 378,
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-08')
                ])
                ->update([
                    'nb_pers'   => 3
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 161.3;
        },
        'expected'          => 161.3,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Lewyllie verify for matching total prices'])
                ->delete(true);
        }
    ],

    '0102 Familie Veltjen' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'FA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-11-07'),
                    'date_to'               => strtotime('2023-11-08'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 4670,
                    'description'           => 'Booking test Jackie Buysse verify for matching total prices'
                ])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'name'              => 'Séjour Louvain-la-neuve',
                    'order'             => 1,
                    'rate_class_id'     => 4,
                    'sojourn_type_id'   => 1,
                    'has_pack'          => true,
                    'pack_id'           => 378,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-08')
                ])
                ->update([
                    'nb_pers'   => 3
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 178.65;
        },
        'expected'          => 178.65,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Familie Veltjen verify for matching total prices'])->delete(true);
        }
    ],

    '0103 Jackie Buysse' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Rochefort%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'AM'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-11-07'),
                    'date_to'               => strtotime('2023-11-10'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 12615,
                    'description'           => 'Booking test Jackie Buysse verify for matching total prices'
                ])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'name'              => 'Séjour Rochefort',
                    'order'             => 1,
                    'rate_class_id'     => 4,
                    'sojourn_type_id'   => 1,
                    'has_pack'          => true,
                    'pack_id'           => 379,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-10')
                ])
                ->update([
                    'nb_pers'   => 4
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 605.6;
        },
        'expected'          => 605.6,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Jackie Buysse verify for matching total prices'])->delete(true);
        }
    ],

    '0104 Michele Malbrecq' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-11-07'),
                    'date_to'               => strtotime('2023-11-10'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 10712,
                    'description'           => 'Booking test Michele Malbrecq verify for matching total prices'
                ])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'order'             => 1,
                    'rate_class_id'     => 4,
                    'sojourn_type_id'   => 1,
                    'has_pack'          => true,
                    'pack_id'           => 379,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-10')
                ])
                ->update([
                    'nb_pers'   => 4
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 664.4;
        },
        'expected'          => 664.4,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Michele Malbrecq verify for matching total prices'])
                ->delete(true);
        }
    ],

    '0105 Mireille Wauters' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Rochefort%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'AM'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-11-07'),
                    'date_to'               => strtotime('2023-11-08'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 15001911,
                    'description'           => 'Booking test Mireille Wauters verify for matching total prices'
                ])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'name'              => 'Séjour Rochefort',
                    'order'             => 1,
                    'rate_class_id'     => 4,
                    'sojourn_type_id'   => 1,
                    'has_pack'          => true,
                    'pack_id'           => 365,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-08')
                ])
                ->update([
                    'nb_pers'   => 2
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 60.3;
        },
        'expected'          => 60.3,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Mireille Wauters verify for matching total prices'])->delete(true);
        }
    ],

    '0106 Mathieu Braekeveld' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'AL'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-11-07'),
                    'date_to'               => strtotime('2023-11-08'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 12567,
                    'description'           => 'Booking test Mathieu Braekeveld verify for matching total prices'
                ])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'name'              => 'Séjour Louvain-la-Neuve',
                    'order'             => 1,
                    'rate_class_id'     => 4,
                    'sojourn_type_id'   => 1,
                    'has_pack'          => true,
                    'pack_id'           => 365,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-08'),
                ])
                ->update([
                    'nb_pers'   => 2
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 81.9;
        },
        'expected'          => 81.9,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Mathieu Braekeveld verify for matching total prices'])->delete(true);
        }
    ],

    '0107 Verena Müllender' => [
        'description'       => 'Creating bookings and looking out for matching TOTAL PRICES',
        'arrange'           => function() {
            $center = Center::search(['name', 'like', '%Wanne%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id']];
        },
        'act'               => function($data) {

            list($center_id, $booking_type_id, $customer_nature_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-11-07'),
                    'date_to'               => strtotime('2023-11-08'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 10908,
                    'description'           => 'Booking test Verena Müllender verify for matching total prices'
                ])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'name'              => 'Séjour Wanne',
                    'order'             => 1,
                    'rate_class_id'     => 4,
                    'sojourn_type_id'   => 1,
                    'has_pack'          => true,
                    'pack_id'           => 365,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from' => strtotime('2023-11-07'),
                    'date_to'   => strtotime('2023-11-08'),
                ])
                ->update([
                    'nb_pers'   => 2
                ]);

            return Booking::id($booking['id'])
                ->read(['price'])
                ->first(true);
        },
        'assert'            => function($booking) {
            return $booking['price'] == 66.4;
        },
        'expected'          => 66.4,
        'rollback'          => function() {
            Booking::search(['description', '=', 'Booking test Verena Müllender verify for matching total prices'])->delete(true);
        }
    ],

    '0108 Olivier Signet' => [
        'description'       =>  'Creating bookings and looking out for matching TOTAL PRICES',
        'help'              => "
            Creates a booking with configuration below and test the consistency between  invoice price, booking price, group booking price, and sum of lines prices. \n
            Center: Villers-Sainte-Gertrude
            Sejourn Type: Gîte de Auberge
            RateClass: Grand public
            Dates from: 08/04/2023
            Dates to: 11/04/2023
            Pack: Chambre 3 personnes Pension Complète
            Nights: 3 nights
            Numbers pers: 3 Adults",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'AM'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T4'])->read(['id'])->first(true);


            return [$center['id'], $booking_type['id'], $customer_nature['id'], $sojourn_type['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $sojourn_type_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-04-08'),
                    'date_to'               => strtotime('2023-04-11'),
                    'type_id'               => $booking_type_id,
                    'center_id'             => $center_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => 15002557,
                    'description'           => 'Booking Test 0108 Olivier Signet for matching TOTAL PRICES'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'        => $booking['id'],
                    'name'              => 'Séjour villers-Sainte-Gertrude',
                    'order'             => 1,
                    'rate_class_id'     => $rate_class_id,
                    'sojourn_type_id'   => $sojourn_type_id,
                    'is_sojourn'        => true,
                    'group_type'        => 'sojourn'
                ])
                ->update([
                    'date_from'     => $booking['date_from'],
                    'date_to'       => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'       => 3
                ])
                ->update([
                    'has_pack'      => true
                ])
                ->update([
                    'pack_id'       => 378
                ]);

            $booking = Booking::id($booking['id'])
                ->read(['id', 'price', 'total'])
                ->first(true);

            $bookingLineGroups = BookingLineGroup::search(['booking_id','=', $booking['id']])
                ->read(['id', 'price', 'total'])
                ->get(true);

            $bookingLines = BookingLine::search(['booking_id','=', $booking['id']])
                ->read(['id', 'price', 'total'])
                ->get(true);

            return [$booking, $bookingLineGroups, $bookingLines];
        },
        'assert'            =>  function ($data) {

            list($booking, $bookingLineGroups, $bookingLines) = $data;

            $total_price_blg=0;
            foreach($bookingLineGroups as $bookingLineGroup) {
                $total_price_blg += $bookingLineGroup['price'];
            }

            $total_price_bl = 0;
            foreach($bookingLines as $bookingLine) {
                // precision greater than 2 must only be kept at line level, not above
                $total_price_bl += round($bookingLine['price'], 2);
            }

            return ($booking['price'] == 452.82 && $booking['price'] == $total_price_bl && $booking['price'] == $total_price_blg);
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Booking Test 0108 Olivier Signet for matching TOTAL PRICES'.'%'])->delete(true);
        }
    ]

];


