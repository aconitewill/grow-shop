<?php
/**
 * Countries Stripe can ship to, and the regions a shipping zone can use.
 *
 * @package GrowShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * ISO code => name. Codes Stripe Checkout won't accept for shipping are
 * left out.
 *
 * @return array
 */
function grs_countries() {
	return array(
		'AC' => 'Ascension Island', 'AD' => 'Andorra', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AG' => 'Antigua and Barbuda', 'AI' => 'Anguilla', 'AL' => 'Albania', 'AM' => 'Armenia', 'AO' => 'Angola', 'AQ' => 'Antarctica', 'AR' => 'Argentina', 'AT' => 'Austria', 'AU' => 'Australia', 'AW' => 'Aruba', 'AX' => 'Åland Islands', 'AZ' => 'Azerbaijan',
		'BA' => 'Bosnia and Herzegovina', 'BB' => 'Barbados', 'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Bahrain', 'BI' => 'Burundi', 'BJ' => 'Benin', 'BL' => 'Saint Barthélemy', 'BM' => 'Bermuda', 'BN' => 'Brunei', 'BO' => 'Bolivia', 'BQ' => 'Caribbean Netherlands', 'BR' => 'Brazil', 'BS' => 'Bahamas', 'BT' => 'Bhutan', 'BV' => 'Bouvet Island', 'BW' => 'Botswana', 'BY' => 'Belarus', 'BZ' => 'Belize',
		'CA' => 'Canada', 'CD' => 'Congo (DRC)', 'CF' => 'Central African Republic', 'CG' => 'Congo', 'CH' => 'Switzerland', 'CI' => 'Côte d\'Ivoire', 'CK' => 'Cook Islands', 'CL' => 'Chile', 'CM' => 'Cameroon', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CV' => 'Cape Verde', 'CW' => 'Curaçao', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
		'DE' => 'Germany', 'DJ' => 'Djibouti', 'DK' => 'Denmark', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'DZ' => 'Algeria',
		'EC' => 'Ecuador', 'EE' => 'Estonia', 'EG' => 'Egypt', 'EH' => 'Western Sahara', 'ER' => 'Eritrea', 'ES' => 'Spain', 'ET' => 'Ethiopia',
		'FI' => 'Finland', 'FJ' => 'Fiji', 'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands', 'FR' => 'France',
		'GA' => 'Gabon', 'GB' => 'United Kingdom', 'GD' => 'Grenada', 'GE' => 'Georgia', 'GF' => 'French Guiana', 'GG' => 'Guernsey', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GL' => 'Greenland', 'GM' => 'Gambia', 'GN' => 'Guinea', 'GP' => 'Guadeloupe', 'GQ' => 'Equatorial Guinea', 'GR' => 'Greece', 'GS' => 'South Georgia', 'GT' => 'Guatemala', 'GU' => 'Guam', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
		'HK' => 'Hong Kong', 'HN' => 'Honduras', 'HR' => 'Croatia', 'HT' => 'Haiti', 'HU' => 'Hungary',
		'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel', 'IM' => 'Isle of Man', 'IN' => 'India', 'IO' => 'British Indian Ocean Territory', 'IQ' => 'Iraq', 'IS' => 'Iceland', 'IT' => 'Italy',
		'JE' => 'Jersey', 'JM' => 'Jamaica', 'JO' => 'Jordan', 'JP' => 'Japan',
		'KE' => 'Kenya', 'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia', 'KI' => 'Kiribati', 'KM' => 'Comoros', 'KN' => 'Saint Kitts and Nevis', 'KR' => 'South Korea', 'KW' => 'Kuwait', 'KY' => 'Cayman Islands', 'KZ' => 'Kazakhstan',
		'LA' => 'Laos', 'LB' => 'Lebanon', 'LC' => 'Saint Lucia', 'LI' => 'Liechtenstein', 'LK' => 'Sri Lanka', 'LR' => 'Liberia', 'LS' => 'Lesotho', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'LY' => 'Libya',
		'MA' => 'Morocco', 'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Montenegro', 'MF' => 'Saint Martin', 'MG' => 'Madagascar', 'MK' => 'North Macedonia', 'ML' => 'Mali', 'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macao', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MS' => 'Montserrat', 'MT' => 'Malta', 'MU' => 'Mauritius', 'MV' => 'Maldives', 'MW' => 'Malawi', 'MX' => 'Mexico', 'MY' => 'Malaysia', 'MZ' => 'Mozambique',
		'NA' => 'Namibia', 'NC' => 'New Caledonia', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NP' => 'Nepal', 'NR' => 'Nauru', 'NU' => 'Niue', 'NZ' => 'New Zealand',
		'OM' => 'Oman',
		'PA' => 'Panama', 'PE' => 'Peru', 'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea', 'PH' => 'Philippines', 'PK' => 'Pakistan', 'PL' => 'Poland', 'PM' => 'Saint Pierre and Miquelon', 'PN' => 'Pitcairn Islands', 'PR' => 'Puerto Rico', 'PS' => 'Palestine', 'PT' => 'Portugal', 'PY' => 'Paraguay',
		'QA' => 'Qatar',
		'RE' => 'Réunion', 'RO' => 'Romania', 'RS' => 'Serbia', 'RU' => 'Russia', 'RW' => 'Rwanda',
		'SA' => 'Saudi Arabia', 'SB' => 'Solomon Islands', 'SC' => 'Seychelles', 'SE' => 'Sweden', 'SG' => 'Singapore', 'SH' => 'Saint Helena', 'SI' => 'Slovenia', 'SJ' => 'Svalbard and Jan Mayen', 'SK' => 'Slovakia', 'SL' => 'Sierra Leone', 'SM' => 'San Marino', 'SN' => 'Senegal', 'SO' => 'Somalia', 'SR' => 'Suriname', 'SS' => 'South Sudan', 'ST' => 'São Tomé and Príncipe', 'SV' => 'El Salvador', 'SX' => 'Sint Maarten', 'SZ' => 'Eswatini',
		'TA' => 'Tristan da Cunha', 'TC' => 'Turks and Caicos Islands', 'TD' => 'Chad', 'TF' => 'French Southern Territories', 'TG' => 'Togo', 'TH' => 'Thailand', 'TJ' => 'Tajikistan', 'TK' => 'Tokelau', 'TL' => 'Timor-Leste', 'TM' => 'Turkmenistan', 'TN' => 'Tunisia', 'TO' => 'Tonga', 'TR' => 'Türkiye', 'TT' => 'Trinidad and Tobago', 'TV' => 'Tuvalu', 'TW' => 'Taiwan', 'TZ' => 'Tanzania',
		'UA' => 'Ukraine', 'UG' => 'Uganda', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan',
		'VA' => 'Vatican City', 'VC' => 'Saint Vincent and the Grenadines', 'VE' => 'Venezuela', 'VG' => 'British Virgin Islands', 'VN' => 'Vietnam', 'VU' => 'Vanuatu',
		'WF' => 'Wallis and Futuna', 'WS' => 'Samoa',
		'XK' => 'Kosovo',
		'YE' => 'Yemen', 'YT' => 'Mayotte',
		'ZA' => 'South Africa', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
	);
}

