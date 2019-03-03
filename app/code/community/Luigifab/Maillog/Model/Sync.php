<?php
/**
 * Created M/10/11/2015
 * Updated S/16/02/2019
 *
 * Copyright 2015-2019 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
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

class Luigifab_Maillog_Model_Sync extends Mage_Core_Model_Abstract {

	public function _construct() {
		$this->_init('maillog/sync');
	}

	public function getSystem() {
		if (!is_object($this->model))
			$this->model = Mage::getSingleton('maillog/system_'.Mage::getStoreConfig('maillog/sync/type'));
		return $this->model;
	}


	// effectue la synchronisation
	public function updateNow($send = true) {

		$now = time();
		if (empty($this->getId()))
			Mage::throwException('You must load a sync before trying to sync it.');

		// 0 action : 1 type : 2 id : 3 ancien-email : 4 email
		// 0 action : 1 type : 2 id : 3              : 4 email
		$dat = explode(':', $this->getData('action'));

		try {
			$this->setData('status', 'running');
			$this->save();

			$allow = Mage::helper('maillog')->canSend('sync', $dat);

			// chargement des objets du client
			if ($dat[1] == 'customer') {

				$customer   = Mage::getModel('customer/customer')->load($dat[2]);
				$billing    = $customer->getDefaultBillingAddress();
				$shipping   = $customer->getDefaultShippingAddress();
				$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getData('email'), $customer->getData('store_id'));
				$object     = $this->initSpecialObject($customer);

				if (!empty($dat[3])) {
					$customer->setOrigData('email', $dat[3]);
					$customer->setData('email', $dat[4]);
				}

				if (is_object($billing) && empty($billing->getData('is_default_billing')))
					$billing->setData('is_default_billing', 1);
				if (is_object($shipping) && empty($shipping->getData('is_default_shipping')))
					$shipping->setData('is_default_shipping', 1);
			}
			else if ($dat[1] == 'subscriber') {

				$subscriber = Mage::getModel('newsletter/subscriber')->load($dat[2]);
				$customer   = Mage::getModel('customer/customer');

				$customer->setOrigData('email', $subscriber->getOrigData('subscriber_email'));
				$customer->setData('email', $subscriber->getData('subscriber_email'));
				$customer->setData('store_id', $subscriber->getData('store_id'));

				$billing    = null;
				$shipping   = null;
				$object     = $this->initSpecialObject($customer);
			}
			else {
				Mage::throwException('Unknown sync type.');
			}

			// action
			// note très très importante, le + fait en sorte que ce qui est déjà présent n'est pas écrasé
			// par exemple, si entity_id est trouvé dans $customer, même si entity_id est trouvé dans $billing,
			// c'est bien l'entity_id de customer qui est utilisé
			$data  = $this->getSystem()->mapFields($customer);
			$data += $this->getSystem()->mapFields($billing);
			$data += $this->getSystem()->mapFields($shipping);
			$data += $this->getSystem()->mapFields($subscriber);
			$data += $this->getSystem()->mapFields($object);

			if ($allow !== true) {
				$this->setData('duration', time() - $now);
				$this->saveAllData($data, null, false);
				$this->setData('status', 'notsync');
				$this->setData('response', $allow);
				$this->save();
			}
			else if ($send) {
				$result = $this->getSystem()->updateCustomer($data);
				$this->setData('duration', time() - $now);
				$this->saveAllData($data, $result);
			}
			else {
				$this->saveAllData($data, null);
			}
		}
		catch (Exception $e) {
			Mage::logException($e);
		}

		return !empty($data) ? $data : null;
	}

	public function deleteNow() {

		$now = time();
		if (empty($this->getId()))
			Mage::throwException('You must load a sync before trying to sync it.');

		// 0 action : 1 type : 2 id : 3 ancien-email : 4 email
		// 0 action : 1 type : 2 id : 3              : 4 email
		$dat = explode(':', $this->getData('action'));

		try {
			$this->setData('status', 'running');
			$this->save();

			// simulation du client
			$customer = Mage::getModel('customer/customer');
			$customer->setOrigData('email', $dat[4]);
			$customer->setData('email', $dat[4]);

			$allow = Mage::helper('maillog')->canSend('sync', $dat);
			$data  = $this->getSystem()->mapFields($customer);

			if ($allow !== true) {
				$this->setData('duration', time() - $now);
				$this->saveAllData($data, null, false);
				$this->setData('status', 'notsync');
				$this->setData('response', $allow);
				$this->save();
			}
			else {
				$result = $this->getSystem()->deleteCustomer($data);
				$this->setData('duration', time() - $now);
				$this->saveAllData($data, $result);
			}
		}
		catch (Exception $e) {
			Mage::logException($e);
		}

		return !empty($data) ? $data : null;
	}


	// gestion des données des objets et de l'historique
	// si le saveAllData est fait dans une transaction, s'il y a un rollback, tout est perdu
	// dans ce cas ne pas oublier de refaire un save, par exemple
	private function initSpecialObject($customer) {

		$object = new Varien_Object();
		$object->setData('last_sync_date', date('Y-m-d H:i:s'));

		if (!empty($id = $customer->getId())) {

			$database = Mage::getSingleton('core/resource');
			$read     = $database->getConnection('core_read');

			// customer_group_code (lecture express depuis la base de données)
			$select = $read->select()
				->from($database->getTableName('customer_group'), 'customer_group_code')
				->where('customer_group_id = ?', $customer->getGroupId())
				->limit(1);

			$name = $read->fetchOne($select);
			$object->setData('group_name', $name);

			// login_at (lecture express depuis la base de données)
			// si non disponible, utilise la date d'inscription du client
			$select = $read->select()
				->from($database->getTableName('log_customer'), 'login_at')
				->where('customer_id = ?', $id)
				->order('log_id desc')
				->limit(1);

			$last = $read->fetchOne($select);
			$object->setData('last_login_date', (mb_strlen($last) > 10) ? $last : $customer->getData('created_at'));

			// commandes
			$orders = Mage::getResourceModel('sales/order_collection');
			$orders->addFieldToFilter('customer_id', $id);
			$orders->addFieldToFilter('status', array('in' => array('processing', 'complete', 'closed')));
			$orders->setOrder('created_at', 'desc');

			if (!empty($numberOfOrders = $orders->getSize())) {

				$last = $orders->getLastItem();
				$object->setData('first_order_date',        $last->getData('created_at'));
				$object->setData('first_order_total',       floatval($last->getData('base_grand_total')));
				$object->setData('first_order_total_notax', floatval($last->getData('base_grand_total') - $last->getData('base_tax_amount')));

				$first = $orders->getFirstItem();
				$object->setData('last_order_date',        $first->getData('created_at'));
				$object->setData('last_order_total',       floatval($first->getData('base_grand_total')));
				$object->setData('last_order_total_notax', floatval($first->getData('base_grand_total') - $first->getData('base_tax_amount')));

				$orders->clear();
				$orders->getSelect()->columns(array(
					'sumincltax' => 'SUM(main_table.base_grand_total)',
					'sumexcltax' => 'SUM(main_table.base_grand_total) - SUM(main_table.base_tax_amount)'
				))->group('customer_id');

				$item = $orders->getFirstItem();
				$object->setData('average_order_amount',       floatval($item->getData('sumincltax') / $numberOfOrders));
				$object->setData('average_order_amount_notax', floatval($item->getData('sumexcltax') / $numberOfOrders));
				$object->setData('total_order_amount',         floatval($item->getData('sumincltax')));
				$object->setData('total_order_amount_notax',   floatval($item->getData('sumexcltax')));
				$object->setData('number_of_orders',           $numberOfOrders);
			}
		}

		return $object;
	}

	private function transformDataForHistory($data, $asString = true) {

		$inline = array();

		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$subdata = $this->transformDataForHistory($value, false);
					foreach ($subdata as $subvalue)
						$inline[] = sprintf('[%s]%s', $key, $subvalue);
				}
				else {
					$inline[] = sprintf('[%s] %s%s', $key, $value, "\n");
				}
			}
		}
		else {
			$inline[] = empty($data) ? '(no result)' : $data;
		}

		return $asString ? trim(implode($inline)) : $inline;
	}

	public function saveAllData($request, $response, $save = true) {

		if (!empty($request) && is_array($request)) {

			ksort($request);
			$mapping = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog/sync/mapping_config')));
			$lines   = explode("\n", $this->transformDataForHistory($request));

			foreach ($mapping as $map) {
				$map = explode(':', $map);
				$tmp = trim(array_shift($map));
				foreach ($lines as &$line) {
					// emarsys  [2] Test
					if (mb_strpos($line, '['.$tmp.']') !== false) {
						$line = $line.((mb_substr($line, -2) == '] ') ? ' --' : '').' ('.implode(' ', $map).')';
						break;
					}
					// dolist   [Fields][x][Name] lastname
					else if (mb_strpos($line, '][Name] '.$tmp) !== false) {
						$line = $line.' ('.implode(' ', $map).')';
						break;
					}
				}
				unset($line);
			}

			$this->setData('request', implode("\n", $lines));
		}

		$status   = $this->getSystem()->checkResponse($response) ? 'success' : 'error';
		$response = $this->getSystem()->extractResponseData($response, true);

		$this->setData('response', $this->transformDataForHistory($response));
		$this->setData('sync_at', date('Y-m-d H:i:s'));
		$this->setData('status', $status);

		if ($save)
			$this->save();
	}
}