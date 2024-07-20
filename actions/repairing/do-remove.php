<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Repairing;

list($params, $providers) = announce([
    'description'   => "This will remove the repairing episode.The rental unit will be released and made available for bookings.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted repairing.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 */
list($context, $orm, $cron) = [$providers['context'], $providers['orm'], $providers['cron']];

$repairing = Repairing::id($params['id'])
    ->read(['date_from', 'date_to', 'rental_units_ids'])
    ->first(true);

// remember impacted rental units
$map_rental_units_ids = [];

foreach($repairing['rental_units_ids'] as $rental_unit_id) {
    $map_rental_units_ids[$rental_unit_id] = true;
}

// remove targeted repairing
Repairing::id($params['id'])->delete(true);

/*
    Check if consistency must be maintained with channel manager (if repairing impacts a rental unit that is linked to a channelmanager room type)
*/

if(count($map_rental_units_ids)) {
    $cron->schedule(
            "channelmanager.check-contingencies.{$params['id']}",
            time(),
            'lodging_booking_check-contingencies',
            [
                'date_from'         => date('c', $repairing['date_from']),
                // repairings completely cover the last day of the date range
                'date_to'           => date('c', strtotime('+1 day', $repairing['date_to'])),
                'rental_units_ids'  => array_keys($map_rental_units_ids)
            ]
        );
}

$context->httpResponse()
        // success but notify client to reset content
        ->status(205)
        ->send();