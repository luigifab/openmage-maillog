<?php
/**
 * Created J/23/09/2021
 * Updated J/21/09/2023
 *
 * Copyright 2015-2023 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://github.com/luigifab/openmage-maillog
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

class Luigifab_Maillog_Model_System_Mautic extends Luigifab_Maillog_Model_System {

	// https://developer.mautic.org/#rest-api
	protected $_code = 'mautic';
	protected $_regions = [];


	public function getFields() {

		if (empty($this->_fields)) {

			// https://developer.mautic.org/#list-available-fields
			$result = $this->sendRequest('GET', 'contacts/list/fields');
			$fields = [];

			if ($this->checkResponse($result)) {

				$result = $this->extractResponseData($result);
				$result = is_array($result) ? $result : [];

				foreach ($result as $field) {
					$fields[$field['id']] = [
						'id'       => $field['type'].'_'.$field['alias'],
						'name'     => $field['group'].' / '.$field['object'].' / '.$field['label'],
						'readonly' => false
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

			$type   = substr($system, 0, strpos($system, '_'));  // not mb_substr mb_strpos
			$system = substr($system, strpos($system, '_') + 1); // not mb_substr mb_strpos

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

				if ($isAddress && in_array($code, ['street', 'street_1', 'street_2', 'street_3', 'street_4', 'country_id', 'region'])) {
					if ($code == 'region') {

						$country = Mage::getSingleton('core/locale')->getLocale()->getTranslation($object->getData('country_id'), 'country', 'en_US');
						$country = str_replace('Russia', 'Russian Federation', $country);
						$region  = $object->getData('region');

						if (empty($this->_regions))
							$this->_regions = @json_decode(file_get_contents(Mage::getModuleDir('etc', 'Luigifab_Maillog').'/mautic-regions.json'), true);

						if (isset($this->_regions[$country])) {
							if (in_array($region, $this->_regions[$country])) {
								$fields[$system] = $region;
							}
							else if (!empty($region)) {
								$region = transliterator_transliterate('Any-Latin; Latin-ASCII; [^\u001F-\u007f] remove', $region);
								if ($object->getData('country_id') == 'RU')
									$region = str_replace('skaa', 'skaya', $region);
								$fields[$system] = in_array($region, $this->_regions[$country]) ? $region : '';
							}
							else {
								$fields[$system] = '';
							}
						}
					}
					else if ($code == 'country_id') {
						$country = Mage::getSingleton('core/locale')->getLocale()->getTranslation($object->getData('country_id'), 'country', 'en_US');
						$fields[$system] = str_replace([
							'Saint Barthélemy',
							'Hong Kong SAR China',
						], [
							'Saint Barthelemy',
							'Hong Kong',
						], $country);
					}
					else if ($code == 'street') {
						$fields[$system] = implode(', ', $object->getStreet());
					}
					else {
						$fields[$system] = $object->getStreet(substr($code, -1)); // not mb_substr
					}
				}
				else if ($code == 'subscriber_status') {
					$fields[$system] = ($object->getData('subscriber_status') == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) ? 'yes' : 'no';
				}
				else if ($isCustomer && ($code == 'email')) {
					$fields[$system] = $object->getData('email');
					// avec changement d'email
					// $special = Mage::getStoreConfig('maillog_sync/'.$this->_code.'/mapping_customerid_field');
					// il faut que Mautic possède un champ unique avec le customer_id, alors c'est automatique
				}
				else if ($isCustomer && ($code == 'store_id')) {
					$value = $object->getStoreId();
					$fields[$system] = substr(Mage::getStoreConfig('general/locale/code', $value), 0, 2); // not mb_substr
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
						if (!empty($fields[$system]) && (preg_match('#^\d{4}.\d{2}.\d{2}.\d{2}.\d{2}.\d{2}#', $fields[$system]) === 1))
							$fields[$system] = date('Y-m-d H:i:s', strtotime($fields[$system]));
					}
				}

				// 191 caractères pour les champs text
				if (isset($fields[$system]) && ($type == 'text'))
					$fields[$system] = mb_substr($fields[$system], 0, 191);
			}
		}

		return $fields;
	}


	public function updateCustomer(array $data) {

		// https://developer.mautic.org/#create-contact
		// https://developer.mautic.org/#edit-contact
		return $this->sendRequest('POST', 'contacts/new', $data);
	}

	public function deleteCustomer(array $data) {

		// https://developer.mautic.org/#list-contacts
		// https://developer.mautic.org/#delete-contact
		$raw = $this->sendRequest('GET', 'contacts', 'search='.urlencode($data['email']).'&limit=1&minimal=1');
		if ($this->checkResponse($raw)) {
			$response = $this->extractResponseData($raw, true);
			foreach ($response['contacts'] as $contact)
				return $this->sendRequest('DELETE', 'contacts/'.$contact['id'].'/delete');
		}

		return $raw;
	}


	public function extractResponseData($data, bool $forHistory = false) {

		// dans les entêtes il pourrait y avoir "report-to: {"endp..."
		if (($pos = mb_stripos($data, "\n{")) !== false) {

			$json = @json_decode(mb_substr($data, $pos + 1), true);
			$json = empty($json['data']) ? $json : $json['data'];

			if ($forHistory) {
				if (!empty($json['contact']) && array_key_exists('id', $json['contact'])) {
					$json = ['id' => $json['contact']['id']];
				}
				else if (is_array($json)) {
					foreach ($json as $key => $values) {
						if (is_array($values))
							unset($json[$key]['fields']);
					}
				}
			}
		}

		return empty($json) ? $data : $json;
	}

	protected function sendRequest(string $type, string $method, $data = null) {

		$url = Mage::getStoreConfig('maillog_sync/'.$this->_code.'/api_url');
		if (empty($url))
			return null;

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
			//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_POST, true);
			$override = 'X-HTTP-Method-Override: PUT';
			if (!empty($data))
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else if ($type == 'DELETE') {
			// https://gridpane.com/kb/making-nginx-accept-put-delete-and-patch-verbs/
			//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($ch, CURLOPT_POST, true);
			$override = 'X-HTTP-Method-Override: DELETE';
			if (!empty($data))
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		else if ($type == 'GET') {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
			if (!empty($data))
				$method .= '?'.$data;
		}

		curl_setopt($ch, CURLOPT_URL, $url.$method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json; charset="utf-8"',
			'Authorization: Basic '.base64_encode(Mage::getStoreConfig('maillog_sync/'.$this->_code.'/api_username').':'.Mage::helper('core')->decrypt(Mage::getStoreConfig('maillog_sync/'.$this->_code.'/api_password'))),
			$override,
		]);

		$result = curl_exec($ch);
		$result = (($result === false) || (curl_errno($ch) !== 0)) ? trim('CURL_ERROR '.curl_errno($ch).' '.curl_error($ch)) : $result;
		curl_close($ch);

		return $result;
	}
}