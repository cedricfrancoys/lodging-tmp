<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\price\PriceList;
use lodging\identity\Center;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineGroup;

list($params, $providers) = eQual::announce([
    'description'   => "Searches for bookings that are waiting for the pricelist to be published, and updates their TBC status.",
    'help'          => "This controller is scheduled when a PriceList has its status updated to 'published'.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the pricelist whose status has changed.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [ $providers['context'], $providers['orm'], $providers['auth'], $providers['dispatch']];

// switch to root account (access is 'private')
$auth->su();

$pricelist = PriceList::id($params['id'])->read(['id', 'status', 'price_list_category_id', 'date_from', 'date_to'])->first(true);

if(!$pricelist) {
    throw new Exception("unknown_pricelist", QN_ERROR_UNKNOWN_OBJECT);
}

if($pricelist['status'] == 'published') {

    // find related centers
    $centers_ids = Center::search(['price_list_category_id', '=', $pricelist['price_list_category_id']])->ids();

    // find all impacted bookings
    $bookings = Booking::search([
            ['center_id', 'in', $centers_ids],
            ['is_price_tbc', '=', true],
            ['date_from', '>=', $pricelist['date_from']],
            ['date_from', '<=', $pricelist['date_to']]
        ])
        ->read(['center_office_id', 'booking_lines_groups_ids', 'booking_lines_ids'])
        ->get();

    foreach($bookings as $bid => $booking) {
        BookingLine::ids($booking['booking_lines_ids'])->update(['unit_price' => null, 'price' => null, 'vat_rate' => null, 'total' => null]);
        BookingLineGroup::ids($booking['booking_lines_groups_ids'])->update(['unit_price' => null, 'price' => null, 'vat_rate' => null, 'total' => null]);
        Booking::id($bid)->update(['is_price_tbc' => false, 'price' => null, 'total' => null]);

        // dispatch a message for notifying users
        $dispatch->dispatch('lodging.booking.ready', 'lodging\sale\booking\Booking', $bid, 'warning', null, [], [], null, $booking['center_office_id']);
    }
}


$context->httpResponse()
        ->status(204)
        ->send();
