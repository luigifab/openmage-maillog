<?php
/**
 * Created W/11/11/2015
 * Updated J/25/11/2021
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2016      | Pierre-Alexandre Rouanet <pierre-alexandre.rouanet~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://www.luigifab.fr/openmage/maillog
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Luigifab_Maillog_Model_System_Emarsys extends Luigifab_Maillog_Model_System {

	// https://dev.emarsys.com/v2/
	protected $_code = 'emarsys';

	protected $_locales = [
		'ar_DZ' => 40, 'ar_EG' => 40, 'ar_KW' => 40, 'ar_MA' => 40, 'ar_SA' => 40,
		'bg_BG' => 22, 'cs_CZ' => 15, 'da_DK' => 12, 'de_AT' => 2,  'de_CH' => 2,
		'de_DE' => 2,  'el_GR' => 45, 'en_AU' => 1,  'en_CA' => 1,  'en_GB' => 1,
		'en_IE' => 1,  'en_NZ' => 1,  'en_US' => 1,  'es_AR' => 8,  'es_CL' => 8,
		'es_CO' => 8,  'es_CR' => 8,  'es_ES' => 8,  'es_MX' => 51, 'es_PA' => 8,
		'es_PE' => 8,  'es_VE' => 8,  'et_EE' => 47, 'fi_FI' => 13, 'fr_CA' => 3,
		'fr_FR' => 3,  'he_IL' => 43, 'hi_IN' => 49, 'hr_HR' => 48, 'hu_HU' => 16,
		'it_CH' => 4,  'it_IT' => 4,  'ja_JP' => 20, 'ko_KR' => 26, 'lt_LT' => 53,
		'lv_LV' => 46, 'mk_MK' => 39, 'nb_NO' => 10, 'nl_NL' => 11, 'nn_NO' => 10,
		'pl_PL' => 17, 'pt_BR' => 50, 'pt_PT' => 14, 'ro_MD' => 23, 'ro_RO' => 18,
		'ru_RU' => 21, 'sk_SK' => 24, 'sl_SI' => 19, 'sr_RS' => 44, 'sv_SE' => 9,
		'th_TH' => 27, 'tr_TR' => 25, 'uk_UA' => 38, 'vi_VN' => 52, 'zh_CN' => 28,
		'zh_HK' => 29, 'zh_TW' => 29
	];

	protected $_countries = [
		'AD' => 4,   'AE' => 183, 'AF' => 1,   'AG' => 6,   'AL' => 2,   'AM' => 8,
		'AN' => 204, 'AO' => 5,   'AR' => 7,   'AT' => 10,  'AU' => 9,   'AZ' => 11,
		'BA' => 22,  'BB' => 15,  'BD' => 14,  'BE' => 17,  'BF' => 27,  'BG' => 26,
		'BH' => 13,  'BI' => 29,  'BJ' => 19,  'BN' => 25,  'BO' => 21,  'BR' => 24,
		'BS' => 12,  'BT' => 20,  'BW' => 23,  'BY' => 16,  'BZ' => 18,  'CA' => 32,
		'CD' => 40,  'CF' => 34,  'CG' => 41,  'CH' => 168, 'CI' => 43,  'CL' => 36,
		'CM' => 31,  'CN' => 37,  'CO' => 38,  'CR' => 42,  'CU' => 45,  'CV' => 33,
		'CY' => 46,  'CZ' => 47,  'DE' => 65,  'DJ' => 49,  'DK' => 48,  'DM' => 50,
		'DO' => 51,  'DZ' => 3,   'EC' => 52,  'EE' => 57,  'EG' => 53,  'EH' => 192,
		'ER' => 56,  'ES' => 162, 'ET' => 58,  'FI' => 60,  'FJ' => 59,  'FM' => 114,
		'FR' => 61,  'GA' => 62,  'GB' => 184, 'GD' => 68,  'GE' => 64,  'GH' => 66,
		'GI' => 203, 'GL' => 198, 'GM' => 63,  'GN' => 70,  'GQ' => 52,  'GR' => 67,
		'GT' => 69,  'GW' => 71,  'GY' => 72,  'HK' => 205, 'HN' => 74,  'HR' => 44,
		'HT' => 73,  'HU' => 75,  'ID' => 78,  'IE' => 81,  'IL' => 82,  'IN' => 77,
		'IQ' => 80,  'IR' => 79,  'IS' => 76,  'IT' => 83,  'JM' => 84,  'JO' => 86,
		'JP' => 85,  'KE' => 88,  'KG' => 93,  'KH' => 30,  'KI' => 89,  'KM' => 39,
		'KN' => 145, 'KP' => 90,  'KR' => 91,  'KW' => 92,  'KZ' => 87,  'LA' => 94,
		'LB' => 96,  'LC' => 146, 'LI' => 100, 'LK' => 163, 'LR' => 98,  'LS' => 97,
		'LT' => 101, 'LU' => 102, 'LV' => 95,  'LY' => 99,  'MA' => 118, 'MC' => 116,
		'MD' => 115, 'ME' => 202, 'MG' => 104, 'MK' => 103, 'ML' => 108, 'MM' => 120,
		'MN' => 117, 'MO' => 206, 'MR' => 111, 'MT' => 109, 'MU' => 112, 'MV' => 107,
		'MW' => 105, 'MX' => 113, 'MY' => 106, 'MZ' => 119, 'NA' => 121, 'NE' => 127,
		'NG' => 128, 'NI' => 126, 'NL' => 124, 'NO' => 129, 'NP' => 123, 'NR' => 122,
		'NZ' => 125, 'OM' => 130, 'PA' => 134, 'PE' => 137, 'PG' => 135, 'PH' => 138,
		'PK' => 131, 'PL' => 139, 'PT' => 140, 'PY' => 136, 'QA' => 141, 'RO' => 142,
		'RS' => 153, 'RU' => 143, 'RW' => 144, 'SA' => 151, 'SC' => 154, 'SD' => 164,
		'SE' => 167, 'SG' => 156, 'SI' => 158, 'SK' => 157, 'SL' => 155, 'SM' => 149,
		'SN' => 152, 'SO' => 160, 'SR' => 165, 'ST' => 150, 'SV' => 54,  'SY' => 169,
		'SZ' => 166, 'TD' => 35,  'TG' => 174, 'TH' => 173, 'TJ' => 171, 'TL' => 258,
		'TM' => 179, 'TN' => 177, 'TO' => 175, 'TR' => 178, 'TT' => 176, 'TV' => 180,
		'TW' => 170, 'TZ' => 172, 'UA' => 182, 'UG' => 181, 'US' => 185, 'UY' => 186,
		'UZ' => 187, 'VA' => 189, 'VC' => 147, 'VE' => 190, 'VN' => 191, 'VU' => 188,
		'WS' => 148, 'YE' => 193, 'ZA' => 161, 'ZM' => 196, 'ZW' => 197
	];


	public function getFields() {

		if (empty($this->_fields)) {

			// https://dev.emarsys.com/v2/fields/list-available-fields
			$result = $this->sendRequest('GET', 'field/translate', substr(Mage::getSingleton('core/locale')->getLocaleCode(), 0, 2));
			$fields = [];

			if ($this->checkResponse($result)) {

				$result   = $this->extractResponseData($result);
				$result   = is_array($result) ? $result : [];
				$readonly = [0, 27, 28, 29, 30, 32, 33, 34, 36, 47, 48];

				foreach ($result as $field) {
					$fields[$field['id']] = [
						'id'       => $field['id'],
						'name'     => $field['name'],
						'readonly' => in_array($field['id'], $readonly)
					];
				}

				ksort($fields);
			}

			$this->_fields = $fields;
		}

		return $this->_fields;
	}

	public function mapFields($object, string $group, bool $onlyAttributes = false) {

		$fields = [];
		if (!is_object($object))
			return $fields;

		$isAddress  = $group == 'address';
		$isCustomer = $group == 'customer';
		$values     = $this->getMapping();

		if ($onlyAttributes)
			$attributes = array_keys($object->getAttributes());

		foreach ($values as $system => $config) {

			foreach ($config as $code) {

				if ($isAddress) {
					if (!empty($object->getData('is_default_billing')))
						$code = str_replace('address_billing_', '', $code);
					if (!empty($object->getData('is_default_shipping')))
						$code = str_replace('address_shipping_', '', $code);
				}

				if ($onlyAttributes) {
					if (in_array($code, $attributes))
						$fields[] = $code;
					continue;
				}

				$hasData = $object->hasData($code);

				if ($isAddress && in_array($code, ['street', 'street_1', 'street_2', 'street_3', 'street_4'])) {
					if ($code == 'street')
						$fields[$system] = implode(', ', $object->getStreet());
					else
						$fields[$system] = $object->getStreet(substr($code, -1));
				}
				else if ($isAddress && ($code == 'country_id')) {
					$value = $object->getData('country_id');
					if (array_key_exists($value, $this->_countries))
						$fields[$system] = $this->_countries[$value];
					else
						$fields[$system] = '';
				}
				else if ($code == 'subscriber_status') {
					$fields[$system] = ($object->getData('subscriber_status') == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) ? 1 : 2;
				}
				else if ($isCustomer && ($code == 'email')) {
					$fields[$system] = $object->getData('email');
					// avec changement d'email (le champ 3 est l'email sur Emarsys)
					$special = Mage::getStoreConfig('maillog_sync/'.$this->_code.'/mapping_customerid_field');
					$fields['key_id'] = (!empty($special) && ($object->getOrigData('email') != $object->getData('email'))) ? $special : 3;
				}
				else if ($isCustomer && ($code == 'store_id')) {
					$value = $object->getStoreId();
					$check = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $value);
					$fields[$system] = (!empty($value) && array_key_exists($check, $this->_locales)) ? $this->_locales[$check] : '';
				}
				else if ($isCustomer && ($code == 'dob') && $hasData) {
					$fields[$system] = date('Y-m-d', strtotime($object->getData('dob')));
				}
				else if ($hasData) {
					if (!empty($fields[$system])) {
						$fields[$system] .= ' '.$object->getData($code);
					}
					else {
						// 2016-02-26T10:31:11+00:00
						// 2016-02-26 10:32:28
						$fields[$system] = $object->getData($code);
						if (preg_match('#^\d{4}.\d{2}.\d{2}.\d{2}.\d{2}.\d{2}#', $fields[$system]) === 1)
							$fields[$system] = date('Y-m-d H:i:s', strtotime($fields[$system]));
					}
				}
			}
		}

		return $fields;
	}


	public function updateCustomer(array $data) {

		// https://dev.emarsys.com/v2/contacts/update-contacts
		$method = empty($data['key_id']) ? 'contact/create_if_not_exists=1' : 'contact/create_if_not_exists=1&key_id='.$data['key_id'];
		return $this->sendRequest('PUT', $method, $data);
	}

	public function deleteCustomer(array $data) {

		// https://dev.emarsys.com/v2/contacts/delete-contact
		return $this->sendRequest('POST', 'contact/delete', $data);
	}


	public function extractResponseData($data, bool $forHistory = false) {

		// dans les entêtes il pourrait y avoir "report-to: {"endp..."
		if (($pos = mb_stripos($data, "\n{")) !== false) {
			$json = json_decode(mb_substr($data, $pos + 1), true);
			$json = empty($json['data']) ? $json : $json['data'];
		}

		return empty($json) ? $data : $json;
	}

	protected function sendRequest(string $type, string $method, $data = null) {

		$url = Mage::getStoreConfig('maillog_sync/'.$this->_code.'/api_url');
		if (empty($url))
			return null;

		$timestamp = gmdate('c');
		$nonce     = md5(random_int(1000000000000000, 9999999999999999));
		$password  = Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/'.$this->_code.'/api_password'));
		$password  = base64_encode(sha1($nonce.$timestamp.$password));

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_HEADER, true);

		$override = null;
		if ($type == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			if (!empty($data))
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else if ($type == 'PUT') {
			// https://gridpane.com/kb/making-nginx-accept-put-delete-and-patch-verbs/
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			//curl_setopt($ch, CURLOPT_POST, true);
			//$override = 'X-HTTP-Method-Override: PUT';
			if (!empty($data))
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else if ($type == 'DELETE') {
			// https://gridpane.com/kb/making-nginx-accept-put-delete-and-patch-verbs/
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			//curl_setopt($ch, CURLOPT_POST, true);
			//$override = 'X-HTTP-Method-Override: DELETE';
			if (!empty($data))
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else if ($type == 'GET') {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
			if (!empty($data))
				$method .= '/'.$data;
		}

		curl_setopt($ch, CURLOPT_URL, $url.$method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json; charset="utf-8"',
			'X-WSSE: UsernameToken '.
				'Username="'.Mage::getStoreConfig('maillog_sync/'.$this->_code.'/api_username').'", '.
				'PasswordDigest="'.$password.'", Nonce="'.$nonce.'", Created="'.$timestamp.'"',
			$override
		]);

		$result = curl_exec($ch);
		$result = (($result === false) || (curl_errno($ch) !== 0)) ? trim('CURL_ERROR '.curl_errno($ch).' '.curl_error($ch)) : $result;
		curl_close($ch);

		return $result;
	}
}