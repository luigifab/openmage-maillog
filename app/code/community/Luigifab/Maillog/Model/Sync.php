<?php
/**
 * Created M/10/11/2015
 * Updated L/26/03/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2016      | Pierre-Alexandre Rouanet <pierre-alexandre.rouanet~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.info/magento/maillog
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
		return (is_object($this->model)) ? $this->model : $this->model = Mage::getSingleton('maillog/system_'.Mage::getStoreConfig('maillog/sync/type'));
	}


	// effectue la synchronisation
	// runSync lance la synchro en arrière plan
	// l'email peut contenir 2 emails en cas de changement d'adresse email
	// pour le moment (v3.1) le store sert uniquement à identifier le website du client
	public function runSync($observer, $store, $email, $action) {

		//Mage::log('runSync '.$observer->getData('event')->getData('name').' '.$store.' '.$email);
		$srcEmail = $email;

		// email ou ancienEmail:nouvelEmail
		$oldEmail = (stripos($email, ':') !== false) ? explode(':', $email) : false;
		if (!empty($oldEmail)) {
			$email    = $oldEmail[1]; // nouvel email
			$oldEmail = $oldEmail[0]; // ancien email
		}

		// action
		if ((Mage::registry('maillog_no_sync') !== true) && (Mage::registry('maillog_sync_'.$email) !== true) && empty($this->getId())) {

			Mage::unregister('maillog_sync_'.$email);
			Mage::register('maillog_sync_'.$email, true);

			exec(sprintf('php %s %s %d %s %d %s %s >/dev/null 2>&1 &',
				str_replace('Maillog/etc', 'Maillog/lib/sync.php', Mage::getModuleDir('etc', 'Luigifab_Maillog')),
				escapeshellarg($action),
				intval($store),
				escapeshellarg($srcEmail),
				intval(Mage::getIsDeveloperMode() ? 1 : 0),
				escapeshellarg(Mage::app()->getStore()->getData('code')),
				escapeshellarg($this->searchAdminUsername())
			));

			if (Mage::app()->getStore()->isAdmin()) {
				$text  = Mage::helper('maillog')->__('All customer data WILL BE synchronized with %s (%s).', $this->getSystem()->getType(), $email);
				Mage::getSingleton('adminhtml/session')->addNotice($text);
			}
		}
	}

	public function updateNow($store, $email) {

		//Mage::log('updateNow '.$store.' '.$email);
		$srcEmail = $email;

		// email ou ancienEmail:nouvelEmail
		$oldEmail = (stripos($email, ':') !== false) ? explode(':', $email) : false;
		if (!empty($oldEmail)) {
			$email    = $oldEmail[1]; // nouvel email
			$oldEmail = $oldEmail[0]; // ancien email
		}

		// action
		if ((Mage::registry('maillog_no_sync') !== true) && (Mage::registry('maillog_sync_'.$email) !== true) && empty($this->getId())) {

			Mage::unregister('maillog_sync_'.$email);
			Mage::register('maillog_sync_'.$email, true);

			try {
				// chargement des objets du client
				$customer = Mage::getModel('customer/customer')
					->setWebsiteId(Mage::app()->getStore($store)->getWebsiteId())
					->loadByEmail($email);

				$billing  = $customer->getDefaultBillingAddress();
				$shipping = $customer->getDefaultShippingAddress();
				$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
				$object   = $this->initSpecialObject($customer);

				if (!empty($oldEmail))
					$customer->setOrigData('email', $oldEmail);

				if (is_object($billing) && empty($billing->getData('is_default_billing')))
					$billing->setData('is_default_billing', 1);
				if (is_object($shipping) && empty($shipping->getData('is_default_shipping')))
					$shipping->setData('is_default_shipping', 1);

				// action
				// note très très importante, le + fait en sorte que ce qui est déjà présent n'est pas écrasé
				// par exemple, si entity_id est trouvé dans $customer, même si entity_id est trouvé dans $billing,
				// c'est bien l'entity_id de customer qui est utilisé
				$data   = $this->getSystem()->mapFields($customer);
				$data  += $this->getSystem()->mapFields($billing);
				$data  += $this->getSystem()->mapFields($shipping);
				$data  += $this->getSystem()->mapFields($subscriber);
				$data  += $this->getSystem()->mapFields($object);
				$result = $this->getSystem()->updateCustomer($data);
			}
			catch (Exception $e) {
				Mage::logException($e);
			}

			$this->addToHistory(
				(!empty($customer))   ? $customer : $store.':'.$srcEmail,
				(!empty($billing))    ? $billing : null,
				(!empty($shipping))   ? $shipping : null,
				(!empty($subscriber)) ? $subscriber : null,
				(!empty($data))       ? $data : null,
				(!empty($e))          ? $e->getMessage() : $result);
		}
	}

	public function deleteNow($store, $email) {

		//Mage::log('deleteNow '.$store.' '.$email);
		$srcEmail = $email;

		// email ou ancienEmail:nouvelEmail
		$oldEmail = (stripos($email, ':') !== false) ? explode(':', $email) : false;
		if (!empty($oldEmail)) {
			$email    = $oldEmail[1]; // nouvel email
			$oldEmail = $oldEmail[0]; // ancien email
		}

		// action
		if ((Mage::registry('maillog_no_sync') !== true) && (Mage::registry('maillog_sync_'.$email) !== true) && empty($this->getId())) {

			Mage::unregister('maillog_sync_'.$email);
			Mage::register('maillog_sync_'.$email, true);

			try {
				// simulation du client
				$customer = new Varien_Object(array('store_id' => $store, 'email' => $email));

				// action
				$data   = $this->getSystem()->mapFields($customer);
				$result = $this->getSystem()->deleteCustomer($data);
			}
			catch (Exception $e) {
				Mage::logException($e);
			}

			$this->addToHistory(
				(!empty($customer))   ? $customer : $store.':'.$srcEmail,
				(!empty($billing))    ? $billing : null,
				(!empty($shipping))   ? $shipping : null,
				(!empty($subscriber)) ? $subscriber : null,
				(!empty($data))       ? $data : null,
				(!empty($e))          ? $e->getMessage() : $result);
		}
	}


	// gestion des données des objets et de l'historique
	// pour updateNow/deleteNow
	private function searchAdminUsername() {
		return (is_object(Mage::getSingleton('admin/session')->getData('user'))) ?
			Mage::getSingleton('admin/session')->getData('user')->getData('username') : '';
	}

	private function initSpecialObject($customer) {

		$object = new Varien_Object();
		$object->setData('last_sync_date', date('Y-m-d H:i:s'));

		if (!empty($customer->getId())) {

			// connexion (lecture express depuis la base de données)
			// si non disponible, utilise la date d'inscription du client
			$database = Mage::getSingleton('core/resource');
			$read     = $database->getConnection('core_read');
			$select   = $read->select()
				->from($database->getTableName('log_customer'), 'login_at')
				->where('customer_id = ?', $customer->getId())
				->order('log_id desc')
				->limit(1);

			$last = $read->fetchOne($select);
			$object->setData('last_login_date', (strlen($last) > 10) ? $last : $customer->getData('created_at'));

			// commandes
			// date, total, montant moyen
			$orders = Mage::getResourceModel('sales/order_collection');
			$orders->addFieldToFilter('customer_id', $customer->getId());
			$orders->addFieldToFilter('status', array('in' => array('processing', 'complete', 'closed')));
			$orders->setOrder('created_at', 'desc');

			if (!empty($numberOfOrders = $orders->getSize())) {

				$object->setData('last_order_date',  $orders->getFirstItem()->getData('created_at'));
				$object->setData('last_order_total', number_format($orders->getFirstItem()->getData('base_grand_total'), 2));

				$orders->clear();
				$orders->getSelect()->columns(array('total_sales' => 'SUM(main_table.base_grand_total)'))->group('customer_id');

				$object->setData('average_order_amount', number_format($orders->getFirstItem()->getData('total_sales') / $numberOfOrders, 2));
			}
			else {
				$object->setData('last_order_date', '');
				$object->setData('last_order_total', '');
				$object->setData('average_order_amount', '');
			}
		}

		return $object;
	}

	private function transformDataForHistory($data) {

		$inline = array();

		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$subdata = $this->transformDataForHistory($value);
					foreach ($subdata as $subvalue)
						$inline[] = sprintf('[%s]%s', $key, $subvalue);
				}
				else {
					$inline[] = sprintf('[%s] %s%s', $key, $value, "\n");
				}
			}
		}
		else {
			$inline[] = (empty($data)) ? '(no result)' : $data;
		}

		return $inline;
	}

	protected function addToHistory($customer, $billing, $shipping, $subscriber, $request, $response, $extra = '') {

		// calcul l'état en fonction des données
		// s'il y a des résultats ($response), alors la synchronisation a été faite (normlement il y a des données dans $request)
		// sinon, c'est que la synchronisation sera faire plus tard
		if (!empty($response)) {

			$status = $this->getSystem()->checkResponse($response) ? 'success' : 'error';
			$response = $this->getSystem()->extractResponseData($response, true);
			$response = implode($this->transformDataForHistory($response));

			if (is_array($request) && Mage::getIsDeveloperMode()) {

				ksort($request);
				$mapping = preg_split('#\s#', Mage::getStoreConfig('maillog/sync/mapping_config'));

				foreach ($request as $key => &$value) {
					foreach ($mapping as $map) {
						$map = explode(':', $map);
						if ($map[0] == $key) {
							$value = (($value != '') ? $value : '--').' ('.$map[1].')';
							break;
						}
					}
				}

				$response = implode($this->transformDataForHistory($request))."\n".$response;
			}

			$response = trim($response);
		}
		else {
			$status = 'pending';
		}

		$store = Mage::app()->getStore()->getData('code');
		if (($store == 'admin') && !empty($user = $this->searchAdminUsername()))
			$store .= ' ('.$user.')';

		if ($status == 'pending') {
			$this->setData('customer_id', $customer->getId());
			$this->setData('store_id', $customer->getData('store_id'));
			$this->setData('email', $customer->getData('email'));
			$this->setData('created_at', date('Y-m-d H:i:s'));
			$this->setData('details', trim(
				'Sync from '.$store."\n".
				'Customer '.((is_object($customer) && !empty($customer->getId())) ? $customer->getId() : 'notset').' / '.
				'Billing '.((is_object($billing) && !empty($billing->getId())) ? $billing->getId() : 'notset').' / '.
				'Shipping '.((is_object($shipping) && !empty($shipping->getId())) ? $shipping->getId() : 'notset').' / '.
				'Subscriber '.((is_object($subscriber) && !empty($subscriber->getId())) ? $subscriber->getId() : 'notset')
			));
		}
		else {
			$this->setData('status', $status);
			$this->setData('customer_id', $customer->getId());
			$this->setData('store_id', $customer->getData('store_id'));
			$this->setData('email', $customer->getData('email'));
			$this->setData('sync_at', date('Y-m-d H:i:s'));
			$this->setData('details', trim(
				'Sync from '.$store."\n".
				'Customer '.((is_object($customer) && !empty($customer->getId())) ? $customer->getId() : 'notset').' / '.
				'Billing '.((is_object($billing) && !empty($billing->getId())) ? $billing->getId() : 'notset').' / '.
				'Shipping '.((is_object($shipping) && !empty($shipping->getId())) ? $shipping->getId() : 'notset').' / '.
				'Subscriber '.((is_object($subscriber) && !empty($subscriber->getId())) ? $subscriber->getId() : 'notset')."\n".
				$response."\n".
				$extra
			));

			// si pas de traitement en arrière plan
			if (in_array($this->getData('created_at'), array('', '0000-00-00 00:00:00', null)))
				$this->setData('created_at', $this->getData('sync_at'));
		}

		// enregistre et oublie
		$this->save();

		$last = $this->getData();
		$this->setData(array());

		if (stripos(getenv('PHP_SELF'), 'lib/sync.php') === false) {
			unset($last['sync_id']);
			$this->lastSyncData = $last;
		}
	}


	// si le addToHistory est fait dans une transaction, s'il y a un rollback, tout est perdu
	public function reAddToHistory($return = false) {

		$data = $this->lastSyncData;
		if (is_array($data)) {

			$this->setData($data);
			$this->save();
			$this->setData(array());

			if ($return)
				return $data;
		}
	}
}