/**
 * Regions a zone can be set to.
 *
 * @return array slug => label
 */
function grs_regions() {
	return array(
		'uk'     => __( 'UK (with the Channel Islands and Isle of Man)', 'grow-shop' ),
		'europe' => __( 'Europe (not the UK)', 'grow-shop' ),
		'world'  => __( 'Everywhere not in another zone', 'grow-shop' ),
		'custom' => __( 'My own list of countries', 'grow-shop' ),
	);
}

/**
 * The countries in a zone. "Everywhere else" is every country not named
 * by another zone.
 *
 * @param int $index Zone index.
 * @return string[] ISO codes.
 */
function grs_zone_countries( $index ) {
	$zones = (array) grs_get( 'shipping', 'zones' );
	if ( ! isset( $zones[ $index ] ) ) {
		return array();
	}
	$zone = $zones[ $index ];
	if ( 'world' === $zone['region'] ) {
		$taken = array();
		foreach ( $zones as $i => $z ) {
			if ( $i !== $index && 'world' !== $z['region'] ) {
				$taken = array_merge( $taken, grs_region_countries( $z['region'], $z['custom'] ) );
			}
		}
		return array_values( array_diff( array_keys( grs_countries() ), $taken ) );
	}
	return grs_region_countries( $zone['region'], $zone['custom'] );
}

/**
 * The countries in a fixed region.
 *
 * @param string $region Region slug.
 * @param string $custom Custom list of codes.
 * @return string[]
 */
function grs_region_countries( $region, $custom = '' ) {
	switch ( $region ) {
		case 'uk':
			return array( 'GB', 'GG', 'JE', 'IM' );
		case 'europe':
			return array( 'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO', 'FR', 'GI', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'SE', 'SI', 'SJ', 'SK', 'SM', 'UA', 'VA', 'XK' );
		case 'custom':
			$known = grs_countries();
			$codes = array();
			foreach ( preg_split( '/[\s,;]+/', strtoupper( (string) $custom ) ) as $code ) {
				if ( isset( $known[ $code ] ) ) {
					$codes[] = $code;
				}
			}
			return array_values( array_unique( $codes ) );
	}
	return array();
}
