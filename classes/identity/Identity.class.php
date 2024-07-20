<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\identity;

use lodging\sale\booking\Booking;
use lodging\sale\booking\Invoice;

class Identity extends \identity\Identity {

    public static function getColumns() {
        return [

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => Contact::getType(),
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['partner_identity_id', '<>', 'object.id'],
                'description'       => 'List of contacts relating to the organisation (not necessarily employees), if any.'
            ],

            'bookings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => Booking::getType(),
                'foreign_field'     => 'customer_identity_id',
                'description'       => 'List of bookings relating to the identity.'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => Invoice::getType(),
                'foreign_field'     => 'customer_identity_id',
                'description'       => 'List of invoices relating to the identity (as customer).'
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => "Preferred language of the identity.",
                'default'           => 2,
                'onupdate'          => 'identity\Identity::onupdateLangId'
            ],

            'email_secondary' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Identity secondary email address."
            ],

            // field for retrieving all partners related to the identity
            'partners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\customer\Customer',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Partnerships that relate to the identity.',
                'domain'            => ['owner_identity_id', '<>', 'object.id']
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'onupdate'          => 'onupdateAddress'
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.',
                'onupdate'          => 'onupdateAddress'
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'default'           => 'BE',
                'selection'         => [
                    'AF'    => 'Afghanistan',
                    'AX'    => 'Aland Islands',
                    'AL'    => 'Albanie',
                    'DZ'    => 'Algérie',
                    'AS'    => 'American Samoa',
                    'AD'    => 'Andorra',
                    'AO'    => 'Angola',
                    'AI'    => 'Anguilla',
                    'AQ'    => 'Antarctica',
                    'AG'    => 'Antigua And Barbuda',
                    'AR'    => 'Argentina',
                    'AM'    => 'Arménie',
                    'AW'    => 'Aruba',
                    'AU'    => 'Australia',
                    'AT'    => 'Autriche',
                    'AZ'    => 'Azerbaïdjan',
                    'BS'    => 'Bahamas',
                    'BH'    => 'Bahrain',
                    'BD'    => 'Bangladesh',
                    'BB'    => 'Barbados',
                    'BY'    => 'Biélorussie',
                    'BE'    => 'Belgique',
                    'BZ'    => 'Belize',
                    'BJ'    => 'Benin',
                    'BM'    => 'Bermuda',
                    'BT'    => 'Bhutan',
                    'BO'    => 'Bolivia',
                    'BA'    => 'Bosnie-Herzégovine',
                    'BW'    => 'Botswana',
                    'BV'    => 'Bouvet Island',
                    'BR'    => 'Brazil',
                    'IO'    => 'British Indian Ocean Territory',
                    'BN'    => 'Brunei Darussalam',
                    'BG'    => 'Bulgarie',
                    'BF'    => 'Burkina Faso',
                    'BI'    => 'Burundi',
                    'KH'    => 'Cambodia',
                    'CM'    => 'Cameroon',
                    'CA'    => 'Canada',
                    'CV'    => 'Cape Verde',
                    'KY'    => 'Cayman Islands',
                    'CF'    => 'Central African Republic',
                    'TD'    => 'Chad',
                    'CL'    => 'Chile',
                    'CN'    => 'China',
                    'CX'    => 'Christmas Island',
                    'CC'    => 'Cocos (Keeling) Islands',
                    'CO'    => 'Colombia',
                    'KM'    => 'Comoros',
                    'CG'    => 'Congo',
                    'CD'    => 'Congo, Democratic Republic',
                    'CK'    => 'Cook Islands',
                    'CR'    => 'Costa Rica',
                    'CI'    => 'Cote D\'Ivoire',
                    'HR'    => 'Croatie',
                    'CU'    => 'Cuba',
                    'CY'    => 'Chypre',
                    'CZ'    => 'Tchéquie',
                    'DK'    => 'Danemark',
                    'DJ'    => 'Djibouti',
                    'DM'    => 'Dominica',
                    'DO'    => 'Dominican Republic',
                    'EC'    => 'Ecuador',
                    'EG'    => 'Egypte',
                    'SV'    => 'El Salvador',
                    'GQ'    => 'Equatorial Guinea',
                    'ER'    => 'Eritrea',
                    'EE'    => 'Estonie',
                    'ET'    => 'Ethiopia',
                    'FK'    => 'Falkland Islands (Malvinas)',
                    'FO'    => 'Faroe Islands',
                    'FJ'    => 'Fiji',
                    'FI'    => 'Finlande',
                    'FR'    => 'France',
                    'GF'    => 'French Guiana',
                    'PF'    => 'French Polynesia',
                    'TF'    => 'French Southern Territories',
                    'GA'    => 'Gabon',
                    'GM'    => 'Gambia',
                    'GE'    => 'Géorgie',
                    'DE'    => 'Allemagne',
                    'GH'    => 'Ghana',
                    'GI'    => 'Gibraltar',
                    'GR'    => 'Greece',
                    'GL'    => 'Greenland',
                    'GD'    => 'Grenada',
                    'GP'    => 'Guadeloupe',
                    'GU'    => 'Guam',
                    'GT'    => 'Guatemala',
                    'GG'    => 'Guernsey',
                    'GN'    => 'Guinea',
                    'GW'    => 'Guinea-Bissau',
                    'GY'    => 'Guyana',
                    'HT'    => 'Haiti',
                    'HM'    => 'Heard Island & Mcdonald Islands',
                    'VA'    => 'Holy See (Vatican City State)',
                    'HN'    => 'Honduras',
                    'HK'    => 'Hong Kong',
                    'HU'    => 'Hongrie',
                    'IS'    => 'Islande',
                    'IN'    => 'India',
                    'ID'    => 'Indonesia',
                    'IR'    => 'Iran, Islamic Republic Of',
                    'IQ'    => 'Iraq',
                    'IE'    => 'Irlande',
                    'IM'    => 'Isle Of Man',
                    'IL'    => 'Israel',
                    'IT'    => 'Italie',
                    'JM'    => 'Jamaica',
                    'JP'    => 'Japan',
                    'JE'    => 'Jersey',
                    'JO'    => 'Jordanie',
                    'KZ'    => 'Kazakhstan',
                    'KE'    => 'Kenya',
                    'KI'    => 'Kiribati',
                    'KR'    => 'Korea',
                    'KW'    => 'Kuwait',
                    'KG'    => 'Kyrgyzstan',
                    'LA'    => 'Lao People\'s Democratic Republic',
                    'LV'    => 'Lettonie',
                    'LB'    => 'Liban',
                    'LS'    => 'Lesotho',
                    'LR'    => 'Liberia',
                    'LY'    => 'Libye',
                    'LI'    => 'Liechtenstein',
                    'LT'    => 'Lituanie',
                    'LU'    => 'Luxembourg',
                    'MO'    => 'Macao',
                    'MK'    => 'Macedonia',
                    'MG'    => 'Madagascar',
                    'MW'    => 'Malawi',
                    'MY'    => 'Malaysia',
                    'MV'    => 'Maldives',
                    'ML'    => 'Mali',
                    'MT'    => 'Malta',
                    'MH'    => 'Marshall Islands',
                    'MQ'    => 'Martinique',
                    'MR'    => 'Mauritania',
                    'MU'    => 'Mauritius',
                    'YT'    => 'Mayotte',
                    'MX'    => 'Mexico',
                    'FM'    => 'Micronesia, Federated States Of',
                    'MD'    => 'Moldavie',
                    'MC'    => 'Monaco',
                    'MN'    => 'Mongolia',
                    'ME'    => 'Monténégro',
                    'MS'    => 'Montserrat',
                    'MA'    => 'Maroc',
                    'MZ'    => 'Mozambique',
                    'MM'    => 'Myanmar',
                    'NA'    => 'Namibia',
                    'NR'    => 'Nauru',
                    'NP'    => 'Nepal',
                    'NL'    => 'Pays-Bas',
                    'AN'    => 'Netherlands Antilles',
                    'NC'    => 'New Caledonia',
                    'NZ'    => 'New Zealand',
                    'NI'    => 'Nicaragua',
                    'NE'    => 'Niger',
                    'NG'    => 'Nigeria',
                    'NU'    => 'Niue',
                    'NF'    => 'Norfolk Island',
                    'MP'    => 'Northern Mariana Islands',
                    'NO'    => 'Norvège',
                    'OM'    => 'Oman',
                    'PK'    => 'Pakistan',
                    'PW'    => 'Palau',
                    'PS'    => 'Palestinian Territory, Occupied',
                    'PA'    => 'Panama',
                    'PG'    => 'Papua New Guinea',
                    'PY'    => 'Paraguay',
                    'PE'    => 'Peru',
                    'PH'    => 'Philippines',
                    'PN'    => 'Pitcairn',
                    'PL'    => 'Pologne',
                    'PT'    => 'Portugal',
                    'PR'    => 'Puerto Rico',
                    'QA'    => 'Qatar',
                    'RE'    => 'Reunion',
                    'RO'    => 'Roumanie',
                    'RU'    => 'Russie',
                    'RW'    => 'Rwanda',
                    'BL'    => 'Saint Barthelemy',
                    'SH'    => 'Saint Helena',
                    'KN'    => 'Saint Kitts And Nevis',
                    'LC'    => 'Saint Lucia',
                    'MF'    => 'Saint Martin',
                    'PM'    => 'Saint Pierre And Miquelon',
                    'VC'    => 'Saint Vincent And Grenadines',
                    'WS'    => 'Samoa',
                    'SM'    => 'San Marino',
                    'ST'    => 'Sao Tome And Principe',
                    'SA'    => 'Saudi Arabia',
                    'SN'    => 'Senegal',
                    'RS'    => 'Serbie',
                    'SC'    => 'Seychelles',
                    'SL'    => 'Sierra Leone',
                    'SG'    => 'Singapore',
                    'SK'    => 'Slovakia',
                    'SI'    => 'Slovénie',
                    'SB'    => 'Solomon Islands',
                    'SO'    => 'Somalia',
                    'ZA'    => 'South Africa',
                    'GS'    => 'South Georgia And Sandwich Isl.',
                    'ES'    => 'Espagne',
                    'LK'    => 'Sri Lanka',
                    'SD'    => 'Sudan',
                    'SR'    => 'Suriname',
                    'SJ'    => 'Svalbard And Jan Mayen',
                    'SZ'    => 'Swaziland',
                    'SE'    => 'Suède',
                    'CH'    => 'Suisse',
                    'SY'    => 'Syrie',
                    'TW'    => 'Taiwan',
                    'TJ'    => 'Tajikistan',
                    'TZ'    => 'Tanzania',
                    'TH'    => 'Thailand',
                    'TL'    => 'Timor-Leste',
                    'TG'    => 'Togo',
                    'TK'    => 'Tokelau',
                    'TO'    => 'Tonga',
                    'TT'    => 'Trinidad And Tobago',
                    'TN'    => 'Tunisie',
                    'TR'    => 'Turquie',
                    'TM'    => 'Turkmenistan',
                    'TC'    => 'Turks And Caicos Islands',
                    'TV'    => 'Tuvalu',
                    'UG'    => 'Uganda',
                    'UA'    => 'Ukraine',
                    'AE'    => 'United Arab Emirates',
                    'GB'    => 'United Kingdom',
                    'US'    => 'United States',
                    'UM'    => 'United States Outlying Islands',
                    'UY'    => 'Uruguay',
                    'UZ'    => 'Uzbekistan',
                    'VU'    => 'Vanuatu',
                    'VE'    => 'Venezuela',
                    'VN'    => 'Viet Nam',
                    'VG'    => 'Virgin Islands, British',
                    'VI'    => 'Virgin Islands, U.S.',
                    'WF'    => 'Wallis And Futuna',
                    'EH'    => 'Western Sahara',
                    'YE'    => 'Yemen',
                    'ZM'    => 'Zambia',
                    'ZW'    => 'Zimbabwe'
                ],
                'onupdate'          => 'onupdateAddress'
            ],

            // handle duplicate
            'has_duplicate_clue' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Uncheck to force creation.',
                'help'              => 'Alert user that identity under creation may be a duplicate.'
            ],

            'duplicate_clue_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => 'Identity that may be a duplicate.',
                'help'              => 'Showed to user when creating new identity.'
            ],

            'duplicate_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Identity',
                'description'       => 'Identity that is a duplicate.',
            ],

            'is_duplicate' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Is a duplicate of another identity.',
                'store'             => true,
                'function'          => 'calcIsDuplicate',
                'onupdate'          => 'onupdateIsDuplicate'
            ],

            'duplicate_identities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\identity\Identity',
                'foreign_field'     => 'duplicate_identity_id',
                'description'       => 'List of possible duplicates.',
                'domain'            => ['is_duplicate', '<>', false]
            ],

            // ota
            'is_ota' => [
                'type'              => 'boolean',
                'description'       => 'Is the identity from OTA origin.',
                'default'           => false
            ]

        ];
    }

    /**
     * On update name do re-calc is duplicate
     * 
     * @param  \equal\orm\ObjectManager $om     Object Manager instance.
     * @param  int[]                    $ids    List of objects identifiers.
     * @param  array                    $values Associative array holding the new values to be assigned.
     * @param  string                   $lang   Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdateName($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), 'reCalcIsDuplicate', $ids);
        
        parent::onupdateName($om, $ids, $values, $lang);
    }
    
    /**
     * On update address do re-calc is duplicate
     * 
     * @param  \equal\orm\ObjectManager $om     Object Manager instance.
     * @param  int[]                    $ids    List of objects identifiers.
     * @param  array                    $values Associative array holding the new values to be assigned.
     * @param  string                   $lang   Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdateAddress($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), 'reCalcIsDuplicate', $ids);
    }

    /**
     * Signature for single object change from views.
     *
     * @param  \equal\orm\ObjectManager $om     Object Manager instance.
     * @param  array                    $event  Associative array holding changed fields as keys, and their related new values.
     * @param  array                    $values Copy of the current (partial) state of the object (fields depend on the view).
     * @param  string                   $lang   Language (char 2) in which multilang field are to be processed.
     * @return array                    Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = parent::onchange($om, $event, $values, $lang);

        if(isset($event['address_zip']) && isset($values['address_country'])) {
            $list = self::_getCitiesByZip($event['address_zip'], $values['address_country'], $lang);
            if($list) {
                $result['address_city'] = [
                    'selection' => $list
                ];
            }
        }

        $duplicate_identity_fields_sets = [
                'company'       => ['legal_name', 'address_country'],
                'individual'    => ['firstname', 'lastname', 'address_country']
            ];

        foreach($duplicate_identity_fields_sets as $duplicate_identity_fields) {
            if( count(array_intersect_key($event, array_flip($duplicate_identity_fields))) > 0
                || (isset($event['has_duplicate_clue']) && $event['has_duplicate_clue']) ) {
                $domain = [];
                foreach($duplicate_identity_fields as $field) {
                    $value = $event[$field] ?? $values[$field];
                    if(empty($value)) {
                        continue 2;
                    }
                    $domain[] = [$field, 'ilike', "%{$value}%"];
                }

                $duplicate_identity = null;
                if(!empty($domain)) {
                    $domain[] = ['id', '<>', $values['id']];
                    $identity_ids = $om->search('lodging\identity\Identity', $domain);

                    if($identity_ids > 0 && count($identity_ids)) {
                        $identities = $om->read('lodging\identity\Identity', [$identity_ids[0]], ['id', 'name']);
                        $duplicate_identity = reset($identities);
                    }
                }

                $result['has_duplicate_clue'] = !is_null($duplicate_identity);
                $result['duplicate_clue_identity_id'] = $duplicate_identity;
            }
        }



        if(isset($event['has_duplicate_clue']) && !$event['has_duplicate_clue']) {
            $result['duplicate_clue_identity_id'] = null;
        }

        return $result;
    }

    /**
     * Check whether the identity can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $identities = $om->read(self::getType(), $ids, [ 'bookings_ids' ]);

        if($identities > 0) {
            foreach($identities as $id => $identity) {
                if($identity['bookings_ids'] && count($identity['bookings_ids']) > 0) {
                    return ['bookings_ids' => ['non_removable_identity' => 'Identities relating to one or more bookings cannot be deleted.']];
                }
            }
        }
        return parent::candelete($om, $ids);
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $ids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {

        // prevent updating "CLIENT DE PASSAGE" entries
        if(count(array_intersect($ids, [6, 7, 8, 9, 10, 11, 12, 13, 14])) > 0) {
            return ['id' => ['non_updateable_identity' => 'Temporary identities cannot be updated.']];
        }

        if(isset($values['has_duplicate_clue']) && $values['has_duplicate_clue']) {
            return ['has_duplicate_clue' => ['might_be_duplicate' => 'Cannot save possible duplicate without unchecking.']];
        }

        return parent::canupdate($om, $ids, $values, $lang);
    }

    public static function reCalcIsDuplicate($om, $ids, $values, $lang) {
        $om->update(self::getType(), $ids, ['is_duplicate' => null, 'duplicate_identity_id' => null]);
        $om->read(self::getType(), $ids, ['is_duplicate']);
    }

    public static function getDuplicateIdentityId($om, $ids, $values, $lang) {
        $result = [];
        $duplicate_identity_fields = ['legal_name', 'firstname', 'lastname', 'address_city', 'address_state', 'address_country'];
        $identities = $om->read(self::getType(), $ids, $duplicate_identity_fields, $lang);
        foreach($identities as $id => $identity) {
            if(empty($identity['legal_name']) && empty($identity['firstname']) && empty($identity['lastname'])) {
                continue;
            }

            $domain = [];
            foreach($duplicate_identity_fields as $field) {
                if(!empty($identity[$field])) {
                    $domain[] = [$field, 'ilike', "%{$identity[$field]}%"];
                }
            }

            if(!empty($domain)) {
                $domain[] = ['id', '<', $id];
                if(count($ids) == 1) {
                    $domain[] = ['is_duplicate', '=', false];
                }

                $identity_ids = $om->search('lodging\identity\Identity', $domain);

                if($identity_ids > 0 && count($identity_ids)) {
                    $result[$id] = $identity_ids[0];
                }
            }
        }

        return $result;
    }

    public static function calcIsDuplicate($om, $ids, $lang) {
        $result = [];

        $duplicate_identity_ids = $om->call(self::getType(), 'getDuplicateIdentityId', $ids);
        foreach($ids as $id) {
            if(!isset($duplicate_identity_ids[$id])) {
                $result[$id] = false;
            }
            else {
                $result[$id] = true;
                $om->update(self::getType(), [$id], ['duplicate_identity_id' => $duplicate_identity_ids[$id]], $lang);
            }
        }

        return $result;
    }

    public static function onupdateIsDuplicate($om, $ids, $values, $lang) {
        if(isset($values['is_duplicate']) && !$values['is_duplicate']) {
            $om->update(self::getType(), $ids, ['duplicate_identity_id' => null], $lang);
        }
    }

    private static function _getCitiesByZip($zip, $country='BE', $lang='en') {
        $result = null;
        $file = "packages/identity/i18n/{$lang}/zipcodes/{$country}.json";
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_zip = json_decode($data, true);
            if(isset($map_zip[$zip])) {
                $result = $map_zip[$zip];
            }
        }
        return $result;
    }

    /**
     * Returns the name of the BE region based on a zip code.
     */
    public static function _getRegionByZip($zip, $country) {
        $zip = intval($zip);
        if($country != 'BE') {
            return '';
        }
        if($zip < 1300) {
            return "Région Bruxelles-Capitale";
        }
        elseif($zip >= 1300 && $zip < 1500) {
            return "Région wallonne";
        }
        elseif($zip >= 1500 && $zip < 4000) {
            return "Région flamande";
        }
        elseif($zip >= 4000 && $zip < 8000) {
            return "Région wallonne";
        }
        elseif($zip >= 8000 && $zip < 10000) {
            return "Région flamande";
        }
        return '';
    }

}