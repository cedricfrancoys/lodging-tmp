<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2024
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\identity\Center;
use lodging\identity\Identity;
use lodging\sale\booking\Booking;
use sale\customer\CustomerNature;
use sale\booking\BookingType;


$tests = [
    '0801' => [
        'description'       => 'Validate that the reservation cannot be deleted if the user is not connected.',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Villers-Sainte-Gertrude%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

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
                    'description'           => 'Validate that the reservation cannot be deleted if the user is not connected.'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'lodging_booking_do-delete', ['id' => $booking['id']]);

            }
            catch (Exception $e) {
                $code = $e->getCode();
            }

            return $code;
        },
        'assert'            =>  function ($code) {

            return ($code == -4);
        },
        'rollback'=>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the reservation cannot be deleted if the user is not connected'.'%' ])
                    ->delete(true);
        }

    ]
];