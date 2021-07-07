<?php
/**
 * Created J/18/01/2018
 * Updated V/18/06/2021
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Model_System_Dolist implements Luigifab_Maillog_Model_Interface {

	// https://api.dolist.net/doc/

	// liste des pays par rapport à ce qui existe sur Dolist
	protected $countries = [
		'FR' => 1,   'BE' => 2,   'NL' => 3,   'DE' => 4,   'IT' => 5,   'GB' => 6,
		'IE' => 7,   'DK' => 8,   'GR' => 9,   'PT' => 10,  'ES' => 11,  'LU' => 23,
		'IS' => 24,  'FO' => 25,  'NO' => 28,  'SE' => 30,  'FI' => 32,  'CH' => 36,
		'LI' => 37,  'AT' => 38,  'AD' => 43,  'GI' => 44,  'VA' => 45,  'MT' => 46,
		'TR' => 52,  'EE' => 53,  'LV' => 54,  'LT' => 55,  'PL' => 60,  'CZ' => 61,
		'SK' => 63 , 'HU' => 64,  'RO' => 66,  'BG' => 68,  'AL' => 70,  'UA' => 72,
		'BY' => 73,  'MD' => 74,  'RU' => 75,  'GE' => 76,  'AM' => 77,  'AZ' => 78,
		'KZ' => 79,  'TM' => 80,  'UZ' => 81,  'TJ' => 82,  'KG' => 83,  'SI' => 91,
		'HR' => 92,  'BA' => 93,  'RS' => 94,  'MK' => 96,  'MA' => 204, 'DZ' => 208,
		'TN' => 212, 'LY' => 216, 'EG' => 220, 'SD' => 224,              'MR' => 228,
		'ML' => 232, 'BF' => 236, 'NE' => 240, 'TD' => 244, 'CV' => 247, 'SN' => 248,
		'GM' => 252, 'GW' => 257, 'GN' => 260, 'SL' => 264, 'LR' => 268, 'CI' => 272,
		'GH' => 276, 'TG' => 280, 'BJ' => 284, 'NG' => 288, 'CM' => 302, 'CF' => 306,
		'GQ' => 310, 'GA' => 314, 'RW' => 324, 'BI' => 328, 'AO' => 330, 'ET' => 334,
		'ER' => 336, 'DJ' => 338, 'SO' => 342, 'KE' => 346, 'UG' => 350, 'TZ' => 352,
		'SC' => 355, 'MZ' => 366, 'MG' => 370, 'RE' => 372, 'KM' => 375, 'YT' => 377,
		'ZM' => 378, 'ZW' => 382, 'MW' => 386, 'ZA' => 388, 'NA' => 389, 'BW' => 391,
		'SZ' => 393, 'LS' => 395, 'US' => 400, 'CA' => 404, 'GL' => 406, 'PM' => 408,
		'MX' => 412, 'BM' => 413, 'GT' => 416, 'BZ' => 421, 'HN' => 424, 'SV' => 428,
		'NI' => 432, 'CR' => 436, 'PA' => 442, 'AI' => 446, 'CU' => 448, 'KN' => 449,
		'HT' => 452, 'BS' => 453, 'PR' => 455, 'DO' => 456, 'BL' => 457, 'GP' => 458,
		'AG' => 459, 'DM' => 460, 'MS' => 461, 'MQ' => 462, 'JM' => 464, 'LC' => 465,
		'VC' => 467, 'BB' => 469, 'TT' => 472, 'GD' => 473, 'AW' => 474, 'CO' => 480,
		'VE' => 484, 'GY' => 488, 'SR' => 492, 'GF' => 496, 'EC' => 500, 'PE' => 504,
		'BR' => 508, 'CL' => 512, 'BO' => 516, 'PY' => 520, 'UY' => 524, 'AR' => 528,
		'CY' => 600, 'LB' => 604, 'SY' => 608, 'IQ' => 612, 'IR' => 616, 'IL' => 624,
		'JO' => 628, 'SA' => 632, 'KW' => 636, 'BH' => 640, 'QA' => 644, 'AE' => 647,
		'OM' => 649, 'YE' => 653, 'AF' => 660, 'PK' => 662, 'IN' => 664, 'BD' => 666,
		'MV' => 667, 'LK' => 669, 'NP' => 672, 'BT' => 675, 'MM' => 676, 'TH' => 680,
		'LA' => 684, 'VN' => 690, 'KH' => 696, 'ID' => 700, 'MY' => 701, 'BN' => 703,
		'SG' => 706, 'PH' => 708, 'MN' => 716, 'CN' => 720, 'KP' => 724, 'KR' => 728,
		'JP' => 732, 'TW' => 736, 'HK' => 740, 'MO' => 743, 'AU' => 800, 'PG' => 801,
		'NR' => 803, 'NZ' => 804, 'SB' => 806, 'TV' => 807, 'NC' => 809, 'WF' => 811,
		'KI' => 812, 'PN' => 813, 'FJ' => 815, 'VU' => 816, 'TO' => 817, 'WS' => 819,
		'PF' => 822, 'FM' => 823, 'MH' => 824, 'MC' => 825, 'ST' => 311, 'CG' => 318,
		'MU' => 373, 'TC' => 454, 'KY' => 463, 'FK' => 529
	];


	// gestion des champs
	public function getMapping() {
		return array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog_sync/emarsys/mapping_config')));
	}

	public function getFields() {

		// https://api.dolist.net/doc/CustomFields#GetFieldList
		$result = $this->sendRequest('../CustomFieldManagementService', 'GetFieldList', ['request' => []]);
		$fields = [];

		if ($this->checkResponse($result)) {

			$result = $this->extractResponseData($result);

			$fields['Email']       = ['id' => 'Email',       'name' => 'Email',       'readonly' => false];
			$fields['OptoutEmail'] = ['id' => 'OptoutEmail', 'name' => 'OptoutEmail', 'readonly' => false];

			foreach ($result as $field) {
				$fields[$field['ID']] = [
					'id'       => $field['Name'],
					'name'     => $field['Title'],
					'readonly' => false
				];
			}

			ksort($fields);
		}

		return $fields;
	}

	public function mapFields(object $object) {

		if (!is_object($object))
			return [];

		$customer   = get_class(Mage::getModel('customer/customer'));
		$address    = get_class(Mage::getModel('customer/address'));
		$subscriber = get_class(Mage::getModel('newsletter/subscriber'));
		$current    = get_class($object);

		$isAddress  = $current == $address;
		$isCustomer = $current == $customer;
		$isSubscrib = $current == $subscriber;

		$mapping = $this->getMapping();
		$fields  = [];

		foreach ($mapping as $config) {

			if ((mb_stripos($config, ':') !== false) && (mb_strlen($config) > 3) && (mb_stripos($config, '#') !== 0)) {

				$config = explode(':', $config);
				$system = trim(array_shift($config));

				foreach ($config as $key) {

					$code    = trim($key);
					$hasData = $object->hasData($code);

					if ($isAddress) {
						if (!empty($object->getData('is_default_billing')))
							$code = str_replace('address_billing_', '', $code);
						if (!empty($object->getData('is_default_shipping')))
							$code = str_replace('address_shipping_', '', $code);
					}

					if ($isAddress && in_array($code, ['street', 'street_1', 'street_2', 'street_3', 'street_4'])) {
						if ($code == 'street')
							$fields[$system] = ['Name' => $system, 'Value' => implode(', ', $object->getStreet())];
						else
							$fields[$system] = ['Name' => $system, 'Value' => $object->getStreet(mb_substr($code, -1))];
					}
					else if ($isAddress && ($code == 'country_id')) {
						$value = $object->getData($code);
						if (array_key_exists($value, $this->countries))
							$fields[$system] = ['Name' => $system, 'Value' => $this->countries[$value]];
						else
							$fields[$system] = ['Name' => $system, 'Value' => 999]; // = Autres pays
					}
					else if ($isSubscrib && ($code == 'subscriber_status')) {
						// 0 Non inscrit (un client avec commande jamais inscrit à la newsletter)
						// 1 Inscrit
						// 2 Désinscrit (opt-out)
						// 3 En erreur sur status dolist
						$fields[$system] = [
							'Name'  => $system,
							'Value' => ($object->getData($code) == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) ? 1 : 2
						];
						if (empty($object->getId()))
							$fields[$system]['Value'] = 0;
						// 0 Inscrit
						// 1 Désinscrit
						$fields['OptoutEmail'] = ($object->getData($code) == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) ? 0 : 1;
					}
					else if ($isCustomer && ($code == 'email')) {
						$fields['Email'] = $object->getData('email');
						// avec changement d'email
						if ($object->getOrigData('email') != $object->getData('email')) {
							$fields['EmailOld'] = $object->getOrigData('email');
							$fields['Email']    = ['Name' => 'email', 'Value' => $object->getData('email')];
						}
					}
					else if ($isCustomer && ($code == 'store_id')) {
						$value = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $object->getStoreId());
						// spécial
						$fields[$system] = ['Name' => $system, 'Value' => $value];
					}
					else if ($isCustomer && ($code == 'dob') && $hasData) {
						$fields[$system] = ['Name' => $system, 'Value' => date('Y-m-d', strtotime($object->getData($code)))];
					}
					else if ($hasData) {
						if (!empty($fields[$system]['Value'])) {
							$fields[$system]['Value'] .= ' '.$object->getData($code);
						}
						else {
							// 2016-02-26T10:31:11+00:00
							// 2016-02-26 10:32:28
							$fields[$system] = ['Name' => $system, 'Value' => $object->getData($code)];
							if (preg_match('#^\d{4}.\d{2}.\d{2}.\d{2}.\d{2}.\d{2}#', $fields[$system]['Value']) === 1)
								$fields[$system] = ['Name' => $system, 'Value' => date('Y-m-d H:i:s', strtotime($fields[$system]['Value']))];
						}
					}

					// (int) sur les champs int
					if (isset($fields[$system]) && (mb_stripos($system, 'int') !== false))
						$fields[$system]['Value'] = (int) $fields[$system]['Value'];
				}
			}
		}

		return $fields;
	}


	// gestion des données
	public function updateCustomer(array &$data) {

		// déplace les données en prennant soin de conserver Email et OptoutEmail
		// [Email => string, 'Fields' => array, OptoutEmail => int]
		// avec changement d'email ou pas
		if (!empty($data['EmailOld'])) {
			$data = ['Email' => is_array($data['EmailOld']) ? $data['EmailOld']['Value'] : $data['EmailOld'], 'Fields' => $data];
			if (isset($data['Fields']['OptoutEmail']))
				$data['OptoutEmail'] = $data['Fields']['OptoutEmail'];
			unset($data['Fields']['email'], $data['Fields']['EmailOld'], $data['Fields']['OptoutEmail']);
		}
		else {
			$data = ['Email' => is_array($data['Email']) ? $data['Email']['Value'] : $data['Email'], 'Fields' => $data];
			if (isset($data['Fields']['OptoutEmail']))
				$data['OptoutEmail'] = $data['Fields']['OptoutEmail'];
			unset($data['Fields']['email'], $data['Fields']['Email'], $data['Fields']['EmailOld'], $data['Fields']['OptoutEmail']);
		}

		// fait sauter les clés du tableau, mais c'est totalement voulu
		sort($data['Fields']);
		$data['Fields'] = array_filter($data['Fields']);

		// https://api.dolist.net/doc/Contact#SaveContact
		$result = $this->sendRequest('ContactManagementService', 'SaveContact', ['contact' => $data]);
		if ($this->checkResponse($result)) {

			// on récupère un ticket si tout va bien
			$response = $this->extractResponseData($result);
			if (!empty($response['SaveContactResult'])) {

				$ticket = $response['SaveContactResult'];
				while (true) {

					sleep(1);

					// on vérifie l'état du ticket
					// on s'arrête dès que le taitement est terminé (ie != -1)
					// on s'arrête également en cas d'erreur
					$result = $this->sendRequest('ContactManagementService', 'GetStatusByTicket', ['ticket' => $ticket]);
					if ($this->checkResponse($result)) {
						$response = $this->extractResponseData($result);
						if (!isset($response['GetStatusByTicketResult']['ReturnCode']) || ($response['GetStatusByTicketResult']['ReturnCode'] != -1))
							return $result;
					}
					else {
						return $result;
					}
				}
			}
		}

		return $result;
	}

	public function deleteCustomer(array &$data) {
		return ['error' => 'not supported by api'];
	}

	public function updateCustomers(array &$data) {
		return ['error' => 'not supported by api'];
	}


	// traitement des requêtes
	public function checkResponse($data) {

		if (is_object($data)) {

			// updateCustomer
			// https://api.dolist.net/doc/Contact#SavedContactInfo
			if (isset($data->GetStatusByTicketResult->ReturnCode) && !in_array($data->GetStatusByTicketResult->ReturnCode, [-1, 1]))
				return false;

			return true;
		}

		return false;
	}

	public function extractResponseData($data, bool $forHistory = false, bool $multiple = false) {

		if (is_object($data)) {

			// getFields
			// https://api.dolist.net/doc/CustomFields#GetFieldList
			if (!empty($data->GetFieldListResult->FieldList->Field))
				$data = $data->GetFieldListResult->FieldList->Field;

			// updateCustomer
			// https://api.dolist.net/doc/Contact#SaveContact
			if ($forHistory && !empty($data->GetStatusByTicketResult->ReturnCode) && ($data->GetStatusByTicketResult->ReturnCode == 1))
				return ['memberid' => $data->GetStatusByTicketResult->MemberId];
		}

		return json_decode(json_encode($data), true);
	}

	private function sendRequest(string $service, string $method, array $data) {

		$url = Mage::getStoreConfig('maillog_sync/dolist/api_url');
		if (empty($url))
			return null;

		try {
			//ini_set('soap.wsdl_cache_enabled', 0);
			ini_set('default_socket_timeout', 20);

			if (!isset($this->auth) || (!empty($this->auth->GetAuthenticationTokenResult->DeprecatedDate) &&
			    ((strtotime($this->auth->GetAuthenticationTokenResult->DeprecatedDate) + 30) > time()))) {

				$proxy    = $url.'AuthenticationService.svc?wsdl';
				$location = $url.'AuthenticationService.svc/soap1.1';
				$client   = new SoapClient($proxy, ['trace' => 1, 'location' => $location]);

				$this->auth = $client->GetAuthenticationToken([
					'authenticationRequest' => [
						'AuthenticationKey' => Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/dolist/api_password')),
						'AccountID'         => Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/dolist/api_username'))
					]
				]);
			}

			$proxy    = $url.$service.'.svc?wsdl';
			$location = $url.$service.'.svc/soap1.1';
			$client   = new SoapClient($proxy, ['trace' => 1, 'location' => $location]);

			ini_restore('default_socket_timeout');

			return $client->{$method}([
				'token' => [
					'AccountID' => Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/dolist/api_username')),
					'Key'       => $this->auth->GetAuthenticationTokenResult->Key
				]
			] + $data);
		}
		catch (Throwable $t) {
			return $t->getMessage();
		}
	}
}