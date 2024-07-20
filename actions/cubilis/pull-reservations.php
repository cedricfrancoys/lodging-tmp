<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Lang;
use core\Mail;
use equal\email\Email;
use lodging\sale\booking\BookingType;
use lodging\sale\booking\Contact;
use lodging\sale\booking\channelmanager\Identity;
use lodging\sale\booking\channelmanager\Booking;
use lodging\sale\booking\channelmanager\BookingLine;
use lodging\sale\booking\channelmanager\BookingLineGroup;
use lodging\sale\booking\channelmanager\BookingLineGroupAgeRangeAssignment;
use lodging\sale\booking\channelmanager\ExtraService;
use lodging\sale\booking\channelmanager\Funding;
use lodging\sale\booking\channelmanager\Property;
use lodging\sale\booking\Consumption;
use lodging\sale\booking\Contract;
use lodging\sale\booking\channelmanager\Payment;
use lodging\sale\booking\Guarantee;
use lodging\sale\booking\SojournProductModel;
use lodging\sale\booking\SojournProductModelRentalUnitAssignement;
use lodging\sale\catalog\Product;
use lodging\sale\price\PriceList;
use sale\price\Price;

list($params, $providers) = eQual::announce([
    'description'   => "Pull reservations from Cubilis not yet marked as acknowledged.",
    'params'        => [
    ],
    'access' => [
        'visibility'    => 'protected',
    ],
    'constants'     => ['ROOT_APP_URL', 'EMAIL_REPORT_RECIPIENT', 'EMAIL_ERRORS_RECIPIENT'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\cron\Scheduler       $cron
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
list($context, $orm, $cron, $dispatch) = [ $providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch'] ];

// #memo - temporary solution to prevent calls from non-production server
if(constant('ROOT_APP_URL') != 'https://discope.yb.run') {
    throw new Exception('wrong_host', QN_ERROR_INVALID_CONFIG);
}

$result = [
    'errors'                => 0,
    'warnings'              => 0,
    'logs'                  => [],
    // associative array mapping center offices ids with related details and logs
    'center_offices'        => [],
    'processed_properties'  => '',
    'created_bookings'      => '',
    'updated_bookings'      => '',
    'cancelled_bookings'    => '',
];

// map for queuing reservations successfully synched from Cubilis (for sending ack requests)
$map_property_ack_reservations = [];

try {
    $properties = Property::search(['is_active', '=', true])
        ->read([
            'id', 'extref_property_id',
            'center_id' => [ 'id', 'name', 'price_list_category_id'],
            'center_office_id' => ['id', 'name', 'email', 'organisation_id'],
            'username',
            'password'
        ])
        ->get(true);

    if(!count($properties)) {
        ++$result['errors'];
        $result['logs'][] = "ERR - No property found in Discope for syncing with Cubilis.";
        throw new Exception('no_property_defined', QN_ERROR_INVALID_CONFIG);
    }

    foreach($properties as $property) {
        if(!strlen($property['username']) || !strlen($property['password'])) {
            $result['logs'][] = "INFO- Missing credential(s) for property {$property['extref_property_id']}: skipping property.";
            continue;
        }
        $result['processed_properties'] .= "{$property['center_id']['name']} [{$property['extref_property_id']}],";
        $count_attempts = 0;
        $flag_success = false;
        while(!$flag_success) {
            try {
                $data = eQual::run('get', 'lodging_cubilis_reservations', ['property_id' => $property['extref_property_id']]);
                $flag_success = true;
            }
            catch(Exception $e) {
                ++$count_attempts;
            }
            if(!$flag_success && $count_attempts >= 3) {
                ++$result['errors'];
                $result['logs'][] = "ERR - Property {$property['extref_property_id']} : Unable to connect to Cubilis server (retry scheduled).";
                throw new Exception('cubilis_unreachable', QN_ERROR_UNKNOWN);
            }
        }

        // init result map for property center office, if necessary
        if(!isset($result['center_offices'][$property['center_office_id']['id']])) {
            $result['center_offices'][$property['center_office_id']['id']] = [
                'email' => $property['center_office_id']['email'],
                'name'  => $property['center_office_id']['name'],
                'logs'  => []
            ];
        }

        foreach($data as $property_id => $reservations) {

            // discard reservations not matching the property being looked up (there shouldn't be any)
            if($property_id != $property['extref_property_id']) {
                continue;
            }

            foreach($reservations as $reservation) {

                // #memo - in case of change (customer address, new payment, ...), the reservation is put back in the queue with a status 'Reserved' or 'Modify' (both situations were observed)
                // #memo - in case of cancellation, the reservation is put in the queue with a status 'Cancelled' (services are no longer present)
                try {
                    $is_new_booking = false;
                    $has_overbooking = false;
                    // this will hold the total amount paid by the customer, in case some payments are given
                    $total_paid = 0;

                    // attempt to retrieve the booking relating to the reservation, if any
                    $booking = Booking::search(['extref_reservation_id', '=', $reservation['reservation_id']])
                        ->read(['id', 'name','status', 'customer_id', 'customer_identity_id'])
                        ->first(true);

                    // try to sync the reservation

                    if($reservation['status'] == 'Request Denied') {
                        $reservation['status'] = 'Cancelled';
                    }

                    // handle booking cancellation
                    if($reservation['status'] == 'Cancelled') {
                        if(!$booking) {
                            // throw new Exception('unknown_reservation_cancellation', QN_ERROR_UNKNOWN_OBJECT);
                            ++$result['warnings'];
                            $result['logs'][] = "WARN- Unable to handle request for cancelling unknown reservation {$reservation['reservation_id']}.";
                        }
                        else {
                            try {
                                // cancel booking
                                // #memo - this will trigger `check-contingencies`
                                eQual::run('do', 'lodging_booking_do-cancel', [
                                        'id'        => $booking['id'],
                                        'reason'    => 'ota'
                                    ]);
                            }
                            catch(Exception $e) {
                                // error while cancelling (unable to cancel)
                                ++$result['warnings'];
                                $result['logs'][] = "WARN- Unable to cancel Booking {$booking['id']} for reservation {$reservation['reservation_id']} : ".$e->getMessage();
                            }
                        }
                    }
                    // handle creation or modification
                    else {
                        // #memo - it seems Cubilis uses UTC notation but all times are Europe/Brussels
                        // start and end apply to all sojourns
                        $date_from = strtotime($reservation['start']);
                        if($date_from - strtotime('midnight', $date_from) <= 0) {
                            // if missing or set to 0, start time is set to 15:00 (which is the first available time in Cubilis Logis)
                            $date_from += (15 * 3600);
                        }
                        // add checkout default time + TZ offset (use 10:00 AM as default checkout time)
                        $date_to = strtotime($reservation['end']) + (10 * 3600);
                        $nb_nights = ceil( ($date_to - $date_from) / 86400 );
                        $booking_price = $reservation['total']['amount'];

                        // this is an update (resulting booking already present)
                        if($booking) {
                            // checkin has already been made for booking - skip
                            if(!in_array($booking['status'], ['confirmed', 'validated'])){
                                ++$result['warnings'];
                                $result['logs'][] = "WARN- Incompatible reservation status [{$booking['id']} - {$booking['name']}] (Cubilis reservation {$reservation['reservation_id']})";
                                $result['center_offices'][$property['center_office_id']['id']]['logs'][] = 'Réservation en cours : Le client a effectué une modification dans la réservation  de Channel Manager et la mise à jour a été ignorée par Discope pour la <a href="https://discope.yb.run/booking/#/booking/'.$booking['id'].'"> réservation '.$booking['name'].'</a>. Veuillez vérifier les modifications dans Cubilis et effectuer les ajustements manuellement dans Discope.';

                                $dispatch->cancel('lodging.booking.chanelmanager.update.ignored', 'lodging\sale\booking\Booking', $booking['id']);
                                $dispatch->dispatch('lodging.booking.chanelmanager.update.ignored', 'lodging\sale\booking\Booking', $booking['id'], 'important', null, [], [], null, $property['center_office_id']['id']);

                                // do not make any change, but queue reservation for acknowledgement
                                if(!isset($map_property_ack_reservations[$property['extref_property_id']])) {
                                    $map_property_ack_reservations[$property['extref_property_id']] = [];
                                }
                                $map_property_ack_reservations[$property['extref_property_id']][] = $reservation['reservation_id'];
                                continue;
                            }
                            else {
                                // set the booking back to quote (should be 'confirmed') - this should remove all existing consumptions
                                Booking::id($booking['id'])->update(['status' => 'quote']);
                                // void contract (but keep it)
                                Contract::search(['booking_id', '=', $booking['id']])->update(['status' => 'cancelled']);
                                // remove all services
                                BookingLine::search(['booking_id', '=', $booking['id']])->delete(true);
                                BookingLineGroup::search(['booking_id', '=', $booking['id']])->delete(true);

                                // remove all contacts
                                Contact::search(['booking_id', '=', $booking['id']])->delete(true);

                                // remove all consumptions
                                Consumption::search(['booking_id', '=', $booking['id']])->delete(true);

                                // remove only payments previously received from Cubilis (do not remove payments made directly in Discope)
                                $payments_ids_to_delete = Payment::search(['booking_id', '=', $booking['id']],['payment_origin', '=', 'online'])->ids();
                                Funding::search(['payments_ids' , 'contains' , $payments_ids_to_delete])->delete(true);
                                Payment::ids($payments_ids_to_delete)->delete(true);


                                $language = Lang::search(['code', '=', $reservation['customer']['lang']]) ->read(['id']) ->first(true);
                                // The ID 2 is for French
                                $lang_id = $language['id'] ?? 2;

                                // update customer
                                Identity::id($booking['customer_identity_id'])->update([
                                        'firstname'         => $reservation['customer']['firstname'],
                                        'lastname'          => $reservation['customer']['lastname'],
                                        'address_street'    => $reservation['customer']['address_street'],
                                        'address_city'      => $reservation['customer']['address_city'],
                                        'address_zip'       => $reservation['customer']['address_zip'],
                                        'address_country'   => $reservation['customer']['address_country'],
                                        'phone'             => $reservation['customer']['phone'],
                                        'email'             => $reservation['customer']['email'],
                                        'lang_id'           => $lang_id
                                    ]);

                                // update additional values that might have changed
                                Booking::id($booking['id'])->update([
                                    'description'           => $reservation['comments'],
                                    'price'                 => $booking_price,
                                    'date_from'             => $date_from,
                                    'date_to'               => $date_to
                                ]);
                            }
                        }
                        else {
                            $is_new_booking = true;

                            // this is a new booking

                            // try to resolve customer : retrieve a customer according to a partial identity
                            try {
                                $customer = eQual::run('get', 'lodging_cubilis_retrieve-customer', [
                                        'firstname'         => $reservation['customer']['firstname'],
                                        'lastname'          => $reservation['customer']['lastname'],
                                        'address_street'    => $reservation['customer']['address_street'],
                                        'address_city'      => $reservation['customer']['address_city'],
                                        'address_zip'       => $reservation['customer']['address_zip'],
                                        'address_country'   => $reservation['customer']['address_country'],
                                        'phone'             => $reservation['customer']['phone'],
                                        'email'             => $reservation['customer']['email'],
                                        'lang'              => $reservation['customer']['lang']
                                    ]);
                                if(!isset($customer['id']) || !isset($customer['customer_identity_id'])) {
                                    throw new Exception('invalid_customer', QN_ERROR_UNKNOWN);
                                }
                            }
                            catch(Exception $e) {
                                throw new Exception('failed_customer_resolution'.' : '.$e->getMessage(), QN_ERROR_UNKNOWN);
                            }

                            // #memo - in this controller, groups and lines creations do not trigger the update of the booking type_id, so we have to assign it manually
                            $booking_type = BookingType::search(['code', '=', 'OTA'])
                                ->read(['id'])
                                ->first(true);

                            if(!$booking_type) {
                                throw new Exception('missing_ota_booking_type', QN_ERROR_INVALID_CONFIG);
                            }

                            // 1) create booking

                            $booking = Booking::create([
                                    'customer_id'           => $customer['id'],
                                    'customer_identity_id'  => $customer['customer_identity_id'],
                                    // individuals
                                    'customer_nature_id'    => 30,
                                    'center_id'             => $property['center_id']['id'],
                                    'center_office_id'      => $property['center_office_id']['id'],
                                    'organisation_id'       => $property['center_office_id']['organisation_id'],
                                    'extref_reservation_id' => $reservation['reservation_id'],
                                    'description'           => $reservation['comments'],
                                    'price'                 => $booking_price,
                                    'date_from'             => $date_from,
                                    'date_to'               => $date_to,
                                    'is_from_channelmanager'=> true,
                                    'type_id'               => $booking_type['id']
                                ])
                                ->read(['id', 'name', 'customer_id', 'customer_identity_id'])
                                ->first(true);

                            if(!$booking) {
                                throw new Exception('failed_ota_booking_creation', QN_ERROR_UNKNOWN);
                            }

                            if(strlen($reservation['partner']) > 0) {
                                $values = [
                                        'has_tour_operator' => true,
                                        'tour_operator_ref' => '(Cubilis)'
                                    ];
                                // booking.com
                                if($reservation['partner_id'] == 4) {
                                    $values['tour_operator_id'] = 24594;
                                }
                                // expedia
                                elseif($reservation['partner_id'] == 5) {
                                    $values['tour_operator_id'] = 18717;
                                }
                                Booking::id($booking['id'])->update($values);
                            }
                        }

                        // 2) create sojourns booking_line_groups

                        // we create as much BookingLineGroup objects as there are roomstays (we assume dates are always the same)
                        try {
                            $map_assigned_rental_units_ids = [];
                            foreach($reservation['room_stays'] as $room_stay) {

                                if(isset($room_stay['status']) && $room_stay['status'] == 'Cancelled') {
                                    // ignore cancelled sojourns
                                    continue;
                                }

                                $nb_pers = $room_stay['guests']['total'];

                                // check if a room for that room type is still available for the given dates
                                try {
                                    $found = false;
                                    $rental_units = eQual::run('get', 'lodging_cubilis_retrieve-rentalunit', [
                                            'property_id'           => $property['id'],
                                            'extref_room_type_id'   => $room_stay['room_type']['id'],
                                            'date_from'             => date('c', $date_from),
                                            'date_to'               => date('c', $date_to)
                                        ]);
                                    foreach($rental_units as $rental_unit) {
                                        if(!isset($map_assigned_rental_units_ids[$rental_unit['id']]) && $rental_unit['capacity'] >= $nb_pers) {
                                            $map_assigned_rental_units_ids[$rental_unit['id']] = true;
                                            $found = true;
                                            break;
                                        }
                                    }
                                    if(!$found) {
                                        throw new Exception('no_rentalunit_available', QN_ERROR_INVALID_CONFIG);
                                    }
                                }
                                catch(Exception $e) {
                                    // failed rentalunit resolution: emit a warning email
                                    $rental_unit = null;
                                    // #memo - we don't set the result log here because booking creation might still be aborted
                                    $has_overbooking = true;
                                }

                                // we need to make sure a pack and a price exists for the period related to the booking
                                $group_product = Product::search([['sku', '=', 'SEJ_OTA']])
                                    ->read(['id', 'product_model_id'])
                                    ->first(true);

                                if(!$group_product) {
                                    throw new Exception('missing_ota_sojourn_product', QN_ERROR_INVALID_CONFIG);
                                }

                                // retrieve the Price List that matches the criteria from the booking with the shortest duration
                                $price_lists_ids = PriceList::search([
                                            [
                                                ['price_list_category_id', '=', $property['center_id']['price_list_category_id']],
                                                ['date_from', '<=', strtotime('midnight', $date_from)],
                                                ['date_to', '>=', strtotime('midnight', $date_to)],
                                                ['status', 'in', ['pending', 'published']]
                                            ],
                                            // #memo - it seems each Center has a category of its own for SEJ_OTA
                                            /*
                                            [
                                                ['price_list_category_id', '=', 9],
                                                ['date_from', '<=', strtotime('midnight', $date_from)],
                                                ['date_to', '>=', strtotime('midnight', $date_to)],
                                                ['status', 'in', ['pending', 'published']]
                                            ]
                                            */
                                        ],
                                        [ 'sort'  => ['duration' => 'asc'] ]
                                    )
                                    ->limit(1)
                                    ->ids();

                                // price should be 0, we're only interested in VAT rate (there should be only one matching price)
                                $price = Price::search([['product_id', '=', $group_product['id']], ['price_list_id', 'in', $price_lists_ids]])
                                    ->read(['id', 'vat_rate'])
                                    ->first(true);

                                if(!$price) {
                                    throw new Exception('missing_ota_price', QN_ERROR_INVALID_CONFIG);
                                }

                                $resulting_price = round($room_stay['total']['amount'], 2);
                                $resulting_total = round($resulting_price/(1+$price['vat_rate']), 4);

                                $booking_line_group = BookingLineGroup::create([
                                        'name'          => "Séjour {$nb_pers} personne(s) {$reservation['partner']}",
                                        'is_sojourn'    => true,
                                        'group_type'    => 'sojourn',
                                        'has_pack'      => true,
                                        'pack_id'       => $group_product['id'],
                                        'is_locked'     => true,
                                        'date_from'     => strtotime('midnight', $date_from),
                                        'date_to'       => strtotime('midnight', $date_to),
                                        'time_from'     => $date_from - strtotime('midnight', $date_from),
                                        'time_to'       => $date_to - strtotime('midnight', $date_to),
                                        'nb_pers'       => $nb_pers,
                                        'booking_id'    => $booking['id'],
                                        'price_id'      => $price['id'],
                                        'unit_price'    => $resulting_total,
                                        'price'         => $resulting_price,
                                        // total depends on the VAT rate of the matching price_id (VAT excl)
                                        'total'         => $resulting_total
                                    ])
                                    ->read(['id'])
                                    ->first(true);

                                if(!$booking_line_group) {
                                    throw new Exception('failed_ota_sojourn_creation', QN_ERROR_UNKNOWN);
                                }

                                // create the age range assignments
                                if(isset($room_stay['guests']['adults'])) {
                                    BookingLineGroupAgeRangeAssignment::create([
                                        'booking_id'                => $booking['id'],
                                        'booking_line_group_id'     => $booking_line_group['id'],
                                        'qty'                       => $room_stay['guests']['adults'],
                                        'age_range_id'              => 1
                                    ]);
                                }
                                if(isset($room_stay['guests']['children'])) {
                                    BookingLineGroupAgeRangeAssignment::create([
                                        'booking_id'                => $booking['id'],
                                        'booking_line_group_id'     => $booking_line_group['id'],
                                        'qty'                       => $room_stay['guests']['children'],
                                        'age_range_id'              => 3
                                    ]);
                                }
                                if(isset($room_stay['guests']['babies'])) {
                                    BookingLineGroupAgeRangeAssignment::create([
                                        'booking_id'                => $booking['id'],
                                        'booking_line_group_id'     => $booking_line_group['id'],
                                        'qty'                       => $room_stay['guests']['babies'],
                                        'age_range_id'              => 5
                                    ]);
                                }

                                // create booking_lines

                                // add a line for the night stay, according to the rental unit
                                $line_product = Product::search([['sku', '=', 'NUIT_OTA']])
                                    ->read(['id', 'product_model_id'])
                                    ->first(true);

                                if(!$line_product) {
                                    throw new Exception('missing_ota_night_product', QN_ERROR_INVALID_CONFIG);
                                }

                                // #memo - bookings from OTA should always use nights with products accounted "by accommodation".

                                BookingLine::create([
                                        'booking_id'            => $booking['id'],
                                        'booking_line_group_id' => $booking_line_group['id'],
                                        'qty'                   => count($room_stay['rate_plans']),
                                        // #memo - we don't need the price_id here : only the group will generate an invoice line
                                        'product_id'            => $line_product['id'],
                                        'product_model_id'      => $line_product['product_model_id'],
                                        'description'           => (isset($room_stay['room_type']['name']))?$room_stay['room_type']['name']:''
                                    ]);

                                // create SPM
                                $spm = SojournProductModel::create([
                                        'booking_id'            => $booking['id'],
                                        'booking_line_group_id' => $booking_line_group['id'],
                                        'product_model_id'      => $line_product['product_model_id']
                                    ])
                                    ->read(['id'])
                                    ->first(true);

                                // create assignments
                                if($rental_unit) {
                                    SojournProductModelRentalUnitAssignement::create([
                                            'booking_id'                => $booking['id'],
                                            'booking_line_group_id'     => $booking_line_group['id'],
                                            'sojourn_product_model_id'  => $spm['id'],
                                            'rental_unit_id'            => $rental_unit['id'],
                                            'qty'                       => $rental_unit['capacity']
                                        ]);
                                }

                                // add a line for breakfast (always included)
                                $line_product = Product::search([['sku', '=', 'GA-PtDej-A']])
                                    ->read(['id', 'product_model_id'])
                                    ->first(true);

                                if(!$line_product) {
                                    throw new Exception('missing_breakfast_product', QN_ERROR_INVALID_CONFIG);
                                }

                                BookingLine::create([
                                        'booking_id'            => $booking['id'],
                                        'booking_line_group_id' => $booking_line_group['id'],
                                        'qty'                   => count($room_stay['rate_plans']) * $room_stay['guests']['total'],
                                        'product_id'            => $line_product['id'],
                                        'product_model_id'      => $line_product['product_model_id']
                                    ]);

                            }
                        }
                        catch(Exception $e) {
                            throw new Exception('failed_sojourn_creation:'.$e->getMessage(), QN_ERROR_UNKNOWN);
                        }

                        // 3) add extra services
                        $city_tax_found = false;

                        if(isset($reservation['extra_services']) && count($reservation['extra_services'])) {

                            // create a new group for extra services
                            $extra_booking_line_group = BookingLineGroup::create([
                                    'name'          => "Services extra {$reservation['partner']}",
                                    'is_sojourn'    => false,
                                    'is_event'      => false,
                                    'has_pack'      => false,
                                    'is_locked'     => false,
                                    'nb_pers'       => 1,
                                    'booking_id'    => $booking['id']
                                ])
                                ->read(['id'])
                                ->first(true);

                            foreach($reservation['extra_services'] as $extra_service) {
                                try {
                                    // search amongst existing extra services for current property
                                    $service = ExtraService::search([['extref_inventory_code', '=', $extra_service['inventory_code']], ['property_id', '=', $property['id']]])
                                        ->read(['id', 'product_model_id'])
                                        ->first(true);

                                    if(!$service) {
                                        throw new Exception('unknown_extra_service', QN_ERROR_UNKNOWN_OBJECT);
                                    }

                                    $products_ids = Product::search([['product_model_id', '=', $service['product_model_id']], ['can_sell', '=', true]])->ids();

                                    // retrieve a product matching the service for the current property
                                    $price_lists_ids = PriceList::search([
                                                [
                                                    ['price_list_category_id', '=', $property['center_id']['price_list_category_id']],
                                                    ['date_from', '<=', $date_from],
                                                    ['date_to', '>=', $date_to],
                                                    ['status', 'in', ['pending', 'published']]
                                                ]
                                            ],
                                            ['sort' => ['duration' => 'asc']]
                                        )
                                        ->limit(1)
                                        ->ids();

                                    // retrieve a price_id for the product_model
                                    // #memo - we're only interested in VAT rate
                                    $price = Price::search(['product_id', 'in', $products_ids], ['price_list_id', 'in', $price_lists_ids])
                                        ->read(['id', 'product_id', 'vat_rate'])
                                        ->first(true);

                                    if(!$price) {
                                        throw new Exception('missing_ota_price', QN_ERROR_INVALID_CONFIG);
                                    }

                                    $resulting_price = round(floatval($extra_service['total']['amount']), 2);
                                    $resulting_total = round($resulting_price/(1+$price['vat_rate']), 2);

                                    // add a new line relating to the extra service
                                    // #memo - we have to do this because we do not receive VAT excl price
                                    $booking_line = BookingLine::create([
                                            'booking_id'            => $booking['id'],
                                            'description'           => $extra_service['comments'],
                                            'booking_line_group_id' => $extra_booking_line_group['id'],
                                            'qty'                   => 1,
                                            'price_id'              => $price['id'],
                                            'product_id'            => $price['product_id'],
                                            'product_model_id'      => $service['product_model_id']
                                        ])
                                        ->update([
                                            'unit_price'            => $resulting_total,
                                            'has_manual_unit_price' => true,
                                            'price'                 => $resulting_price,
                                            'total'                 => $resulting_total
                                        ])
                                        ->read(['id', 'product_id' => ['id', 'sku']])
                                        ->first(true);

                                    if ($booking_line['product_id']['sku'] == 'KA-CTaxSej-A'){
                                        $city_tax_found = true;
                                    }
                                }
                                catch(Exception $e) {
                                    throw new Exception('failed_services_creation:'.$e->getMessage(), QN_ERROR_UNKNOWN);
                                }
                            }
                        }

                        // if not present, manually add the city tax (must always be present)
                        if(!$city_tax_found) {
                            try {
                                $booking = Booking::id($booking['id'])
                                    ->read(['id', 'name', 'customer_id', 'customer_identity_id', 'nb_pers'])
                                    ->first(true);

                                $extra_booking_line_group = BookingLineGroup::create([
                                        'name'          =>  "Taxes de séjour",
                                        'is_sojourn'    => false,
                                        'is_event'      => false,
                                        'has_pack'      => false,
                                        'is_locked'     => false,
                                        'nb_pers'       => 1,
                                        'booking_id'    => $booking['id']
                                    ])
                                    ->read(['id'])
                                    ->first(true);

                                $products_ids = Product::search(['sku', '=' ,'KA-CTaxSej-A'])->ids();
                                if(empty($products_ids)) {
                                    throw new Exception('missing_city_tax_product', QN_ERROR_INVALID_CONFIG);
                                }
                                // retrieve a product matching the service for the current property
                                $price_lists_ids = PriceList::search([
                                            ['price_list_category_id', '=', $property['center_id']['price_list_category_id']],
                                            ['date_from', '<=', $date_from],
                                            ['date_to', '>=', $date_to],
                                            ['status', 'in', ['pending', 'published']]
                                        ],
                                        ['sort' => ['duration' => 'asc']]
                                    )
                                    ->limit(1)
                                    ->ids();
                                // retrieve a price_id for the product_model
                                // #memo - we're only interested in VAT rate
                                $price = Price::search(['product_id', 'in', $products_ids], ['price_list_id', 'in', $price_lists_ids])
                                    ->read(['id', 'product_id', 'price', 'vat_rate'])
                                    ->first(true);
                                if(!$price) {
                                    throw new Exception('missing_ota_price', QN_ERROR_INVALID_CONFIG);
                                }

                                $resulting_price = round($price['price'] * $booking['nb_pers']* $nb_nights, 2);
                                $resulting_total = round($resulting_price/(1+$price['vat_rate']), 2);
                                // add a new line relating to the extra service
                                // #memo - we have to do this because we do not receive VAT excl price
                                BookingLine::create([
                                        'booking_id'            => $booking['id'],
                                        'booking_line_group_id' => $extra_booking_line_group['id'],
                                        'qty'                   => 1,
                                        'price_id'              => $price['id'],
                                        'product_id'            => $price['product_id'],
                                        'product_model_id'      => $service['product_model_id']
                                    ])
                                    ->update([
                                        'unit_price'            => $resulting_total,
                                        'has_manual_unit_price' => true,
                                        'price'                 => $resulting_price,
                                        'total'                 => $resulting_total
                                    ]);
                                    $booking_price = $reservation['total']['amount'] + $resulting_total;
                                    Booking::id($booking['id'])->update(['price' => $booking_price]);
                            }
                            catch(Exception $e) {
                                throw new Exception('Extra_failed_services_creation:'.$e->getMessage(), QN_ERROR_UNKNOWN);
                            }
                        }

                        // 4) add payments and fundings

                        if(isset($reservation['payments']) && count($reservation['payments'])) {

                            // create a funding for the whole booking price
                            // #memo - the due_date is not relevant since the payment has already been received
                            $funding = Funding::create([
                                    'booking_id'        => $booking['id'],
                                    'due_amount'        => $booking_price,
                                    'due_date'          => strtotime($reservation['start']),
                                    'center_office_id'  => $property['center_office_id']['id'],
                                    'description'       => 'Paiement en ligne'
                                ])
                                ->read(['id'])
                                ->first(true);

                            if(!$funding) {
                                throw new Exception('failed_funding_creation', QN_ERROR_UNKNOWN);
                            }

                            foreach($reservation['payments'] as $payment) {
                                $total_paid += $payment['total']['amount'];

                                $payment = Payment::create([
                                        'booking_id'        => $booking['id'],
                                        'funding_id'        => $funding['id'],
                                        'center_office_id'  => $property['center_office_id']['id'],
                                        'partner_id'        => $booking['customer_id'],
                                        'amount'            => $payment['total']['amount'],
                                        'receipt_date'      => strtotime($payment['created']),
                                        'payment_origin'    => 'online',
                                        'payment_method'    => 'bank_card',
                                        'has_psp'           => true,
                                        // #memo - 'stripe' is the only option for now
                                        'psp_type'          => 'stripe',
                                        'psp_ref'           => $payment['psp_reference']
                                    ])
                                    ->read(['id'])
                                    ->first(true);

                                if(!$payment) {
                                    // log the error
                                    ++$result['errors'];
                                    $result['logs'][] = "ERR - Payment creation failed for booking {$booking['id']} (Cubilis ID {$reservation['reservation_id']})";
                                }

                                // schedule retrieval of additional information about the payment
                                $cron->schedule(
                                        "psp.fetch.{$payment['id']}",
                                        time(),
                                        'lodging_payments_fetch-psp',
                                        [ 'id' => $payment['id'] ]
                                    );

                            }

                        }
                        // no payment : add a single funding
                        else {
                            // create a funding for the whole booking price
                            $funding = Funding::create([
                                    'booking_id'        => $booking['id'],
                                    'due_amount'        => $booking_price,
                                    'due_date'          => strtotime($reservation['start']),
                                    'center_office_id'  => $property['center_office_id']['id'],
                                    'description'       => 'Réservation'
                                ])
                                ->read(['id'])
                                ->first(true);
                        }

                        // 5) add contacts

                        // add main contact : use the customer as default contact (@see Booking class)
                        $orm->callonce(Booking::getType(), 'createContacts', $booking['id']);

                        if(count($reservation['contacts'])) {
                            $map_identities = [];
                            $map_identities[$reservation['customer']['firstname'].$reservation['customer']['lastname']] = true;

                            foreach($reservation['contacts'] as $contact) {
                                if($map_identities[$contact['firstname'].$contact['lastname']]) {
                                    continue;
                                }
                                // create identity
                                $identity = Identity::create([
                                        'firstname' => $contact['firstname'],
                                        'lastname'  => $contact['lastname'],
                                        'is_ota'    => true
                                    ])
                                    ->read(['id'])
                                    ->first(true);
                                Contact::create([
                                        'booking_id'            => $booking['id'],
                                        'owner_identity_id'     => $booking['customer_identity_id'],
                                        'partner_identity_id'   => $identity['id']
                                    ]);
                            }
                        }

                        // confirm the booking
                        try {
                            // re-create consumptions (if any, previous consumptions from option-quote reverting, will be removed)
                            $orm->callonce(Booking::getType(), 'createConsumptions', $booking['id']);
                            Booking::id($booking['id'])->update(['status' => 'option']);
                            eQual::run('do', 'lodging_booking_do-confirm', ['id' => $booking['id'], 'instant_payment' => true]);
                            // #memo - since there is a delay between 2 sync (during which availability might be impacted) we need to set back the channelmanager availabilities
                            eQual::run('do', 'lodging_booking_plan-contingencies-check', ['id' => $booking['id']]);
                        }
                        catch(Exception $e) {
                            // booking could not be confirmed : send an alert and an email but do not stop the execution
                            // manually mark the booking as confirmed
                            Booking::id($booking['id'])->update(['status' => 'confirmed']);
                            ++$result['warnings'];
                            $result['logs'][] = "WARN- Unable to confirm booking {$booking['id']} (Cubilis ID {$reservation['reservation_id']})";
                        }

                        if($total_paid == $booking_price) {
                            Booking::id($booking['id'])->update(['status' => 'validated']);
                        }

                        // mark the contract as signed / accepted (the legal side is actually handled by the OTA)
                        Contract::search(['booking_id', '=', $booking['id']], ['sort'  => ['id' => 'desc']])->limit(1)->update(['status' => 'signed']);

                        // re-generate the composition with additional contacts if any
                        if(count($reservation['contacts'])) {
                            try {
                                eQual::run('do', 'lodging_composition_generate', [
                                        'booking_id'    => $booking['id'],
                                        'data'          => array_merge([
                                                'firstname'         => $reservation['customer']['firstname'],
                                                'lastname'          => $reservation['customer']['lastname'],
                                            ],
                                            $reservation['contacts'])
                                    ]);
                            }
                            catch(Exception $e) {
                                // ignore errors at this stage
                            }
                        }

                        if($has_overbooking) {
                            ++$result['warnings'];
                            $result['logs'][] = "WARN- Overbooking for booking [{$booking['id']} - {$booking['name']}] (Cubilis reservation {$reservation['reservation_id']})";
                            $result['center_offices'][$property['center_office_id']['id']]['logs'][] = 'Surbooking : pas d\'unité locative disponible pour la <a href="https://discope.yb.run/booking/#/booking/'.$booking['id'].'/services">réservation '.$booking['name'].'</a> importée automatiquement depuis Cubilis : une assignation manuelle d\'une autre unité locative doit être faite le plus rapidement possible.';
                            // remove alert if already present
                            // #memo - if do-confirm failed, we cannot be sure if alert has been created or not
                            $dispatch->cancel('lodging.booking.consistency', 'lodging\sale\booking\Booking', $booking['id']);
                            // (re-)create alert
                            $dispatch->dispatch('lodging.booking.consistency', 'lodging\sale\booking\Booking', $booking['id'], 'important', null, [], [], null, $property['center_office_id']['id']);
                        }

                        // #memo - when booking is confirmed, a funding might be created, we don't need it
                        if(isset($reservation['payments']) && count($reservation['payments'])) {
                            // remove any funding that has no payments in it
                            $existing_fundings = Funding::search(['booking_id', '=', $booking['id']])->read(['id', 'payments_ids'])->get(true);
                            foreach($existing_fundings as $funding) {
                                if(!isset($funding['payments_ids']) || count($funding['payments_ids']) <= 0) {
                                    Funding::id($funding['id'])->delete(true);
                                }
                            }
                        }
                        else {
                            // we didn't receive any payment, we assume a CC guarantee was given online but payment will be requested upon checkin
                        }

                        // #memo - by convention, we deal only with a single guarantee
                        // #memo - as of 2024-01-15, Stardekk has disabled the relay of Payment Cards details (there should be no details or empty guarantees)
                        if(isset($reservation['guarantees']) && count($reservation['guarantees'])) {
                            // delete any previous guarantee
                            Guarantee::search(['booking_id', '=', $booking['id']])->delete(true);
                            // create a new guarantee using the first provided one
                            $item_guarantee = reset($reservation['guarantees']);
                            $guarantee = Guarantee::create(array_merge($item_guarantee, ['booking_id' => $booking['id']]))->read(['id'])->first(true);
                            Booking::id($booking['id'])->update(['guarantee_id' => $guarantee['id']]);
                        }

                        // #memo - edge case : even if it is the first time we receive the booking, it might be a cancellation (in such case we won't have the details of the services - not provided by Cubilis)
                        if($reservation['status'] == 'Cancelled') {
                            // booking is still a 'quote': cancellation is trivial
                            try {
                                // cancel booking
                                // #memo - this will not trigger `check-contingencies` since reason is set to 'ota'
                                eQual::run('do', 'lodging_booking_do-cancel', [
                                        'id'        => $booking['id'],
                                        'reason'    => 'ota'
                                    ]);
                                // void contract (but keep it)
                                Contract::search(['booking_id', '=', $booking['id']])->update(['status' => 'cancelled']);
                            }
                            catch(Exception $e) {
                                // error while cancelling (unable to cancel ?)
                                ++$result['errors'];
                                $result['logs'][] = "WARN- Unable to cancel reservation for Cubilis reservation {$reservation['reservation_id']} (property {$property['extref_property_id']}) : ".$e->getMessage();
                            }
                        }
                    }

                    // everything went well : queue reservation for acknowledgement
                    if(!isset($map_property_ack_reservations[$property['extref_property_id']])) {
                        $map_property_ack_reservations[$property['extref_property_id']] = [];
                    }
                    $map_property_ack_reservations[$property['extref_property_id']][] = $reservation['reservation_id'];

                    if($reservation['status'] == 'Cancelled') {
                        $result['cancelled_bookings'] .= "{$booking['name']} [{$reservation['reservation_id']}],";
                    }
                    else {
                        if($is_new_booking) {
                            $result['created_bookings'] .= "{$booking['name']} [{$reservation['reservation_id']}],";
                        }
                        else {
                            $result['updated_bookings'] .= "{$booking['name']} [{$reservation['reservation_id']}],";
                        }
                    }
                }
                catch(Exception $e) {
                    // rollback : remove all elements relating to booking (only in case of new booking)
                    if($is_new_booking && $booking && isset($booking['id'])) {
                        SojournProductModelRentalUnitAssignement::search(['booking_id', '=', $booking['id']])->delete(true);
                        Consumption::search(['booking_id', '=', $booking['id']])->delete(true);
                        SojournProductModel::search(['booking_id', '=', $booking['id']])->delete(true);
                        BookingLine::search(['booking_id', '=', $booking['id']])->delete(true);
                        BookingLineGroup::search(['booking_id', '=', $booking['id']])->delete(true);
                        Booking::id($booking['id'])->update(['status' => 'quote'])->delete(true);
                    }
                    // notify that sync for reservation failed
                    ++$result['errors'];
                    $result['logs'][] = "ERR - Booking creation failed for Cubilis reservation {$reservation['reservation_id']} (property {$property['extref_property_id']}) : ".$e->getMessage();
                }
            }
        }
    }
}
catch(Exception $e) {
    ++$result['errors'];
    $report['logs'][] = "ERR - ".$e->getMessage();
}

// send acknowledgements to Cubilis for successfully imported reservations
foreach($map_property_ack_reservations as $extref_property_id => $reservations_ids) {
    if(empty($reservations_ids)) {
        continue;
    }
    try {
        eQual::run('do', 'lodging_cubilis_ack-reservations', [
                'property_id'       => $extref_property_id,
                'reservations_ids'  => $reservations_ids
            ]);
    }
    catch(Exception $e) {
        ++$result['warnings'];
        $result['logs'][] = "WARN- Unable to acknowledge some reservations for Cubilis (property {$extref_property_id}) (reservations ".implode(',', $reservations_ids)."): ".$e->getMessage();
    }
}

// some errors or warnings might have occurred : send an error alert email to YB and Kaleo Manager
if($result['warnings'] || $result['errors']) {
    // generate text report
    ob_start();
    print_r($result);
    $report = ob_get_clean();

    if($result['errors']) {
        // build email message
        $message = new Email();
        $message->setTo(constant('EMAIL_ERRORS_RECIPIENT'))
                ->setSubject('Discope - ERREUR (Cubilis)')
                ->setContentType("text/html")
                ->setBody("<html>
                        <body>
                        <p>Alertes lors de l'exécution du script ".__FILE__." au ".date('d/m/Y').' à '.date('H:i').":</p>
                        <pre>".$report."</pre>
                        </body>
                    </html>");
        // queue message
        Mail::queue($message);
    }

    // send messages to specific center office teams
    foreach($result['center_offices'] as $center_office_id => $center_office) {
        // build email message
        if(strlen($center_office['email']) && count($center_office['logs'])) {
            $message = new Email();
            $message->setTo($center_office['email'])
                    ->setSubject('Discope ['.$center_office['name'].'] - Alerte Cubilis')
                    ->setContentType("text/html")
                    ->setBody("<html>
                            <body>
                            <p>Action requise suite à la synchronisation Cubilis du ".date('d/m/Y').' à '.date('H:i').":</p>
                            <p>
                            ".implode('<br />', $center_office['logs'])."
                            </p>
                            </body>
                        </html>");

            if($result['errors']) {
                $message->addCc(constant('EMAIL_REPORT_RECIPIENT'));
            }

            // queue message
            Mail::queue($message);
        }
    }
}
else {
    // do not store messages intended to users in Task logs
    unset($result['center_offices']);
}


$context->httpResponse()
        ->body($result)
        ->send();
