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
use lodging\sale\booking\SojournType;
use lodging\sale\catalog\Product;
use lodging\sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\BookingType;
use sale\customer\CustomerNature;


$tests = [
    '0001' => [
        'description'       =>  'Create a booking for a single client and multiple days.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices, and sum of lines prices. \n
            The created booking is subject to extra services (membership card); is set during low season; and total is expected to be 730,90 EUR VAT incl. \n
            Center: Louvain-la-neuve \n
            Dates from: 01-01-2023
            Dates to: 15-01-2023 (14 nights)
            Numbers pers: 1
            Product: Nuit Chambre 1 pers
            Produc: Taxe Séjour",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-15'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for a single client and multiple days'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                'booking_id'     => $booking['id'],
                'is_sojourn'     => true,
                'group_type'     => 'sojourn',
                'has_pack'       => false,
                'name'           => 'Séjour 1 pers',
                'order'          => 1,
                'rate_class_id'  => 4, //'general public'
                'sojourn_type_id'=> 1  //'GA'
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

            $bookingline = BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id']
                ])
                ->update([
                    'product_id'            => $product['id']
                ])
                ->read(['id','name','price'])
                ->first(true);

            $booking = Booking::id($booking['id'])->read(['id', 'price'])->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $bookingLines = BookingLine::search(['booking_id','=', $booking['id']])->read(['id','price']);

            $total_price_bl = 0;
            foreach($bookingLines as $bookingLine) {
                $total_price_bl += $bookingLine['price'];
            }

            $bookingLineGroups = BookingLineGroup::search(['booking_id','=', $booking['id']])->read(['id','price']);

            $total_price_blg = 0;
            foreach($bookingLineGroups as $bookingLineGroup) {
                $total_price_blg += $bookingLineGroup['price'];
            }

            return ($booking['price'] == 730.9 && $booking['price'] == $total_price_bl && $booking['price'] == $total_price_blg);
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Booking test for a single client and multiple days'.'%'])->read('id')->first(true);

            Booking::id($booking['id'])->delete(true);

        }

    ],
    '0002' => [
        'description'       =>  'Create a booking for 10 persons only for 1 day.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices, and sum of lines prices. \n
            The created booking is subject to extra services (membership card); is set during low season; and total is expected to be 523.5 EUR VAT incl. \n
            Client: Alexandra Cabrera
            Center: Louvain-la-neuve
            Dates from: 01-01-2023
            Dates to: 02-01-2023
            Night: 1 night
            Numbers pers: 10 adults
        ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            if ($data){
                list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;
            }

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for a multiple persons by only day'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour 1 pers',
                    'order'          => 1,
                    'rate_class_id'  => 4, //'general public'
                    'sojourn_type_id'=> 1, //'GA
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 10
                ])
                ->read(['id'])->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])
                ->read(['id'])
                ->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id'],
                    'product_id'            => $product['id'],
                    'qty'                   => 10,
                    'order'                 => 1
                ])
                ->update([
                    'nb_pers'               => 10,
                ])
                ->read(['id','price', 'qty','name'])
                ->first(true);

            $booking = Booking::id($booking['id'])->read(['id', 'price'])->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $bookingLines = BookingLine::search(['booking_id','=', $booking['id']])->read(['id','price']);

            $total_price_bl = 0;
            foreach($bookingLines as $bookingLine) {
                $total_price_bl += $bookingLine['price'];
            }

            $bookingLineGroups = BookingLineGroup::search(['booking_id','=', $booking['id']])->read(['id','price']);

            $total_price_blg=0;
            foreach($bookingLineGroups as $bookingLineGroup) {
                $total_price_blg += $bookingLineGroup['price'];
            }

            $total_price_bl = round($total_price_bl, 2);
            $booking_price = round($booking['price'], 2);
            $total_price_blg = round($total_price_blg, 2);

            return ($booking_price==$total_price_bl && $total_price_bl==$total_price_blg && $booking_price == 523.5);

        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like','%'. 'Booking test for a multiple persons by only day'.'%'])->read('id')->first(true);

            Booking::id($booking['id'])->delete(true);

        }

    ],

    '0003' => [
        'description' => 'Create a reservation for children aged 12 and 2 adults and above for 3 days.',
        'help' => "
            Creates a booking with the following configuration and verify the consistency between the booking price and the sum of group prices  \n
            This booking is scheduled during the low season, and it applies the 'school' category to access its advantages.\n
            The total is expected to be 1885.12 EUR VAT incl. \n
            Center: Louvain-la-neuve
            Dates from: 01-01-2023
            Dates to: 03-01-2023
            Numbers pers: 22 (20 children + 2 adults)
            Age: 12 years (children)
            Packs: 'Séjour scolaire Secondaire' and 'Pension Complète dortoir' ",

        'arrange' =>  function () {
            $center = Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },

        'act' =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-03'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for 20 children aged 12 and above for 3 days'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack_children = Product::search(['sku','=','GA-SejScoSec-A'])
                ->read(['id','label'])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack_children['id'],
                    'rate_class_id'  => 5, //Ecoles primaires et secondaire
                    'sojourn_type_id'=> 1, //'GA'
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'       => 20
                ]);

            $secondary_age_range_id = 2;
            BookingLineGroupAgeRangeAssignment::search([
                    ['booking_id', '=', $booking['id']],
                ])
                ->update(['age_range_id' => $secondary_age_range_id]);

            $pack_pension = Product::search(['sku', '=', 'GA-DortPC-A'])
                ->read(['id','label'])
                ->first(true);

            BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack_pension['id'],
                    'rate_class_id'  => 5, //Ecoles primaires et secondaire
                    'sojourn_type_id'=> 1, //'GA'
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'       => 2
                ]);

            $booking = Booking::id($booking['id'])->read(['id','price'])->first();
            return($booking);
        },

        'assert' =>  function ($booking) {
            $bookingLineGroups = BookingLineGroup::search(['booking_id','=', $booking['id']])->read(['id','price']);
            $total_price_blg = 0;
            foreach($bookingLineGroups as $bookingLineGroup) {
                $total_price_blg += $bookingLineGroup['price'];
            }
            $total_price_blg = round($total_price_blg, 2);
            $booking_price = round($booking['price'], 2);

            return ($booking_price == $total_price_blg  && $booking_price == 1839.12);
        },

        'rollback' =>  function () {
            $booking = Booking::search(['description', 'like', '%'. 'Booking test for 20 children aged 12 and above for 3 days'.'%' ])
                ->read('id')
                ->first(true);

            Booking::id($booking['id'])->delete(true);
        }

    ],
    '0004' => [
        'description'       => 'Create a booking at Arbrefontaine Center for the midseason.',
        'help'              => "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices. \n
            This booking includes extra services such as a membership card and is scheduled during the midseason.\n
            The product 'Nuitée Arbrefontaine - Petite Maison' comes with advantages.
            The total cost, including VAT, is expected to be 711.1 EUR.\n
            Center: Arbrefontaine  - Petite maison
            Season: midseason
            Sejourn Type: Gîte de Groupe
            Dates from: 16/02/2023
            Dates to: 20/02/2023
            Nights: 4 nights",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Arbrefontaine - Petite maison%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-16'),
                    'date_to'               => strtotime('2023-02-20'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Create a booking at Arbrefontaine - Petite maison'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $product = Product::search(['sku','like','%'. 'AP-ArbPM-A'. '%' ])
                    ->read(['id','label'])
                    ->first(true);

            $sojourn_type = SojournType::search(['name','like','%'.'GG'. '%'])
                    ->read(['id','name'])
                    ->first(true);
            BookingLineGroup::create([
                'booking_id'     => $booking['id'],
                'name'           => "Séjour Arbrefontaine Petite",
                'order'          => 1,
                'group_type'     => 'sojourn',
                'is_sojourn'     => true,
                'has_pack'       => true,
                'pack_id'        => $product['id'],
                'rate_class_id'  => 4, //general
                'sojourn_type_id'=> $sojourn_type['id']
            ])
            ->update([
                'date_from'      => $booking['date_from'],
                'date_to'        => $booking['date_to'],
            ])
            ->update([
                'nb_pers'       => 1
            ]);

            $booking = Booking::id($booking['id'])->read(['id','price'])->first(true);
            return($booking);
        },
        'assert'            =>  function ($booking) {

            $bookingLineGroups = BookingLineGroup::search(['booking_id','=', $booking['id']])->read(['id','price','price_adapters_ids' => ['id', 'value']])->get(true);

            $total_price_blg = 0;
            foreach($bookingLineGroups as $bookingLineGroup) {
                $total_price_blg += $bookingLineGroup['price'];
            }
            return ($booking['price']== $total_price_blg && $booking['price'] == 537.1);
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Create a booking at Arbrefontaine - Petite maison'.'%' ])->read('id')->first(true);
            Booking::id($booking['id'])->delete(true);

        }

    ],
    '0005' => [
        'description'       => 'Create a booking at Arbrefontaine Center, the price adapters as GA.',
        'help'              => "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices. \n
            This booking includes extra services such as a membership card and is scheduled during the midseason.\n
            The product 'Séjour scolaire Secondaire (exCDV de 12 ans à 25 ans) (GG-SejScoSec-A)' comes with advantages.
            The total cost, including VAT, is expected to be 827.6 EUR.\n
            Center: Arbrefontaine - École
            Pack: Séjour scolaire Secondaire
            Season: midseason
            Sejourn Type: Gîte Auberge
            Numbers pers: 10
            Cetegory sojourn: Ecoles primaires et secondaires
            Dates from: 16/02/2023
            Dates to: 18/02/2023
            Nights: 2 nights",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Arbrefontaine - École%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-16'),
                    'date_to'               => strtotime('2023-02-18'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Create a booking at Arbrefontaine,the price adapters like GA'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $product = Product::search(['sku', 'like', '%GG-SejScoSec-A%'])
                ->read(['id', 'label'])
                ->first(true);

            $sojourn_type = SojournType::search(['name', 'like', '%GA%'])
                ->read(['id', 'name'])
                ->first(true);

            BookingLineGroup::create([
                'booking_id'        => $booking['id'],
                'name'              => "Séjour Arbrefontaine Petite",
                'order'             => 1,
                'is_sojourn'        => true,
                'group_type'        => 'sojourn',
                'has_pack'          => true,
                'pack_id'           => $product['id'],
                'sojourn_type_id'   => $sojourn_type['id'],
                'rate_class_id'     => 5,                   // Ecoles primaires et secondaires
            ])
            ->update([
                'date_from' => $booking['date_from'],
                'date_to'   => $booking['date_to'],
            ])
            ->update([
                'nb_pers'   => 10
            ]);

            $secondary_age_range_id = 2;
            BookingLineGroupAgeRangeAssignment::search([
                ['booking_id', '=', $booking['id']],
            ])
                ->update(['age_range_id' => $secondary_age_range_id]);

            return Booking::id($booking['id'])->read(['id','price'])->first();
        },
        'assert'            =>  function ($booking) {
            $bookingLineGroups = BookingLineGroup::search(['booking_id','=', $booking['id']])->read(['id','price','price_adapters_ids' => ['id', 'value']])->get(true);
            $total_price_blg = 0;
            foreach($bookingLineGroups as $bookingLineGroup) {
                $total_price_blg += $bookingLineGroup['price'];
            }

            return ($booking['price']== $total_price_blg && $booking['price'] == 800.2);
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Create a booking at Arbrefontaine,the price adapters like GA'.'%' ])->read('id')->first(true);
            Booking::id($booking['id'])->delete(true);

        }

    ]
];
