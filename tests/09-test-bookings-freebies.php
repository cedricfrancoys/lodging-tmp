<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\identity\CenterOffice;
use lodging\identity\Identity;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\BookingLineGroupAgeRangeAssignment;
use lodging\sale\booking\BookingType;
use lodging\sale\booking\SojournType;
use lodging\sale\catalog\Product;
use sale\customer\CustomerNature;
use sale\customer\RateClass;

$tests = [
    '0901' => [
        'description'   => 'Test that a discount that applies on adult age range does not exceed nb_adults when value_max is set to nb_adults.',
        'help'          => 'The discount should give 5 freebie but only give 3 because of the value_max set to "nb_adults".',
        'arrange'       => function() {
            return [
                Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id', 'center_office_id'])->first(true),
                BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true)['id'],
                CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true)['id'],
                Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true)['id'],
                SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true)['id'],
                RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true)['id']
            ];
        },
        'act'           => function($data) {

            list($center, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id) = $data;

            $center_office = CenterOffice::id($center['center_office_id'])
                ->read(['id', 'freebies_manual_assignment'])
                ->first(true);

            // Change freebies_manual_assignment if needed
            $center_office_freebies_manual_assignment_changed = false;
            if($center_office['freebies_manual_assignment'] === true) {
                CenterOffice::id($center_office['id'])->update(['freebies_manual_assignment' => false]);
                $center_office_freebies_manual_assignment_changed = true;
            }

            $booking = Booking::create([
                'date_from'             => strtotime('2023-01-01'),
                'date_to'               => strtotime('2023-01-03'),
                'center_id'             => $center['id'],
                'type_id'               => $booking_type_id,
                'customer_nature_id'    => $customer_nature_id,
                'customer_identity_id'  => $customer_identity_id,
                'description'           => 'Booking test 0901: adult freebies does not pass cap of nb_adult.'
            ])
                ->read(['id', 'date_from', 'date_to', 'price'])
                ->first(true);

            $pack_secondary_sojourn = Product::search(['sku', '=', 'GA-SejScoSec-A'])
                ->read(['id', 'label'])
                ->first(true);

            $group = BookingLineGroup::create([
                'booking_id'        => $booking['id'],
                'is_sojourn'        => true,
                'group_type'        => 'sojourn',
                'has_pack'          => true,
                'pack_id'           => $pack_secondary_sojourn['id'],
                'rate_class_id'     => $rate_class_id,
                'sojourn_type_id'   => $sojourn_type_id
            ])
                ->update([
                    'date_from' => $booking['date_from'],
                    'date_to'   => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'   => 55
                ])
                ->read(['id'])
                ->first(true);

            $secondary_age_range_id = 2;
            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update([
                    'age_range_id'  => $secondary_age_range_id,
                    'qty'           => 52
                ]);

            $adult_age_range_id = 1;
            BookingLineGroupAgeRangeAssignment::create([
                'booking_id'            => $booking['id'],
                'booking_line_group_id' => $group['id'],
                'age_range_id'          => $adult_age_range_id,
                'qty'                   => 3,
                'is_active'             => true
            ]);

            Booking::id($booking['id'])
                ->update([
                    'date_from' => strtotime('2023-01-01'),
                    'date_to'   => strtotime('2023-01-04')
                ]);

            BookingLineGroup::id($group['id'])
                ->update([
                    'date_from' => strtotime('2023-01-01'),
                    'date_to'   => strtotime('2023-01-04')
                ]);

            // Rollback freebies_manual_assignment if needed
            if($center_office_freebies_manual_assignment_changed) {
                CenterOffice::id($center_office['id'])
                    ->update(['freebies_manual_assignment' => true]);
            }

            return BookingLine::search(['booking_id', '=', $booking['id']])
                ->read(['id', 'qty', 'free_qty', 'product_id' => ['age_range_id']])
                ->get(true);
        },
        'assert'        => function($booking_lines) {
            $adult_product_lines = [];
            $secondary_child_product_lines = [];

            $adult_age_range_id = 1;
            $secondary_age_range_id = 2;
            foreach($booking_lines as $line) {
                if($line['product_id']['age_range_id'] === $adult_age_range_id) {
                    $adult_product_lines[] = $line;
                }
                elseif($line['product_id']['age_range_id'] === $secondary_age_range_id) {
                    $secondary_child_product_lines[] = $line;
                }
            }

            if(count($adult_product_lines) !== 4 || count($secondary_child_product_lines) !== 4) {
                return false;
            }

            $nb_adults = 3;
            $nb_nights = 3;
            foreach($adult_product_lines as $line) {
                if($line['qty'] !== ($nb_adults * $nb_nights) && $line['free_qty'] !== $line['qty']) {
                    return false;
                }
            }

            foreach($secondary_child_product_lines as $line) {
                if($line['free_qty'] !== 0) {
                    return false;
                }
            }

            return true;
        },
        'rollback'      => function() {
            /*
            Booking::search(['description', '=', 'Booking test 0901: adult freebies does not pass cap of nb_adult.'])
                ->delete(true);
            */
        }
    ],
    '0902' => [
        'description'   => 'Test that the sojourn tax (KA-CTaxSej-A) is not automaticaly sold when a scholar sojourn pack is used.',
        'arrange'       => function() {
            return [
                Center::search(['name', 'like', '%Louvain-la-Neuve%'])->read(['id'])->first(true)['id'],
                BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true)['id'],
                CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true)['id'],
                Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true)['id'],
                SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true)['id'],
                RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true)['id']
            ];
        },
        'act'           => function($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id) = $data;

            $booking = Booking::create([
                'date_from'             => strtotime('2023-01-01'),
                'date_to'               => strtotime('2023-01-03'),
                'center_id'             => $center_id,
                'type_id'               => $booking_type_id,
                'customer_nature_id'    => $customer_nature_id,
                'customer_identity_id'  => $customer_identity_id,
                'description'           => 'Booking test 0902: sojourn tax not automatically added to scholar sojourn.'
            ])
                ->read(['id', 'date_from', 'date_to', 'price'])
                ->first(true);

            $pack_secondary_sojourn = Product::search(['sku', '=', 'GA-SejScoSec-A'])
                ->read(['id', 'label'])
                ->first(true);

            BookingLineGroup::create([
                'booking_id'        => $booking['id'],
                'is_sojourn'        => true,
                'group_type'        => 'sojourn',
                'has_pack'          => true,
                'pack_id'           => $pack_secondary_sojourn['id'],
                'rate_class_id'     => $rate_class_id,
                'sojourn_type_id'   => $sojourn_type_id
            ])
                ->update([
                    'date_from' => $booking['date_from'],
                    'date_to'   => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'   => 20
                ])
                ->read(['id'])
                ->first(true);

            $secondary_age_range_id = 2;
            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update(['age_range_id' => $secondary_age_range_id]);

            Booking::id($booking['id'])
                ->update(['nb_pers' => 15]);

            return BookingLine::search(['booking_id', '=', $booking['id']])
                ->read(['id', 'product_id' => ['sku']])
                ->get(true);
        },
        'assert'        => function($booking_lines) {
            $has_sojourn_tax = false;
            foreach($booking_lines as $line) {
                if($line['sku'] === 'KA-CTaxSej-A') {
                    $has_sojourn_tax = true;
                    break;
                }
            }

            return count($booking_lines) > 0 && $has_sojourn_tax === false;
        },
        'rollback'      => function() {
            Booking::search(['description', '=', 'Booking test 0902: sojourn tax not automatically added to scholar sojourn.'])
                ->delete(true);
        }
    ]
];
