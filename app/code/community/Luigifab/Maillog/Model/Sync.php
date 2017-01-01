<?php
/**
 * Created M/10/11/2015
 * Updated M/08/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>,
 *   Pierre-Alexandre Rouanet <pierre-alexandre.rouanet~label-park~com>
 * https://redmine.luigifab.info/projects/magento/wiki/maillog
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

	private $model = null;
	private $isBackground = false;

	// attention, ceci est un singleton
	// comme chaque model dans System
	public function _construct() {
		$this->_init('maillog/sync');
		$this->model = Mage::getSingleton('maillog/system_'.Mage::getStoreConfig('maillog/sync/type'));
	}

	public function backgroundSync($website, $store) {

		if ($this->getStatus() === 'pending') {
			$customer = Mage::getModel('customer/customer')->setWebsiteId($website)->loadByEmail($this->getEmail());
			$this->isBackground = true;
			$this->updateCustomer($customer, $store, true);
		}
	}

	public function cleanup() {

		$lifetime = intval(Mage::getStoreConfig('maillog/sync/lifetime'));
		if ($lifetime < 7200) // 5 jours
			return $this;

		// check every 24 hours (1440 minutes) if history cleanup is needed
		if (Mage::app()->loadCache('maillog_last_history_sync_cleanup_at') > (time() - 1440 * 60))
			return $this;

		$items = Mage::getResourceModel('maillog/sync_collection');
		$items->addFieldToFilter('status', 'success');
		$items->addFieldToFilter('created_at', array('lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$lifetime.' MINUTE)')));
		$items->deleteAll();

		Mage::app()->saveCache(time(), 'maillog_last_history_sync_cleanup_at', array('maillog'), null);

		return $this;
	}


	// SYNCHRONISATION des données, traitement en arrière plan si autorisé
	// front-office et back-office
	public function updateCustomer($objects, $store, $now = false) {

		// récupère les 3 objets
		if (get_class($objects) === get_class(Mage::getModel('customer/address'))) {
			$customer   = $objects->getCustomer();
			$address    = $objects;
			$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
		}
		else {
			$customer   = $objects;
			$address    = $customer->getDefaultShippingAddress();
			$subscriber = (is_object($objects->getSubscriber())) ? clone $objects->getSubscriber() :
				Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
			$objects->setSubscriber(null); // supprime l'objet subscriber ajouté par updateSubscriber et deleteSubscriber
		}

		// évite de faire deux fois le même traitement
		if (Mage::registry('maillog_sync_'.$customer->getEmail()) === true)
			return;
		Mage::register('maillog_sync_'.$customer->getEmail(), true);

		// Magento 1.4 1.5 1.6 1.7 1.8 1.9
		// très utile lors de la création d'un client avec une adresse de livraison par défaut dans le back-office
		// très utile lors de la modification des données du compte client sur le front-office
		// charge l'adresse manquante
		if (!is_object($address) && ($customer->getDefaultShipping() > 0))
			$address = Mage::getModel('customer/address')->load($customer->getDefaultShipping());

		// Magento 1.6 1.7 1.8 1.9
		// très utile lors de la création d'une nouvelle adresse de livraison par défaut sur le front-office
		// très utile lors du changement de l'adresse de livraison par défaut et la modification de l'adresse sur le front-office
		// ajoute les attributs manquants
		if (version_compare(Mage::getVersion(), '1.6', '>=') && is_object($address)) {
			$attributes = Mage::getSingleton('eav/config')->getEntityType('customer_address')->getAttributeCollection();
			foreach ($attributes as $attribute) {
				if (!$address->hasData($attribute->getAttributeCode()))
					$address->setData($attribute->getAttributeCode(), '');
			}
		}

		// traitement de la synchronisation
		// soit tout de suite (si demandé), soit en arrière plan (si la config le permet)
		if ($now || !Mage::getStoreConfigFlag('maillog/sync/background')) {

			try {
				$idField = Mage::getStoreConfig('maillog/sync/mapping_customerid_field');
				$idField = ($idField) ? $idField : false;
				$newMail = false;

				// dans le cas où le client existe en cas de changement d'adresse email
				if ($customer->getId() > 0) {
					if (($customer->getData('email') !== $customer->getOrigData('email')) && (strlen($customer->getOrigData('email')) > 0))
						$newMail = true;
				}

				// action !
				$data   = $this->model->mapFields($customer);
				$data  += $this->model->mapFields($address);
				$data  += $this->model->mapFields($subscriber);
				$data  += $this->model->mapFields($this->getSpecialObject($customer));
				$data  += $this->model->mapUniqueField($idField, $newMail);

				$result = $this->model->sendRequest(
					constant(get_class($this->model).'::UPDATE_CUSTOMER_REQUEST_TYPE'),
					constant(get_class($this->model).'::UPDATE_CUSTOMER_REQUEST_ENDPOINT'),
					$data
				);

				$status = $this->model->checkResponse($result) ? 'success' : 'error';
				$result = $this->getArrayForHistory($this->model->extractResponseData($result));

				if (Mage::getIsDeveloperMode()) {
					ksort($data);
					$data = str_replace(array("Array\n(\n", "\n)"), '', trim(print_r($data, true)));
					$this->addToHistory($store, $customer, $address, $subscriber, $status, trim($data."\n".$result));
				}
				else {
					$this->addToHistory($store, $customer, $address, $subscriber, $status, $result);
				}
			}
			catch (Exception $e) {
				$this->addToHistory($store, $customer, $address, $subscriber, 'error', 'Exception: '.$e->getMessage());
			}
		}
		else {
			$this->addToHistory($store, $customer, $address, $subscriber);
			$program = substr(__FILE__, 0, strpos(__FILE__, 'Model')).'lib/sync.php';
			exec('php '.$program.' '.$this->getId().' '.$customer->getWebsiteId().' '.$store.' '.$this->getAdminUsername().' >/dev/null 2>&1 &');
		}
	}

	// SYNCHRONISATION des données, pas de traitement en arrière plan
	// simule une adresse avec Varien_Object pour afficher l'id dans l'historique
	// simule un client avec Varien_Object car il n'est pas nécessaire d'envoyer toutes les informations du client pour le supprimer
	// back-office uniquement (bien que déclaré en global)
	public function deleteCustomer($objects, $store) {

		// récupère les 3 objets
		$customer   = $objects;
		$address    = new Varien_Object(array('id' => $customer->getDefaultShipping()));
		$subscriber = (is_object($objects->getSubscriber())) ? clone $objects->getSubscriber() :
			Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
		$objects->setSubscriber(null); // supprime l'objet subscriber ajouté par deleteSubscriber

		// évite de faire deux fois le même traitement
		if (Mage::registry('maillog_sync_'.$customer->getEmail()) === true)
			return;
		Mage::register('maillog_sync_'.$customer->getEmail(), true);

		try {
			// simulation du client
			if ($customer->getId() > 0)
				$object = new Varien_Object(array('email' => $customer->getEmail(), 'entity_id' => $customer->getId()));
			else
				$object = new Varien_Object(array('email' => $customer->getEmail()));

			// action !
			$data   = $this->model->mapFields($object);

			$result = $this->model->sendRequest(
				constant(get_class($this->model).'::DELETE_CUSTOMER_REQUEST_TYPE'),
				constant(get_class($this->model).'::DELETE_CUSTOMER_REQUEST_ENDPOINT'),
				$data
			);

			$status = $this->model->checkResponse($result) ? 'success' : 'error';
			$result = $this->getArrayForHistory($this->model->extractResponseData($result));

			if (Mage::getIsDeveloperMode()) {
				ksort($data);
				$data = str_replace(array("Array\n(\n", "\n)"), '', trim(print_r($data, true)));
				$this->addToHistory($store, $customer, $address, $subscriber, $status, trim($data."\n".$result));
			}
			else {
				$this->addToHistory($store, $customer, $address, $subscriber, $status, $result);
			}
		}
		catch (Exception $e) {
			$this->addToHistory($store, $customer, null, null, 'error', 'Exception: '.$e->getMessage());
		}
	}

	// traitement en arrière plan sur updateCustomer
	// uniquement si c'est l'adresse de livraison par défaut
	// front-office uniquement
	public function updateAddress($address, $store) {

		$customer = $address->getCustomer();

		if (stripos(Mage::helper('core/url')->getCurrentUrl(), '/customer/address/') !== false) {
			if (!is_object($customer->getDefaultShippingAddress()) || ($address->getId() === $customer->getDefaultShippingAddress()->getId()))
				$this->updateCustomer($address, $store);
		}
	}

	// traitement en arrière plan sur updateCustomer (sauf si le client n'existe pas)
	// simule un vrai client s'il n'y a pas de client existant sur Magento
	// front-office et back-office
	public function updateSubscriber($subscriber, $store) {

		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$customer = ($customer->getId() > 0) ? $customer : Mage::getModel('customer/customer')->load($subscriber->getCustomerId());

		if ($customer->getId() > 0) {
			$customer->setSubscriber($subscriber);                                  // le set sera supprimé par updateCustomer
			$this->updateCustomer($customer, $store);
		}
		else {
			$customer->setData('store_id', Mage::app()->getStore()->getStoreId());  // le set n'a pas d'importance car l'objet n'existe pas
			$customer->setData('email', $subscriber->getSubscriberEmail());         // le set n'a pas d'importance car l'objet n'existe pas
			$customer->setSubscriber($subscriber);                                  // le set sera supprimé par updateCustomer
			$this->updateCustomer($customer, $store, true);
		}
	}

	// traitement en arrière plan sur updateCustomer - pas de traitement en arrière plan sur deleteCustomer
	// simule un vrai client s'il n'y a pas de client existant sur Magento
	// back-office uniquement (bien que déclaré en global)
	public function deleteSubscriber($subscriber, $store) {

		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$customer = ($customer->getId() > 0) ? $customer : Mage::getModel('customer/customer')->load($subscriber->getCustomerId());

		// si le client existe on le désabonne, sinon on supprime le contact
		if ($customer->getId() > 0) {
			$subscriber->setData('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
			$customer->setSubscriber($subscriber);                                  // le set sera supprimé par updateCustomer
			$this->updateCustomer($customer, $store);
		}
		else {
			$subscriber->setData('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
			$customer->setData('email', $subscriber->getSubscriberEmail());         // le set n'a pas d'importance car l'objet n'existe pas
			$customer->setSubscriber($subscriber);                                  // le set sera supprimé par deleteCustomer
			$this->deleteCustomer($customer, $store);
		}
	}

	// traitement en arrière plan sur updateCustomer
	// front-office et back-office
	public function orderInvoice($order, $store) {

		if ($order->getCustomerId() > 0) {
			$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
			$this->updateCustomer($customer, $store);
		}
	}


	private function getSpecialObject($customer) {

		$object = new Varien_Object();
		$object->setData('last_sync_date', date('Y-m-d H:i:s'));

		if ($customer->getId() > 0) {

			// connexion (lecture express depuis la base de données)
			// si non disponible, utilise la date d'inscription du client
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('maillog_read');

			$select = $read->select()
				->from($resource->getTableName('log_customer'), 'login_at')
				->where('customer_id = ?', $customer->getId())
				->order('log_id desc')->limit(1);

			$lastLogin = $read->fetchOne($select);
			$object->setData('last_login_date', (strlen($lastLogin) > 10) ? $lastLogin : $customer->getCreatedAt());

			// commandes
			// date, total, montant moyen
			$orders = Mage::getResourceModel('sales/order_collection');
			$orders->addFieldToFilter('customer_id', $customer->getId());
			$orders->addFieldToFilter('status', array('in' => array('processing', 'complete', 'closed')));
			$orders->setOrder('created_at', 'desc');

			if (($numberOfOrders = $orders->count()) > 0) {

				$object->setData('last_order_date',  $orders->getFirstItem()->getCreatedAt());
				$object->setData('last_order_total', number_format($orders->getFirstItem()->getBaseGrandTotal(), 2));

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

	private function getArrayForHistory($data) {

		$data = trim((is_array($data)) ? str_replace(array("Array\n(\n", "\n)"), '', print_r($data, true)) : $data);
		$data = (empty($data)) ? '(no result)' : $data;

		return $data;
	}

	private function addToHistory($store, $customer, $address, $subscriber, $status = 'pending', $result = '') {

		if (($store === 'admin') && (strlen($user = $this->getAdminUsername()) > 0))
			$store .= ' ('.$user.')';

		if (!$this->isBackground)
			$this->setId(null)->setStatus(null)->setEmail(null)->setCreatedAt(null)->setSyncAt(null)->setDetails(null);

		$this->setStatus($status);
		$this->setEmail($customer->getEmail());

		// on demande une synchro en arrière plan, il n'y a pas encore de détails
		//  on enregistre la date de création de la synchro
		//  on enregistre la première partie des détails
		if ($status === 'pending') {
			$this->setCreatedAt(date('Y-m-d H:i:s'));
			$this->setDetails(trim(
				'Store: '.$store.' / background-sync'."\n".
				'Customer: '.((is_object($customer) && ($customer->getId() > 0)) ? $customer->getId() : 'notset').' / '.
				'Address: '.((is_object($address) && ($address->getId() > 0)) ? $address->getId() : 'notset').' / '.
				'Subscriber: '.((is_object($subscriber) && ($subscriber->getId() > 0)) ? $subscriber->getId() : 'notset')
			));
		}
		// la synchro a été exécutée
		//  si la demande de synchro N'EST PAS exécutée en arrière plan
		//   on enregistre la date de création de la synchro
		//   on enregistre la date de la synchro
		//   on enregistre tous les détails
		// si la demande EST exécutée en arrière plan
		//   on enregistre la date de la synchro
		//   on enregistre la deuxième partie des détails
		else {
			if (in_array($this->getCreatedAt(), array('', '0000-00-00 00:00:00', null))) {
				$this->setCreatedAt(date('Y-m-d H:i:s'));
				$this->setSyncAt(date('Y-m-d H:i:s'));
				$this->setDetails(trim(
					'Store: '.$store."\n".
					'Customer: '.((is_object($customer) && ($customer->getId() > 0)) ? $customer->getId() : 'notset').' / '.
					'Address: '.((is_object($address) && ($address->getId() > 0)) ? $address->getId() : 'notset').' / '.
					'Subscriber: '.((is_object($subscriber) && ($subscriber->getId() > 0)) ? $subscriber->getId() : 'notset')."\n".
					$result
				));
			}
			else {
				$this->setSyncAt(date('Y-m-d H:i:s'));
				$this->setDetails(trim(
					$this->getDetails()."\n".
					$result
				));
			}
		}

		$this->save();

		// uniquement dans le back-office en mode pas arrière plan
		if (Mage::app()->getStore()->isAdmin() && !$this->isBackground) {
			$url = Mage::helper('adminhtml')->getUrl('*/maillog_sync/index', array('id' => $this->getId()));
			if ($status === 'success')
				Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('maillog')->__('All customer data HAS BEEN synchronized with %s (<a href="%s">sync-id:%d</a>, %s).', $this->model->getType(), $url, $this->getId(), $this->getEmail()));
			else if ($status === 'error')
				Mage::getSingleton('adminhtml/session')->addError(Mage::helper('maillog')->__('An error occured with customer data synchronization with %s (<a href="%s">sync-id:%d</a>, %s).', $this->model->getType(), $url, $this->getId(), $this->getEmail()));
			else if ($status === 'pending')
				Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('maillog')->__('All customer data WILL BE synchronized with %s (<a href="%s">sync-id:%d</a>, %s).', $this->model->getType(), $url, $this->getId(), $this->getEmail()));
		}
	}

	private function getAdminUsername() {
		return (is_object(Mage::getSingleton('admin/session')->getUser())) ? Mage::getSingleton('admin/session')->getUser()->getUsername() : '';
	}
}