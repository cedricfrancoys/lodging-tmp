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
use lodging\sale\booking\BookingLineGroupAgeRangeAssignment;
use lodging\sale\booking\Invoice;
use lodging\sale\booking\SojournType;
use sale\customer\CustomerNature;
use sale\customer\RateClass;
use sale\booking\BookingType;
use lodging\sale\catalog\Product;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;
use lodging\sale\booking\SojournProductModel;
use lodging\realestate\RentalUnit;
use lodging\sale\catalog\ProductModel;
use lodging\sale\booking\Contract;


$tests = [
    '0010' => [
        'description'       => 'Validate the invoice price including the TVA.',
        'help'              => "
            Creates a booking with configuration below and test the consistency between  invoice price, booking price, group booking price, and sum of lines prices. \n
            The price for the group booking is determined based on the advantages associated with the 'Ecoles primaires et secondaires' category.'\n
            The 'Nuitée Séjour scolaire Plus de 26 ans', 'Petit déjeuner (matin)' and 'Taxe Séjour' have a 6% VAT applied to their prices.'\n
            The 'Lunch (midi)', 'Repas chaud (soir)' and 'Repas chaud arrivée Séjour scolaire' have a 12% VAT applied to their prices.'\n
            The 'Carte de membre petit Gîte' is exempt from VAT with a 0% rate.'\n
            WorkFlow reservation:
                quote -> option: lodging_booking_do-option
                option -> confirmed : lodging_booking_do-confirm
                Contract Singed: lodging_contract_signed
                confirmed -> checkedin : lodging_booking_do-checkin
                checkedin -> checkedout : lodging_booking_do-checkout
                checkedout -> invoiced: lodging_booking_do-invoice
            Center: Villers-Sainte-Gertrude
            Sejourn Type: Gîte Auberge
            RateClass: Ecoles primaires et secondaires
            Dates from: 10/03/2023
            Dates to: 14/03/2023
            Nights: 4 nights
            Numbers pers: 10 children (Primaire).",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-10'),
                    'date_to'               => strtotime('2023-03-14'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the invoice price including the TVA.'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack = Product::search(['sku', 'like', '%GA-SejScoPri-A%'])
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
                ->read(['id', 'nb_pers', 'price', 'fare_benefit','nb_pers','booking_lines_ids'])
                ->first(true);

            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update(['age_range_id' => 3]);

            $booking_line_group = BookingLineGroup::id($booking_line_group['id'])
                ->update(['nb_pers' => 10])
                ->read(['id', 'nb_pers', 'price', 'fare_benefit', 'nb_pers', 'booking_lines_ids'])
                ->first(true);

            $product_model = ProductModel::search([
                    ['can_sell', '=', true],
                    ['is_rental_unit', '=', true],
                    ['is_accomodation', '=', true],
                    ['name', 'like', '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id', "=", $booking_line_group['id']],
                    ['product_model_id', "=", $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=', $center_id],
                    ['sojourn_type_id', '=', $sojourn_type_id],
                    ['is_accomodation', '=', true],
                ])
                ->read(['id', 'name', 'sojourn_type_id', 'capacity', 'room_types_ids']);

            $num_rua = 0;
            foreach ($rental_units as $rental_unit) {
                if ($num_rua >= $booking_line_group['nb_pers']) {
                    break;
                }

                $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                        'booking_id'                => $booking['id'],
                        'booking_line_group_id'     => $booking_line_group['id'],
                        'sojourn_product_model_id'  => $sojourn_product_model['id'],
                        'rental_unit_id'            => $rental_unit['id'],
                        'qty'                       => $rental_unit['capacity'],
                        'is_accomodation'           => true
                    ])
                    ->read(['id','qty'])
                    ->first(true);

                $num_rua += $spm_rental_unit_assignement['qty'];

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

            try {
                $contract = Contract::search([
                            ['booking_id', '=',  $booking['id']],
                            ['status', '=',  'pending'],
                    ])
                    ->read(['id', 'status'])
                    ->first(true);

                eQual::run('do', 'lodging_contract_signed', ['id' => $contract['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'lodging_booking_do-checkin', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'lodging_booking_do-checkout', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'lodging_booking_do-invoice', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id', 'date_from', 'date_to', 'price', 'total'])
                ->first(true);

            $invoice = Invoice::search(['booking_id' ,'=', $booking['id']])
                ->read(['id', 'status', 'price', 'total'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::search(['booking_id','=', $booking['id']])
                ->read(['id', 'price', 'total'])
                ->first(true);

            $bookingLines = BookingLine::search(['booking_id','=', $booking['id']])
                ->read(['id','price', 'total'])
                ->get(true);

            return [$booking, $invoice, $bookingLineGroup, $bookingLines];

        },
        'assert'            =>  function ($data) {

            list($booking, $invoice, $bookingLineGroup, $bookingLines) = $data;

            $total_price_bl = 0;
            foreach($bookingLines as $bookingLine) {
                // precision greater than 2 must only be kept at line level, not above
                $total_price_bl += round($bookingLine['price'], 2);
            }

            return (
                $booking['price'] == 1418.12 &&
                $booking['price'] == $invoice['price'] &&
                $booking['price'] == $bookingLineGroup['price'] &&
                $booking['price'] == $total_price_bl
            );

        },
        'rollback'          =>  function () {
            $booking = Booking::search(['description', 'like', '%'. 'Validate the invoice price including the TVA'.'%' ])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);
        }
    ]
];
