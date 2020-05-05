<?php
/**
 * Created W/11/11/2015
 * Updated L/27/04/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2016      | Pierre-Alexandre Rouanet <pierre-alexandre.rouanet~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
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

	// https://help.emarsys.com/hc/fr/articles/115004499373-Overview-of-Contact-Management-Endpoints
	// https://api.emarsys.net/api-demo/

	// liste des locales par rapport à ce qui existe sur Emarsys
	protected $locales = [
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

	// liste des pays par rapport à ce qui existe sur Emarsys
	protected $countries = [
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


	// gestion des champs
	public function getFields() {

		// https://help.emarsys.com/hc/fr/articles/115004466193-Listing-Available-Fields
		$result = $this->sendRequest('GET', 'field/translate', mb_substr(Mage::getSingleton('core/locale')->getLocaleCode(), 0, 2));
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

		return $fields;
	}

	public function mapFields($object) {

		if (!is_object($object))
			return [];

		$customer   = get_class(Mage::getModel('customer/customer'));
		$address    = get_class(Mage::getModel('customer/address'));
		$subscriber = get_class(Mage::getModel('newsletter/subscriber'));
		$current    = get_class($object);

		$isAddress  = $current == $address;
		$isCustomer = $current == $customer;
		$isSubscrib = $current == $subscriber;

		$special = Mage::getStoreConfig('maillog_sync/general/mapping_customerid_field'); // en cas de changement d'email
		$mapping = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog_sync/general/mapping_config')));
		$fields  = [];

		foreach ($mapping as $config) {

			if ((mb_stripos($config, ':') !== false) && (mb_strlen($config) > 3) && (mb_stripos($config, '#') !== 0)) {

				$config = explode(':', $config);
				$system = trim(array_shift($config));

				foreach ($config as $key) {

					$magento = trim($key);
					$hasData = $object->hasData($magento);

					if ($isAddress) {
						if (!empty($object->getData('is_default_billing')))
							$magento = str_replace('address_billing_', '', $magento);
						if (!empty($object->getData('is_default_shipping')))
							$magento = str_replace('address_shipping_', '', $magento);
					}

					if ($isAddress && in_array($magento, ['street', 'street_1', 'street_2', 'street_3', 'street_4'])) {
						if ($magento == 'street')
							$fields[$system] = implode(', ', $object->getStreet());
						else
							$fields[$system] = $object->getStreet(mb_substr($magento, -1));
					}
					else if ($isAddress && ($magento == 'country_id')) {
						$value = $object->getData($magento);
						if (array_key_exists($value, $this->countries))
							$fields[$system] = $this->countries[$value];
						else
							$fields[$system] = '';
					}
					else if ($isSubscrib && ($magento == 'subscriber_status')) {
						$fields[$system] = ($object->getData($magento) == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) ? 1 : 2;
					}
					else if ($isCustomer && ($magento == 'email')) {
						$fields[$system] = $object->getData($magento);
						// avec changement d'email (le champs 3 est l'email sur Emarsys)
						$fields['key_id'] = (!empty($special) && ($object->getOrigData($magento) != $object->getData($magento))) ?
							$special : 3;
					}
					else if ($isCustomer && ($magento == 'store_id')) {
						$value = $object->getStoreId();
						// spécial
						if (!empty($value) && array_key_exists(Mage::getStoreConfig('general/locale/code', $value), $this->locales))
							$fields[$system] = $this->locales[Mage::getStoreConfig('general/locale/code', $value)];
						else
							$fields[$system] = '';
					}
					else if ($isCustomer && ($magento == 'dob') && $hasData) {
						$fields[$system] = date('Y-m-d', strtotime($object->getData($magento)));
					}
					else if ($hasData) {
						if (!empty($fields[$system])) {
							$fields[$system] .= ' '.$object->getData($magento);
						}
						else {
							// 2016-02-26T10:31:11+00:00
							// 2016-02-26 10:32:28
							$fields[$system] = $object->getData($magento);
							if (preg_match('#^\d{4}.\d{2}.\d{2}.\d{2}.\d{2}.\d{2}#', $fields[$system]) === 1)
								$fields[$system] = date('Y-m-d H:i:s', strtotime($fields[$system]));
						}
					}
				}
			}
		}

		return $fields;
	}


	// gestion des données
	public function updateCustomer(&$data) {

		// https://help.emarsys.com/hc/fr/articles/115004492994-Creating-a-New-Contact
		// https://help.emarsys.com/hc/fr/articles/115004494794-Updating-a-Contact
		$method = empty($data['key_id']) ? 'contact/create_if_not_exists=1' : 'contact/create_if_not_exists=1&key_id='.$data['key_id'];
		return $this->sendRequest('PUT', $method, $data);
	}

	public function deleteCustomer(&$data) {

		// https://help.emarsys.com/hc/fr/articles/115004465793-Deleting-a-Contact
		return $this->sendRequest('POST', 'contact/delete', $data);
	}

	public function updateCustomers(&$data) {

		// https://help.emarsys.com/hc/fr/articles/115004493054-Creating-Multiple-Contacts
		// https://help.emarsys.com/hc/fr/articles/115004494854-Updating-Multiple-Contacts
		return $this->sendRequest('PUT', 'contact/create_if_not_exists=1', ['contacts' => $data]);
	}


	// traitement des requêtes
	public function checkResponse($data) {
		return mb_stripos($data, 'HTTP/1.1 200') === 0;
	}

	public function extractResponseData($data, $forHistory = false, $multiple = false) {

		if (mb_stripos($data, '{') !== false) {
			$data = mb_substr($data, mb_stripos($data, '{'));
			$data = json_decode($data, true);
			$data = empty($data['data']) ? $data : $data['data'];
		}

		return $data;
	}

	private function sendRequest($type, $method, $data) {

		$timestamp = gmdate('c');
		$nonce     = md5(random_int(1000000000000000, 9999999999999999));
		$password  = Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/general/api_password'));
		$password  = base64_encode(sha1($nonce.$timestamp.$password));

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_TIMEOUT, 18);
		curl_setopt($ch, CURLOPT_HEADER, true);

		switch ($type) {
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				break;
			case 'PUT':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				break;
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				break;
			case 'GET':
				$method = (mb_stripos($method, '?') === false) ? $method.'/'.$data : str_replace('?', '/'.$data.'?', $method);
				curl_setopt($ch, CURLOPT_HTTPGET, 1);
				break;
		}

		curl_setopt($ch, CURLOPT_URL, Mage::getStoreConfig('maillog_sync/general/api_url').$method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'X-WSSE: UsernameToken '.
				'Username="'.Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/general/api_username')).'", '.
				'PasswordDigest="'.$password.'", Nonce="'.$nonce.'", Created="'.$timestamp.'"',
			'Content-Type: application/json; charset="utf-8"'
		]);

		$response = curl_exec($ch);
		$response = ((curl_errno($ch) !== 0) || ($response === false)) ? 'CURL_ERROR_'.curl_errno($ch).' '.curl_error($ch) : $response;
		curl_close($ch);

		return $response;
	}
}