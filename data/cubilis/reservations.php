<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use equal\http\HttpRequest;
use lodging\sale\booking\channelmanager\Property;

list($params, $providers) = announce([
    'description'   => "Retrieve a batch of the latest reservations and their statuses, as provided from Cubilis in response to `OTA_HotelResRQ`.",
    'params'        => [
        'property_id' => [
            'description'   => 'Identifier of the property (from Cubilis).',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected',
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
list($context, $auth) = [ $providers['context'], $providers['auth'] ];

/*
XML payload response example :

<?xml version="1.0" encoding="utf-8"?>
<OTA_HotelResRS xmlns="http://www.opentravel.org/OTA/2003/05" Version="2.02">
    <Success />
    <HotelReservations>
        <HotelReservation CreateDateTime="2023-07-18T17:33:02" CreatorID="44442761" ResStatus="Reserved">
            <POS>
                <Source TerminalID="" />
            </POS>
            <UniqueID ID="0" Type="PAR">
                <CompanyName>LogisManager</CompanyName>
            </UniqueID>
            <UniqueID ID="9489" Type="HOT" />
            <RoomStays>
                <RoomStay RoomStayStatus="Reserved" IndexNumber="1">
                    <RoomTypes>
                        <RoomType IsRoom="true" RoomID="48553">
                            <RoomDescription Name="SINGLE" />
                        </RoomType>
                    </RoomTypes>
                    <RatePlans>
                        <RatePlan EffectiveDate="2023-07-25" RatePlanID="0" RatePlanName="Default rate">
                            <RatePlanInclusions TaxInclusive="true" />
                            <AdditionalDetails>
                                <AdditionalDetail Amount="80.00" CurrencyCode="EUR" />
                            </AdditionalDetails>
                        </RatePlan>
                        <RatePlan EffectiveDate="2023-07-26" RatePlanID="0" RatePlanName="Default rate">
                            <RatePlanInclusions TaxInclusive="true" />
                            <AdditionalDetails>
                                <AdditionalDetail Amount="80.00" CurrencyCode="EUR" />
                            </AdditionalDetails>
                        </RatePlan>
                        <RatePlan EffectiveDate="2023-07-27" RatePlanID="0" RatePlanName="Default rate">
                            <RatePlanInclusions TaxInclusive="true" />
                            <AdditionalDetails>
                                <AdditionalDetail Amount="80.00" CurrencyCode="EUR" />
                            </AdditionalDetails>
                        </RatePlan>
                    </RatePlans>
                    <GuestCounts>
                        <GuestCount AgeQualifyingCode="1" Count="1" />
                    </GuestCounts>
                    <ResGuestRPHs />
                    <Total AmountAfterTax="240.00" CurrencyCode="EUR" />
                    <BasicPropertyInfo HotelCode="9489" />
                    <Comments>
                        <Comment GuestViewable="true">
                            <Text>
                            </Text>
                        </Comment>
                    </Comments>
                </RoomStay>
            </RoomStays>
            <ResGlobalInfo>
                <TimeSpan Start="2023-07-25T13:30:00" End="2023-07-28" />
                <Comments>
                    <Comment GuestViewable="false" Name="Partner" CreatorID="0">
                        <Text>LogisManager</Text>
                    </Comment>
                    <Comment GuestViewable="true">
                        <Text>commentaires tests ok</Text>
                    </Comment>
                </Comments>
                <Total AmountAfterTax="240.00" CurrencyCode="EUR" />
                <HotelReservationIDs>
                    <HotelReservationID ResID_Value="44442761" ResID_Source="" />
                </HotelReservationIDs>
                <Profiles>
                    <ProfileInfo>
                        <Profile RPH="1">
                            <Customer Language="fr">
                                <PersonName>
                                    <GivenName>Cédric</GivenName>
                                    <Surname>Françoys</Surname>
                                </PersonName>
                                <Telephone PhoneNumber="+32486152419" />
                                <Email>cedric@yesbabylon.com</Email>
                                <Address>
                                    <AddressLine>Boulevard du Souverain 24</AddressLine>
                                    <CityName>Bruxelles</CityName>
                                    <PostalCode>1170</PostalCode>
                                    <CountryName>BE</CountryName>
                                </Address>
                            </Customer>
                        </Profile>
                    </ProfileInfo>
                </Profiles>
            </ResGlobalInfo>
        </HotelReservation>
        <HotelReservation CreateDateTime="2023-07-05T15:27:25" CreatorID="44228809" ResStatus="Reserved">
            <POS>
                <Source TerminalID="" />
            </POS>
            <UniqueID ID="0" Type="PAR">
                <CompanyName>LogisManager</CompanyName>
            </UniqueID>
            <UniqueID ID="9489" Type="HOT" />
            <RoomStays>
                <RoomStay RoomStayStatus="Reserved" IndexNumber="1">
                    <RoomTypes>
                        <RoomType IsRoom="true" RoomID="48553">
                            <RoomDescription Name="SINGLE" />
                        </RoomType>
                    </RoomTypes>
                    <RatePlans>
                        <RatePlan EffectiveDate="2023-07-05" RatePlanID="0" RatePlanName="Default rate">
                            <RatePlanInclusions TaxInclusive="true" />
                            <AdditionalDetails>
                                <AdditionalDetail Amount="80.00" CurrencyCode="EUR" />
                            </AdditionalDetails>
                        </RatePlan>
                    </RatePlans>
                    <GuestCounts>
                        <GuestCount AgeQualifyingCode="1" Count="1" />
                    </GuestCounts>
                    <ResGuestRPHs />
                    <Total AmountAfterTax="80.00" CurrencyCode="EUR" />
                    <BasicPropertyInfo HotelCode="9489" />
                    <Comments>
                        <Comment GuestViewable="true">
                            <Text>
              </Text>
                        </Comment>
                    </Comments>
                </RoomStay>
            </RoomStays>
            <ResGlobalInfo>
                <TimeSpan Start="2023-07-05T13:00:00" End="2023-07-06" />
                <Comments>
                    <Comment GuestViewable="false" Name="Partner" CreatorID="0">
                        <Text>LogisManager</Text>
                    </Comment>
                    <Comment GuestViewable="true">
                        <Text>test</Text>
                    </Comment>
                </Comments>
                <Guarantee>
                    <GuaranteesAccepted>
                        <GuaranteeAccepted>
                            <PaymentCard CardCode="VI" CardNumber="4462919012345678" SeriesCode="" ExpireDate="0923">
                                <CardHolderName>Cedric Francoys</CardHolderName>
                            </PaymentCard>
                        </GuaranteeAccepted>
                    </GuaranteesAccepted>
                </Guarantee>
                <Total AmountAfterTax="80.00" CurrencyCode="EUR" />
                <HotelReservationIDs>
                    <HotelReservationID ResID_Value="44228809" ResID_Source="" />
                </HotelReservationIDs>
                <Profiles>
                    <ProfileInfo>
                        <Profile RPH="1">
                            <Customer Language="fr">
                                <PersonName>
                                    <GivenName>cedric</GivenName>
                                    <Surname>francoys</Surname>
                                </PersonName>
                                <Telephone PhoneNumber="0486152419" />
                                <Email>cedric@yesbabylon.com</Email>
                                <Address>
                                    <AddressLine>24 boulevard du souverain 24</AddressLine>
                                    <CityName>Bruxelles</CityName>
                                    <PostalCode>1170</PostalCode>
                                    <CountryName>BE</CountryName>
                                </Address>
                            </Customer>
                        </Profile>
                    </ProfileInfo>
                </Profiles>
            </ResGlobalInfo>
        </HotelReservation>
    </HotelReservations>
</OTA_HotelResRS>
 */

/*
The response of this controller is an associative array mapping properties_ids (hotels) with the new reservations notifications.

Example of JSON response provided by this controller :
```
{
    "9489": [
        {
            "reservation_id": "44442761",
            "partner_reservation_id": "",
            "source": "",
            "partner": "LogisManager",
            "partner_id": "0",
            "status": "Reserved",
            "start": "2023-07-25T13:30:00+00:00",
            "end": "2023-07-28T00:00:00+00:00",
            "total": "240.00",
            "currency": "EUR",
            "comments": "commentaires tests ok",
            "customer": {
                "firstname": "C\u00e9dric",
                "lastname": "Fran\u00e7oys",
                "phone": "+32486152419",
                "email": "cedric@yesbabylon.com",
                "address_street": "Boulevard du Souverain 24",
                "address_city": "Bruxelles",
                "address_zip": "1170",
                "address_country": "BE",
                "lang": "fr",
                "RPH": "1"
            },
            "room_stays": [
                {
                    "room_type": {
                        "id": 48553,
                        "name": "SINGLE",
                        "is_room": true
                    },
                    "guests": {
                        "adults": "1"
                    },
                    "rate_plans": {
                        "2023-07-25": {
                            "tax_inclusive": true,
                            "amount": 80,
                            "currency": "EUR"
                        },
                        "2023-07-26": {
                            "tax_inclusive": true,
                            "amount": 80,
                            "currency": "EUR"
                        },
                        "2023-07-27": {
                            "tax_inclusive": true,
                            "amount": 80,
                            "currency": "EUR"
                        }
                    },
                    "total": {
                        "amount": 240,
                        "currency": "EUR"
                    }
                }
            ],
            "extra_services": [
                {
                    "inventory_code": "23067",
                    "comments": "Taxe de s\u00e9jour: 3n x 2ps x 2.01 = 12.06",
                    "total": {
                        "amount": 12.06,
                        "currency": "EUR"
                    }
                },
                {
                    "inventory_code": "23065",
                    "comments": "Membership card: 5.00",
                    "total": {
                        "amount": 5.00,
                        "currency": "EUR"
                    }
                },
                {
                    "inventory_code": "23066",
                    "comments": "Location draps : 2ps x 3n x 4.96 = 29.76",
                    "total": {
                        "amount": 29.76,
                        "currency": "EUR"
                    }
                }
            ],
            "payments": [
                {
                    "psp_reference": "pi_3Nj1yaJkzhOGpR2F0yqPo52M",
                    "payment_service_provider": "Stripe - CF",
                    "payment_method": "CC"
                },
                {
                    "psp_reference": "pi_3Nj21tJkzhOGpR2F0y3cvIqB",
                    "payment_service_provider": "Stripe - CF",
                    "payment_method": "CC"
                },
                {
                    "psp_reference": "pi_3Nj24RJkzhOGpR2F1O43x4iF",
                    "payment_service_provider": "Stripe - CF",
                    "payment_method": "CC"
                }
            ]
        },
    ]
}
```
*/

// #memo - each we need the credentials from the Center
$property = Property::search(['extref_property_id', '=', $params['property_id']])->read(['id', 'username', 'password', 'api_id'])->first(true);

if(!$property) {
    throw new Exception('unknown_property', QN_ERROR_UNKNOWN_OBJECT);
}

$xml = Property::cubilis_HotelResRQ_generateXmlPayload($params['property_id'], $property['username'], $property['password'], $property['api_id']);

$entrypoint_url = "https://cubilis.eu/plugins/PMS_ota/reservations.aspx";

$request = new HttpRequest('POST '.$entrypoint_url);
$request->header('Content-Type', 'text/xml');

$response = $request->setBody($xml, true)->send();

$status = $response->getStatusCode();

if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception('request_rejected', QN_ERROR_INVALID_PARAM);
}

// we should have received a text/xml response, if so HttpMessage::body() contains a parsed version of the XML data
// #memo - raw body can be retrieved by using $response->getBody(true);
$envelope = $response->body();

// check response consistency
if(!isset($envelope['name']) || $envelope['name'] != 'OTA_HotelResRS') {
    throw new Exception('Invalid response received (valid XML but unexpected format).', QN_ERROR_UNKNOWN);
}

if(!isset($envelope['children']['Success'])) {
    if(isset($envelope['children']['Errors'])) {
        $errors = [];
        foreach($envelope['children']['Errors']['children'] as $child) {
            if(isset($child['attributes'])) {
                $errors[] = $child['attributes']['Code'].' - '.$child['attributes']['ShortText'];
            }
        }
        throw new Exception('errors: '.implode(',', $errors), QN_ERROR_INVALID_PARAM);
    }
    throw new Exception('unknown_error', QN_ERROR_UNKNOWN);
}

/*
    build the payload as an associative array
*/
$result = [];

if(isset($envelope['children'][0])) {

    $reservations = $envelope['children'][0];

    /**
     * Generate an associative array mapping properties IDS (hotels) with retrieved reservations.
     *
     */

    foreach($reservations['children'] as $reservation) {
        $hotel_id = 0;
        $entry = [
            // (?IP from which the reservation originates)
            'source'                => '',
            // string identifier of the partner through which the reservation was made
            'partner'               => '',
            // identifier of the partner OTA through which the reservation was made (0 = Cubilis LogisManager, 4 = booking.com, 5 = expedia)
            'partner_id'            => 0,
            // Cubilis reservation ID
            'reservation_id'        => 0,
            // identifier of the reservation from the partner OTA (if distinct from Cubilis LogisManager)
            'partner_reservation_id'=> 0,
            // status : ['Reserved', 'Cancelled', 'Modify', 'Request denied', 'Waitlisted']
            'status'                => $reservation['attributes']['ResStatus'],
            // date of arrival
            'start'                 => 0,
            // date of departure
            'end'                   => 0,
            // tax-inclusive total price
            'total'                 => 0.0,
            // currency of the total
            'currency'              => '',
            // comments provided by the Customer
            'comments'              => '',
            // customer details
            'customer'              => [],
            // contact details of additional people
            'contacts'              => [],
            'room_stays'            => [],
            'extra_services'        => [],
            'payments'              => [],
            'guarantees'            => []
        ];

        foreach($reservation['children'] as $reservation_info) {
            if($reservation_info['name'] == 'UniqueID' && $reservation_info['attributes']['Type'] == 'HOT') {
                $hotel_id = $reservation_info['attributes']['ID'];
                continue;
            }
            if($reservation_info['name'] == 'UniqueID' && $reservation_info['attributes']['Type'] == 'PAR') {
                $entry['partner'] = $reservation_info['children']['CompanyName']['value'];
                $entry['partner_id'] = intval($reservation_info['attributes']['ID']);
                continue;
            }
            if($reservation_info['name'] == 'POS' && isset($reservation_info['children']['Source'])) {
                $entry['source'] = $reservation_info['children']['Source']['attributes']['TerminalID'];
                continue;
            }
            if($reservation_info['name'] == 'ResGlobalInfo' && $reservation_info['has_children']) {
                foreach($reservation_info['children'] as $global_info) {
                    if($global_info['name'] == 'TimeSpan') {
                        $entry['start'] = date('c', strtotime($global_info['attributes']['Start']));
                        $entry['end'] = date('c', strtotime($global_info['attributes']['End']));
                        continue;
                    }
                    if($global_info['name'] == 'Comments') {
                        foreach($global_info['children'] as $comment_info) {
                            if($comment_info['attributes']['GuestViewable'] == 'true') {
                                $entry['comments'] = $comment_info['children']['Text']['value'];
                                break;
                            }
                        }
                        continue;
                    }
                    if($global_info['name'] == 'Guarantee') {
                        if(isset($global_info['children']['GuaranteesAccepted']['children']['GuaranteeAccepted']['children'])) {
                            foreach($global_info['children']['GuaranteesAccepted']['children']['GuaranteeAccepted']['children'] as $payment_mode) {
                                $guarantee_entry = [
                                    'mode'          => '',
                                    'holder'        => '',
                                    'card_number'   => '',
                                    'card_expire'   => '',
                                    'card_type'     => ''
                                ];
                                $guarantee_entry['mode'] = str_replace(['PaymentCard'], ['card'], $payment_mode['name']);
                                if(isset($payment_mode['attributes']['CardNumber'])) {
                                    $card_num = $payment_mode['attributes']['CardNumber'];
                                    $guarantee_entry['card_number'] = str_repeat('*', strlen($card_num) - 4) . substr($card_num, -4);
                                }
                                if(isset($payment_mode['attributes']['ExpireDate'])) {
                                    $card_expire = $payment_mode['attributes']['ExpireDate'];
                                    $guarantee_entry['card_expire'] = substr($card_expire, 0, 2).'/'.substr($card_expire, 2, 2);
                                }
                                if(isset($payment_mode['attributes']['CardCode'])) {
                                    $guarantee_entry['card_type'] = $payment_mode['attributes']['CardCode'];;
                                }
                                if(isset($payment_mode['children']['CardHolderName'])) {
                                    $guarantee_entry['holder'] = $payment_mode['children']['CardHolderName']['value'];
                                }
                                $entry['guarantees'][] = $guarantee_entry;
                            }
                        }
                        continue;
                    }
                    if($global_info['name'] == 'Total') {
                        $entry['total'] = [
                            'amount'    => round(floatval($global_info['attributes']['AmountAfterTax']), 2),
                            'currency'  => $global_info['attributes']['CurrencyCode']
                        ];
                        continue;
                    }
                    if($global_info['name'] == 'HotelReservationIDs' && $global_info['has_children']) {
                        $entry['reservation_id'] = intval($global_info['children']['HotelReservationID']['attributes']['ResID_Value']);
                        $entry['partner_reservation_id'] = intval($global_info['children']['HotelReservationID']['attributes']['ResID_Source']);
                        continue;
                    }
                    if($global_info['name'] == 'Profiles' && $global_info['has_children']) {
                        // if there is only one profile, then it is the customer
                        if(isset($global_info['children']['ProfileInfo']['children']['Profile'])) {
                            $entry['customer'] = Property::cubilis_convertProfile($global_info['children']['ProfileInfo']['children']['Profile']);
                        }
                        // if there are several profiles, the first is the customer (RPH=1) and the others as additional contacts
                        else {
                            $count_profiles = count($global_info['children']['ProfileInfo']['children']);
                            if($count_profiles > 0) {
                                $entry['customer'] = Property::cubilis_convertProfile($global_info['children']['ProfileInfo']['children'][0]);
                                for($i = 1; $i < $count_profiles; ++$i) {
                                    $entry['contacts'][] = Property::cubilis_convertProfile($global_info['children']['ProfileInfo']['children'][$i]);
                                }
                            }
                        }
                        continue;
                    }
                    if($global_info['name'] == 'Payments') {
                        $payment = [];
                        foreach($global_info['children'] as $payment_info) {
                            if(isset($payment_info['children']['PspReference'])) {
                                $payment['psp_reference'] = $payment_info['children']['PspReference']['value'];
                            }
                            if(isset($payment_info['children']['PaymentProvider'])) {
                                $payment['payment_service_provider'] = $payment_info['children']['PaymentProvider']['value'];
                            }
                            if(isset($payment_info['children']['PaymentMethod'])) {
                                $payment['payment_method'] = $payment_info['children']['PaymentMethod']['value'];
                            }
                            if(isset($payment_info['children']['Created'])) {
                                $payment['created'] = $payment_info['children']['Created']['value'];
                            }
                            if(isset($payment_info['children']['Amount'])) {
                                $payment['total'] = [
                                        'amount'    => floatval($payment_info['children']['Amount']['value'])
                                    ];
                            }

                            $entry['payments'][] = $payment;
                        }
                        continue;
                    }
                }
                continue;
            }
            if($reservation_info['name'] == 'RoomStays' && $reservation_info['has_children']) {
                $entry['room_stays'] = [];
                foreach($reservation_info['children'] as $room_stay) {
                    $room_stay_entry = [
                        'status'        => $room_stay['attributes']['RoomStayStatus'],
                        'room_type'     => [],
                        'guests'        => [],
                        'rate_plans'    => []
                    ];
                    foreach($room_stay['children'] as $room_stay_info) {
                        if($room_stay_info['name'] == 'RoomTypes' && isset($room_stay_info['children']['RoomType'])) {
                            $room_stay_entry['room_type'] = Property::cubilis_convertRoomType($room_stay_info['children']['RoomType']);
                            continue;
                        }
                        if($room_stay_info['name'] == 'RatePlans' && $room_stay_info['has_children']) {
                            $room_stay_entry['rate_plans'] = Property::cubilis_convertRatePlans($room_stay_info['children']);
                            continue;
                        }
                        if($room_stay_info['name'] == 'GuestCounts' && $room_stay_info['has_children']) {
                            $guest_counts = [
                                'total' => 0
                            ];
                            foreach($room_stay_info['children'] as $guest_count_info) {
                                $range = [1 => 'adults', 2 => 'children', 3 => 'babies'][$guest_count_info['attributes']['AgeQualifyingCode']];
                                $guest_counts[$range] = $guest_count_info['attributes']['Count'];
                                $guest_counts['total'] += $guest_count_info['attributes']['Count'];
                            }
                            $room_stay_entry['guests'] = $guest_counts;
                            continue;
                        }
                        if($room_stay_info['name'] == 'Total' && isset($room_stay_info['attributes'])) {
                            $room_stay_entry['total'] = Property::cubilis_convertRoomStayTotal($room_stay_info);
                            continue;
                        }
                    }
                    $entry['room_stays'][] = $room_stay_entry;
                }
                continue;
            }
            if($reservation_info['name'] == 'Services' && $reservation_info['has_children']) {
                foreach($reservation_info['children'] as $service) {
                    $service_entry = [
                        'inventory_code'    => [],
                        'comments'          => [],
                        'total'             => []
                    ];
                    if(isset($service['attributes']['ServiceInventoryCode'])) {
                        $service_entry['inventory_code'] = $service['attributes']['ServiceInventoryCode'];
                    }
                    if(isset($service['children']['ServiceDetails']['children'])) {
                        foreach($service['children']['ServiceDetails']['children'] as $service_details) {
                            if($service_details['name'] == 'Total' && isset($service_details['attributes']['AmountAfterTax'])) {
                                $service_entry['total'] = [
                                        'amount'    => round(floatval($service_details['attributes']['AmountAfterTax']), 2),
                                        'currency'  => (string) $service_details['attributes']['CurrencyCode']
                                    ];
                                continue;
                            }
                            if($service_details['name'] == 'Comments' && isset($service_details['children']['Comment']['children']['Text']['value'])) {
                                $service_entry['comments'] = $service_details['children']['Comment']['children']['Text']['value'];
                                continue;
                            }
                        }
                    }
                    $entry['extra_services'][] = $service_entry;
                }
                continue;
            }
        }
        if(!isset($result[$hotel_id])) {
            $result[$hotel_id] = [];
        }
        $result[$hotel_id][] = $entry;
    }
}

$context->httpResponse()
        ->body($result)
        ->send();
