<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking\channelmanager;

class Property extends \equal\orm\Model {

    public static function getDescription() {
        return "A property is used as an interface to map Center from Discope with Property (hotel) from the channel manager.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => "Display name of the property (center)."
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Mark the property as active (for syncing).",
                'default'           => true
            ],

            'extref_property_id' => [
                'type'              => 'integer',
                'description'       => "External identifier of the property (from channel manager).",
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center to the property refers to.",
                'required'          => true
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\CenterOffice',
                'description'       => 'Office that manages the property.',
            ],

            'room_types_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\RoomType',
                'foreign_field'     => 'property_id',
                'description'       => 'Room types defined for the property.',
                'order'             => 'extref_roomtype_id'
            ],

            'extra_services_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\channelmanager\ExtraService',
                'foreign_field'     => 'property_id',
                'description'       => 'Extra services defined for the property.'
            ],

            'username' => [
                'type'              => 'string',
                'description'       => 'Username to access the Cubilis API.'
            ],

            'password' => [
                'type'              => 'string',
                'description'       => 'Password to access the Cubilis API.'
            ],

            'api_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier for the Cubilis API (allows to identity the Origin).'
            ],

            'psp_provider' => [
                'type'              => 'string',
                'description'       => 'String identifier of the Payment Service Provider.',
                'default'           => 'stripe'
            ],

            'psp_key' => [
                'type'              => 'string',
                'description'       => 'Private key to use for sending requests to PSP.'
            ]

        ];
    }


    public static function calcName($om, $ids, $lang) {
        $result = [];
        $properties = $om->read(self::getType(), $ids, ['center_id.name']);
        if($properties > 0) {
            foreach($properties as $id => $property) {
                $result[$id] = $property['center_id.name'];
            }
        }
        return $result;
    }



    /**
     * Generate an associative array mapping dates with invoiced amount.
     * There should be one entry for each date of the stay.
     */
    public static function cubilis_convertRatePlans($rate_plans) {
        $result = [];
        foreach($rate_plans as $rate_plan) {
            $date = $rate_plan['attributes']['EffectiveDate'];
            $result[$date] = [
                'tax_inclusive' => true,
                'amount'        => 0.0,
                'currency'      => 'EUR'
            ];
            foreach($rate_plan['children'] as $rate_plan_info) {
                if($rate_plan_info['name'] == 'RatePlanInclusions') {
                    $result[$date]['tax_inclusive'] = (bool) $rate_plan_info['attributes']['TaxInclusive'];
                    continue;
                }
                if($rate_plan_info['name'] == 'AdditionalDetails') {
                    $result[$date]['amount'] = (float) $rate_plan_info['children']['AdditionalDetail']['attributes']['Amount'];
                    $result[$date]['currency'] = (string) $rate_plan_info['children']['AdditionalDetail']['attributes']['CurrencyCode'];
                    continue;
                }
            }
        }
        return $result;
    }

    public static function cubilis_convertProfile($profile) {
        $result = [
            'firstname'         => '',
            'lastname'          => '',
            'phone'             => '',
            'email'             => '',
            'address_street'    => '',
            'address_city'      => '',
            'address_zip'       => '',
            'address_country'   => '',
            'lang'              => 'fr',
            // Reference Point Identifier - should always be 1, assuming there is only one customer per reservation
            'RPH'               => $profile['attributes']['RPH'],
        ];

        if(isset($profile['children']['Customer']['attributes']['Language'])) {
            $result['lang'] = strtolower($profile['children']['Customer']['attributes']['Language']);
        }
        if(isset($profile['children']['Customer']['children'])) {
            foreach($profile['children']['Customer']['children'] as $customer_info) {
                if($customer_info['name'] == 'PersonName') {
                    $result['firstname'] = $customer_info['children']['GivenName']['value'];
                    $result['lastname'] = $customer_info['children']['Surname']['value'];
                    continue;
                }
                if($customer_info['name'] == 'Telephone') {
                    $result['phone'] = $customer_info['attributes']['PhoneNumber'];
                    continue;
                }
                if($customer_info['name'] == 'Email') {
                    $result['email'] = $customer_info['value'];
                    continue;
                }
                if($customer_info['name'] == 'Address') {
                    foreach($customer_info['children'] as $address_info) {
                        if($address_info['name'] == 'AddressLine') {
                            $result['address_street'] = $address_info['value'];
                            continue;
                        }
                        if($address_info['name'] == 'CityName') {
                            $result['address_city'] = $address_info['value'];
                            continue;
                        }
                        if($address_info['name'] == 'PostalCode') {
                            $result['address_zip'] = $address_info['value'];
                            continue;
                        }
                        if($address_info['name'] == 'CountryName') {
                            $result['address_country'] = $address_info['value'];
                            continue;
                        }
                    }
                    continue;
                }

            }
        }

        return $result;
    }

    public static function cubilis_convertRoomType($room_type) {
        $result = [
            // identifier of the Room Type (Accommodation Type)
            'id'        => 0,
            // name of the Room Type (Accommodation Type)
            'name'      => '',
            // flag marking the unit as an accommodation
            'is_room'   => true
        ];
        if(isset($room_type['attributes']['IsRoom'])) {
            $result['is_room'] = (bool) $room_type['attributes']['IsRoom'];
        }
        if(isset($room_type['attributes']['RoomID'])) {
            $result['id'] = (int) $room_type['attributes']['RoomID'];
        }
        if(isset($room_type['children']['RoomDescription']['attributes']['Name'])) {
            $result['name'] = (string) $room_type['children']['RoomDescription']['attributes']['Name'];
        }
        return $result;
    }

    public static function cubilis_convertRoomStayTotal($total) {
        $result = [
            'amount'      => 0.0,
            'currency'    => 'EUR'
        ];
        if(isset($total['attributes']['AmountAfterTax'])) {
            $result['amount'] = round(floatval($total['attributes']['AmountAfterTax']), 2);
        }
        if(isset($total['attributes']['CurrencyCode'])) {
            $result['currency'] = (string) $total['attributes']['CurrencyCode'];
        }
        return $result;
    }
    /**
     * Generate a `OTA_HotelResRQ` request XML payload according to Cubilis 2.02 specs
     */
    public static function cubilis_HotelResRQ_generateXmlPayload($property_id, $username, $password, $api_id) {
        // #memo - the second RequestorID is set globally at Cubilis side for identifying Discope
        $xml = <<<XML
            <OTA_HotelResRQ Version="2.02" xmlns="http://www.opentravel.org/OTA/2003/05">
                <POS>
                    <Source>
                        <RequestorID Type="1" ID="{$username}" MessagePassword="{$password}" />
                    </Source>
                    <Source>
                        <RequestorID Type="2" ID="{$api_id}" />
                    </Source>
                </POS>
                <HotelReservations>
                    <HotelReservation />
                </HotelReservations>
            </OTA_HotelResRQ>
        XML;

        $root = simplexml_load_string( $xml );

        if ($root === false) {
            $errors = libxml_get_errors();
            throw new \Exception('invalid_xml_envelope', QN_ERROR_INVALID_PARAM);
        }

        // export as formatted XML
        $dom = new \DOMDocument();
        $dom->loadXML($root->asXML());
        // #memo - original settings are overwritten by loadXML
        $dom->encoding = "utf-8";
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        return trim($dom->saveXML());
    }

    /**
     * Generate a `OTA_NotifReportRQ` request XML payload according to Cubilis 2.02 specs
     */
    public static function cubilis_NotifReportRQ_generateXmlPayload($property_id, $username, $password, $api_id, $reservations_ids) {
        $xml = "
            <OTA_NotifReportRQ Version=\"2.0\" xmlns=\"http://www.opentravel.org/OTA/2003/05\">
                <NotifDetails>
                    <HotelNotifReport>
                        <HotelReservations>
                            <HotelReservation>
                                <POS>
                                    <Source>
                                        <RequestorID Type=\"1\" ID=\"{$username}\" MessagePassword=\"{$password}\" />
                                    </Source>
                                    <Source>
                                        <RequestorID Type=\"2\" ID=\"{$api_id}\" />
                                    </Source>
                                </POS>
                                <ResGlobalInfo>
                                    <HotelReservationIDs>\n";

        foreach($reservations_ids as $reservation_id) {
            $xml .= "                                        <HotelReservationID ResID_Value=\"{$reservation_id}\" />\n";
        }

        $xml .= "                                    </HotelReservationIDs>
                                </ResGlobalInfo>
                            </HotelReservation>
                        </HotelReservations>
                    </HotelNotifReport>
                </NotifDetails>
            </OTA_NotifReportRQ>";

        $root = simplexml_load_string( $xml );

        if ($root === false) {
            $errors = libxml_get_errors();
            throw new \Exception('invalid_xml_envelope', QN_ERROR_INVALID_PARAM);
        }

        // export as formatted XML
        $dom = new \DOMDocument();
        $dom->loadXML($root->asXML());
        // #memo - original settings are overwritten by loadXML
        $dom->encoding = "utf-8";
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        return trim($dom->saveXML());
    }

    /**
     * Generate a `OTA_HotelAvailNotifRQ` request XML payload according to Cubilis 2.02 specs
     */
    public static function cubilis_HotelAvailNotifRQ_generateXmlPayload($property_id, $username, $password, $api_id, $room_type_id, $date, $availability) {

        // make sure date is in `Y-m-d` format
        if(strpos($date, '-') === false) {
            $date = date('Y-m-d', $date);
        }

        // convert date to time span (day & day+1) as expected by XML structure
        $date_from = date('Y-m-d', strtotime($date));
        $date_to = date('Y-m-d', strtotime('+1 day', strtotime($date)));

        $xml = <<<XML
            <OTA_HotelAvailNotifRQ Version="2.0" xmlns="http://www.opentravel.org/OTA/2003/05">
                <POS>
                    <Source>
                        <RequestorID Type="1" ID="{$username}" MessagePassword="{$password}" />
                    </Source>
                    <Source>
                        <RequestorID Type="2" ID="{$api_id}" />
                    </Source>
                </POS>
                <AvailStatusMessages>
                    <AvailStatusMessage BookingLimit="{$availability}">
                        <StatusApplicationControl InvCode="{$room_type_id}" Start="{$date_from}" End="{$date_to}" />
                    </AvailStatusMessage>
                </AvailStatusMessages>
            </OTA_HotelAvailNotifRQ>
        XML;

        $root = simplexml_load_string( $xml );

        if ($root === false) {
            $errors = libxml_get_errors();
            throw new \Exception('invalid_xml_envelope', QN_ERROR_INVALID_PARAM);
        }

        // export as formatted XML
        $dom = new \DOMDocument();
        $dom->loadXML($root->asXML());
        // #memo - original settings are overwritten by loadXML
        $dom->encoding = "utf-8";
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        return trim($dom->saveXML());
    }
}