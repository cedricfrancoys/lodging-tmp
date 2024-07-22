<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2024
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\identity\Identity;
use lodging\sale\booking\Booking;
use lodging\sale\booking\SojournType;
use sale\customer\CustomerNature;
use sale\customer\RateClass;
use sale\booking\BookingType;


$tests = [
    '0301' => [
        'description'       => 'Validate that the reservation can be canceled from a reservation in quote status.',
        'help'              => "
            action: lodging_booking_do-cancel
            Center: Villers-Sainte-Gertrude
            Dates from: 12/03/2023
            Dates to: 13/03/2023
            Nights: 1 nights",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T4'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id'], $rate_class['id']];
        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-12'),
                    'date_to'               => strtotime('2023-03-13'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be canceled from a reservation in quote status.'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-cancel', ['id' => $booking['id'], 'reason' => 'other']);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $booking['id'];

        },
        'assert'            =>  function ($booking_id) {

            $booking = Booking::id($booking_id)
                ->read(['id','status','is_cancelled'])
                ->first(true);

            return ($booking['status'] == 'quote' && $booking['is_cancelled'] == true );

        },
        'rollback'          =>  function () {
          Booking::search(['description', 'like', '%'. 'Validate that the reservation can be canceled from a reservation in quote status'.'%' ])
                   ->delete(true);
        }

    ],
    '0302' => [
        'description'       => 'Validate that the reservation cannot be canceled if the reason is missing',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-14'),
                    'date_to'               => strtotime('2023-03-15'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be canceled if the reason is missing.'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-cancel', ['id' => $booking['id']]);

            }
            catch (Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },
        'assert'            =>  function ($message) {

            return ($message == 'reason');
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the reservation cannot be canceled if the reason is missing'.'%' ])
                    ->delete(true);
        }

    ],
    '0303' => [
        'description'       => 'Validate that the reservation cannot be canceled if the reservation has been canceled before',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-16'),
                    'date_to'               => strtotime('2023-03-17'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be canceled if the reservation has been canceled before.'
                ])
                ->update(['is_cancelled' => true])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-cancel', ['id' => $booking['id'],  'reason' => 'other']);

            } catch (Exception $e) {
                $message = $e->getMessage();

            }

            return $message;
        },
        'assert'            =>  function ($message) {

            return ($message == "incompatible_status");
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'ilike', '%'. 'Validate that the reservation cannot be canceled if the reservation has been canceled before'.'%' ])
                    ->delete(true);
        }

    ],
    '0304' => [
        'description'       => 'Validate that the reservation can be canceled from a reservation in option status.',
        'help'              => "
            Center: Villers-Sainte-Gertrude
            Dates from: 18/03/2023
            Dates to: 19/03/2023
            Nights: 1 nights",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T4'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-18'),
                    'date_to'               => strtotime('2023-03-19'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be canceled from a reservation in option status'
                ])
                ->update(['status' => 'option'])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-cancel', ['id' => $booking['id'], 'reason' => 'other']);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $booking['id'];

        },
        'assert'            =>  function ($booking_id) {

            $booking = Booking::id($booking_id)
                ->read(['id','status','is_cancelled', 'cancellation_reason'])
                ->first(true);

            return (
                $booking['status'] == 'checkedout' &&
                $booking['is_cancelled'] == true &&
                $booking['cancellation_reason'] == 'other'
            );

        },
        'rollback'          =>  function () {
          $booking = Booking::search(['description', 'ilike', '%'. 'Validate that the reservation can be canceled from a reservation in option status'.'%' ])
                  ->update(['status' => 'quote'])
                  ->read(['id'])
                  ->first(true);
            Booking::id($booking['id'])->delete(true);
        }

    ],

    '0305' => [

        'description' => "Validate that the reservation can be canceled from a reservation in balanced status.",

        'arrange' =>  function () {
            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T4'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id'], $rate_class['id']];
        },

        'act' =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-19'),
                    'date_to'               => strtotime('2023-03-20'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be canceled from a reservation in balanced status'
                ])
                ->update(['status' => 'balanced'])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-cancel', ['id' => $booking['id'], 'reason' => 'other']);
            }
            catch (Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },

        'assert' =>  function ($message) {
            return ($message == "incompatible_status");
        },

        'rollback' =>  function () {
            $booking = Booking::search(['description', 'ilike', '%'. 'Validate that the reservation can be canceled from a reservation in balanced status'.'%' ])
                ->read(['id'])
                ->first(true);

            $services = eQual::inject(['orm']);
            $services['orm']->delete(Booking::getType(), $booking['id'], true);
        }
    ]
];