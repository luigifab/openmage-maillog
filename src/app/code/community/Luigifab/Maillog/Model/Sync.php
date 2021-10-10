<?php
/**
 * Created M/10/11/2015
 * Updated M/05/10/2021
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

class Luigifab_Maillog_Model_Sync extends Mage_Core_Model_Abstract {

	protected $_eventPrefix  = 'maillog_sync';
	protected static $_reviews = [];

	public function _construct() {
		$this->_init('maillog/sync');
	}


	// action
	public function updateNow($entity = null) {

		$now = time();
		if (empty($this->getId()))
			Mage::throwException('You must load a sync before trying to sync it.');

		// 0 method_name (update) : 1 object_type (customer) : 2 object_id : 3 old-email : 4 email
		// 0 method_name (update) : 1 object_type (customer) : 2 object_id : 3           : 4 email
		$info = (array) explode(':', $this->getData('action')); // (yes)

		try {
			$system = Mage::helper('maillog')->getSystem($code = $this->getData('model'));
			if (!($system instanceof Luigifab_Maillog_Model_Interface))
				Mage::throwException('Unknown system: '.get_class($system).'.');

			$this->setData('status', 'running');
			$this->save();

			// chargement des objets du client
			if ($info[1] == 'customer') {

				$customer   = is_object($entity) ? $entity : Mage::getModel('customer/customer')->load($info[2]);
				$billing    = $customer->getDefaultBillingAddress();
				$shipping   = $customer->getDefaultShippingAddress();
				$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getData('email'), $customer->getStoreId());
				$object     = $this->initSpecialObject($customer, $system->getMapping());

				if (!empty($info[3])) {
					$customer->setOrigData('email', $info[3]);
					$customer->setData('email', $info[4]);
				}
				else if (empty($info[4])) {
					$this->setData('action', $this->getData('action').$customer->getData('email'));
				}

				if (is_object($billing) && empty($billing->getData('is_default_billing')))
					$billing->setData('is_default_billing', 1);
				if (is_object($shipping) && empty($shipping->getData('is_default_shipping')))
					$shipping->setData('is_default_shipping', 1);
			}
			else if ($info[1] == 'subscriber') {

				$subscriber = is_object($entity) ? $entity : Mage::getModel('newsletter/subscriber')->load($info[2]);

				$customer = Mage::getModel('customer/customer');
				$customer->setOrigData('email', $subscriber->getOrigData('subscriber_email'));
				$customer->setData('email', $subscriber->getData('subscriber_email'));
				$customer->setData('store_id', $subscriber->getStoreId());

				$billing  = null;
				$shipping = null;
				$object   = $this->initSpecialObject($customer, $system->getMapping());
			}
			else {
				Mage::throwException('Unknown object_type ('.$info[1] .').');
			}

			// action
			// note très très importante, le + fait en sorte que ce qui est déjà présent n'est pas écrasé
			// par exemple, si entity_id est trouvé dans $customer, même si entity_id est trouvé dans $billing,
			// c'est bien l'entity_id de customer qui est utilisé
			$allow = Mage::getStoreConfigFlag('maillog_sync/'.$code.'/send') ? Mage::helper('maillog')->canSend(...$info) : false;
			$data  = $system->mapFields($customer, 'customer');
			$data += $system->mapFields($billing, 'address');
			$data += $system->mapFields($shipping, 'address');
			$data += $system->mapFields($subscriber, 'subscriber');
			$data += $system->mapFields($object, 'special');

			if ($allow !== true) {
				$this->setData('duration', time() - $now);
				$this->setData('response', $allow);
				$this->saveAllData($system, $data);
			}
			else {
				$result = $system->updateCustomer($data);
				$this->setData('duration', time() - $now);
				$this->saveAllData($system, $data, $result);
			}
		}
		catch (Throwable $t) {
			Mage::logException($t);
			$this->saveAllData($system, $data ?? null, $t->getMessage());
		}

		return empty($data) ? null : $data;
	}

	public function deleteNow($entity = null) {

		$now = time();
		if (empty($this->getId()))
			Mage::throwException('You must load a sync before trying to sync it.');

		// 0 method_name (delete) : 1 object_type (customer) : 2 object_id : 3 old-email : 4 email
		// 0 method_name (delete) : 1 object_type (customer) : 2 object_id : 3           : 4 email
		$info = (array) explode(':', $this->getData('action')); // (yes)

		try {
			$system = Mage::helper('maillog')->getSystem($code = $this->getData('model'));
			if (!($system instanceof Luigifab_Maillog_Model_Interface))
				Mage::throwException('Unknown system: '.get_class($system).'.');

			$this->setData('status', 'running');
			$this->save();

			// chargement des objets du client
			if ($info[1] == 'customer') {
				$customer = is_object($entity) ? $entity : Mage::getModel('customer/customer');
				$customer->setOrigData('email', $info[4]);
				$customer->setData('email', $info[4]);
			}
			else {
				Mage::throwException('Unknown object_type ('.$info[1] .').');
			}

			// action
			$allow = Mage::getStoreConfigFlag('maillog_sync/'.$code.'/send') ? Mage::helper('maillog')->canSend(...$info) : false;
			$data  = $system->mapFields($customer, 'customer');

			if ($allow !== true) {
				$this->setData('duration', time() - $now);
				$this->setData('response', $allow);
				$this->saveAllData($system, $data);
			}
			else {
				$result = $system->deleteCustomer($data);
				$this->setData('duration', time() - $now);
				$this->saveAllData($system, $data, $result);
			}
		}
		catch (Throwable $t) {
			Mage::logException($t);
			$this->saveAllData($system, $data ?? null, $t->getMessage());
		}

		return empty($data) ? null : $data;
	}


	// gestion des données des objets et de l'historique
	protected function initSpecialObject(object $customer, array $values) {

		$object = new Varien_Object();
		$object->setData('last_sync_date', date('Y-m-d H:i:s'));

		if (!empty($id = $customer->getId())) {

			$database = Mage::getSingleton('core/resource');
			$read = $database->getConnection('core_read');

			// customer_group_code (lecture express depuis la base de données)
			if ($this->inArray('group_name', $values)) {

				$name = $read->fetchOne($read->select()
					->from($database->getTableName('customer_group'), 'customer_group_code')
					->where('customer_group_id = ?', $customer->getGroupId())
					->limit(1));

				$object->setData('group_name', $name);
			}

			// login_at (lecture express depuis la base de données)
			// si non disponible, utilise la date d'inscription du client
			if ($this->inArray('last_login_date', $values)) {

				$item = $read->fetchOne($read->select()
					->from($database->getTableName('log_customer'), 'login_at')
					->where('customer_id = ?', $id)
					->order('log_id desc')
					->limit(1));

				$object->setData('last_login_date', (strlen($item) > 10) ? $item : $customer->getData('created_at'));
			}

			// commandes
			// $order->getData('global_currency_code') != $order->getData('base_currency_code')
			$orders = Mage::getResourceModel('sales/order_collection')
				->addFieldToFilter('customer_id', $id)
				->addFieldToFilter('state', ['in' => ['processing', 'complete', 'closed']])
				->setOrder('created_at', 'desc');

			$numberOfOrders = $orders->getSize();
			$object->setData('number_of_orders', $numberOfOrders);
			$object->setData('orders_collection', $orders);

			if ($numberOfOrders > 0) {

				// première commande
				$firstOrder = $orders->getLastItem();
				$object->setData('first_order_id',          $firstOrder->getId());
				$object->setData('first_order_incrementid', $firstOrder->getData('increment_id'));
				$object->setData('first_order_date',        $firstOrder->getData('created_at'));
				$object->setData('first_order_total', ($firstOrder->getData('base_grand_total') * $firstOrder->getData('base_to_global_rate')));
				$object->setData('first_order_total_notax', (($firstOrder->getData('base_grand_total') - $firstOrder->getData('base_tax_amount')) * $firstOrder->getData('base_to_global_rate')));

				if ($this->inArray('first_order_names_list', $values) ||
				    $this->inArray('first_order_skus_list', $values) || $this->inArray('first_order_skus_number', $values)) {

					$firstSkus = $firstOrder->getItemsCollection()
						->addFieldToFilter('parent_item_id', ['null' => true])
						->setOrder('price_incl_tax', 'desc');

					$object->setData('first_order_items_collection', $firstSkus);

					$list = array_unique($firstSkus->getColumnValues('sku'));
					$object->setData('first_order_skus_list', implode(',', $list));
					$object->setData('first_order_skus_number', count($list));

					$list = array_unique($firstSkus->getColumnValues('name'));
					$object->setData('first_order_names_list', implode(',', $list));
				}

				// dernière commande
				$lastOrder = $orders->getFirstItem();
				$object->setData('last_order_id',          $lastOrder->getId());
				$object->setData('last_order_incrementid', $lastOrder->getData('increment_id'));
				$object->setData('last_order_date',        $lastOrder->getData('created_at'));
				$object->setData('last_order_total', ($lastOrder->getData('base_grand_total') * $lastOrder->getData('base_to_global_rate')));
				$object->setData('last_order_total_notax', (($lastOrder->getData('base_grand_total') - $lastOrder->getData('base_tax_amount')) * $lastOrder->getData('base_to_global_rate')));

				if ($this->inArray('last_order_names_list', $values) ||
				    $this->inArray('last_order_skus_list', $values) || $this->inArray('last_order_skus_number', $values) ||
				    $this->inArray('last_order_product_1_sku', $values) || $this->inArray('last_order_product_1_name', $values)) {

					$lastSkus = $lastOrder->getItemsCollection()
						->addFieldToFilter('parent_item_id', ['null' => true])
						->setOrder('price_incl_tax', 'desc');

					$object->setData('last_order_items_collection', $lastSkus);

					$list = array_unique($lastSkus->getColumnValues('sku'));
					$object->setData('last_order_skus_list', implode(',', $list));
					$object->setData('last_order_skus_number', count($list));

					$list = array_unique($lastSkus->getColumnValues('name'));
					$object->setData('last_order_names_list', implode(',', $list));

					$idx = 1;
					$storeId = $lastOrder->getStoreId();
					foreach ($lastSkus as $sku) {

						$object->setData('last_order_product_'.$idx.'_sku',  $sku->getData('sku'));
						$object->setData('last_order_product_'.$idx.'_name',  $sku->getData('name'));
						$object->setData('last_order_product_'.$idx.'_price', (float) $sku->getData('price_incl_tax'));
						$productId = $sku->getProductId();

						if ($this->inArray('last_order_product_'.$idx.'_rating', $values)) {

							$key = $storeId.$productId;
							if (!array_key_exists($key, self::$_reviews))
								self::$_reviews[$key] = Mage::getModel('review/review_summary') // 0/100
									->setStoreId($storeId)
									->load($productId)
									->getRatingSummary();

							$object->setData('last_order_product_'.$idx.'_rating', self::$_reviews[$key]);
						}

						if ($this->inArray('last_order_product_'.$idx.'_url', $values)) {
							if (empty($baseurl)) {
								$baseurl = preg_replace('#/[^/]+\.php\d*/#', '/index.php/', Mage::app()->getStore($storeId)->getBaseUrl());
								$suffix  = Mage::helper('catalog/product')->getProductUrlSuffix();
							}
							if (Mage::helper('core')->isModuleEnabled('Luigifab_Cronlog') && Mage::getStoreConfigFlag('urlnosql/general/enabled')) {
								$object->setData('last_order_product_'.$idx.'_url', $baseurl.$productId.$suffix);
							}
							else {
								$object->setData('last_order_product_'.$idx.'_url', $baseurl.'catalog/product/view/id/'.$productId.'/');
							}
						}

						if ($this->inArray('last_order_product_'.$idx.'_image', $values)) {
							$image = Mage::getResourceSingleton('catalog/product')->getAttributeRawValue($productId, 'thumbnail', $storeId);
							$image = (string) Mage::helper('catalog/image')->init(new Varien_Object(), 'thumbnail', $image)->resize(400);
							$object->setData('last_order_product_'.$idx.'_image', $image);
						}

						$idx++;
						if ($idx > 5)
							break;
					}
				}

				// produits commandés
				if ($this->inArray('all_ordered_names', $values) || $this->inArray('all_ordered_skus', $values)) {

					$allSkus = Mage::getResourceModel('sales/order_item_collection')
						->addFieldToFilter('order_id', ['in' => $orders->getAllIds()])
						->addFieldToFilter('parent_item_id', ['null' => true])
						->setOrder('created_at', 'desc');

					$object->setData('all_ordered_items_collection', $allSkus);
					$object->setData('all_ordered_names', implode(',', array_slice(array_unique($allSkus->getColumnValues('name')), 0, 250)));
					$object->setData('all_ordered_skus', implode(',', array_slice(array_unique($allSkus->getColumnValues('sku')), 0, 250)));
				}

				if ($this->inArray('number_of_products_ordered', $values)) {

					$allSkus = empty($allSkus) ? Mage::getResourceModel('sales/order_item_collection')
						->addFieldToFilter('order_id', ['in' => $orders->getAllIds()])
						->addFieldToFilter('parent_item_id', ['null' => true]) : $allSkus;

					$object->setData('number_of_products_ordered', array_sum($allSkus->getColumnValues('qty_ordered')));
				}

				// mautic
				$rom = $this->inArray('rating_order_monetary', $values) || $this->inArray('rating_order_recency', $values) || $this->inArray('rating_order_frequency', $values);

				// moyennes
				$columns = [];
				if ($rom || $this->inArray('average_order_amount', $values) || $this->inArray('total_order_amount', $values))
					$columns['sum_incl_tax'] = 'SUM(main_table.base_grand_total * main_table.base_to_global_rate)';

				if ($this->inArray('average_order_amount_notax', $values) || $this->inArray('total_order_amount_notax', $values))
					$columns['sum_excl_tax'] = 'SUM(main_table.base_grand_total * main_table.base_to_global_rate) - '.
						'SUM(main_table.base_tax_amount * main_table.base_to_global_rate)';

				if ($this->inArray('average_days_between_orders', $values))
					$columns['average_days'] = // https://dba.stackexchange.com/a/164826
						'CASE WHEN COUNT(*) > 1
							THEN ABS(DATEDIFF(MIN(main_table.created_at), MAX(main_table.created_at)) / (COUNT(*) - 1))
							ELSE 0
						END';

				if (!empty($columns)) {

					$orders->clear();
					$orders->getSelect()->columns($columns)->group('customer_id');
					$item = $orders->getFirstItem();

					if (isset($item['sum_incl_tax'])) {
						$object->setData('average_order_amount', (float) ($item->getData('sum_incl_tax') / $numberOfOrders));
						$object->setData('total_order_amount',   (float) $item->getData('sum_incl_tax'));
					}
					if (isset($item['sum_excl_tax'])) {
						$object->setData('average_order_amount_notax', (float) ($item->getData('sum_excl_tax') / $numberOfOrders));
						$object->setData('total_order_amount_notax',   (float) $item->getData('sum_excl_tax'));
					}
					if (isset($item['average_days'])) {
						$object->setData('average_days_between_orders', (float) $item->getData('average_days'));
					}
				}

				// mautic
				if ($rom) {

					$config = @unserialize(Mage::getStoreConfig('maillog_sync/mautic/mautic_config'), ['allowed_classes' => false]);
					$time   = Mage::getModel('core/date')->gmtTimestamp();

					// nombre de jour depuis la dernière commande
					$recency = ($time - strtotime($lastOrder->getData('created_at'))) / (60 * 60 * 24);
					if ($recency <= $config['5a'])
						$object->setData('rating_order_recency', 5);
					else if ($recency <= $config['4a'])
						$object->setData('rating_order_recency', 4);
					else if ($recency <= $config['3a'])
						$object->setData('rating_order_recency', 3);
					else if ($recency <= $config['2a'])
						$object->setData('rating_order_recency', 2);
					else
						$object->setData('rating_order_recency', 1);

					$frequency = $object->getData('number_of_orders');
					if ($frequency > $config['4b'])
						$object->setData('rating_order_frequency', 5);
					else if ($frequency > $config['3b'])
						$object->setData('rating_order_frequency', 4);
					else if ($frequency > $config['2b'])
						$object->setData('rating_order_frequency', 3);
					else if ($frequency > $config['1b'])
						$object->setData('rating_order_frequency', 2);
					else
						$object->setData('rating_order_frequency', 1);

					$monetary = $object->getData('total_order_amount');
					if ($monetary > $config['4c'])
						$object->setData('rating_order_monetary', 5);
					else if ($monetary > $config['3c'])
						$object->setData('rating_order_monetary', 4);
					else if ($monetary > $config['2c'])
						$object->setData('rating_order_monetary', 3);
					else if ($monetary > $config['1c'])
						$object->setData('rating_order_monetary', 2);
					else
						$object->setData('rating_order_monetary', 1);
				}
			}
		}

		return $object;
	}

	protected function inArray($needle, array $haystack, bool $strict = false) {
		// https://stackoverflow.com/a/4128377/2980105
		foreach ($haystack as $item) {
			if (($strict ? ($item === $needle) : ($item == $needle)) || (is_array($item) && $this->inArray($needle, $item, $strict)))
				return true;
		}
		return false;
	}

	protected function transformDataForHistory(array $data, bool $asString = true) {

		$inline = [];

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

		return $asString ? trim(implode($inline)) : $inline;
	}

	public function saveAllData(object $system, $request = null, $response = null) {

		if (!empty($request)) {

			if (Mage::getIsDeveloperMode()) {
				if (is_array($request)) {
					ksort($request);
					$this->setData('request', $this->transformDataForHistory($request));
				}
				else {
					$this->setData('request', $request);
				}
			}
			else {
				$this->setData('request', null);
			}

			if (!empty($response)) {
				$status = $system->checkResponse($response) ? 'success' : 'error';
				$result = $system->extractResponseData($response, true);
				$this->setData('response', is_array($result) ? $this->transformDataForHistory($result) : $result);
			}
		}
		else if (!empty($response)) {
			$status = 'error';
			$this->setData('response', $response);
		}

		$this->setData('sync_at', date('Y-m-d H:i:s'));
		$this->setData('status', empty($status) ? 'notsync' : $status);
		$this->save();
	}
}