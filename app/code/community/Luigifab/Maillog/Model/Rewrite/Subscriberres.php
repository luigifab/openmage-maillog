<?php
/**
 * Created W/26/09/2018
 * Updated J/10/01/2019
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

class Luigifab_Maillog_Model_Rewrite_Subscriberres extends Mage_Newsletter_Model_Resource_Subscriber {

	public function loadByEmail($subscriberEmail, $storeId = 0) {

		// par site web, il faut vérifier le store_id
		if (Mage::getStoreConfigFlag('customer/account_share/scope')) {

			if (empty($storeId))
				$storeId = Mage::app()->getStore()->getId();

			$select = $this->_read->select()->from($this->getMainTable())->where('subscriber_email=:subscriber_email AND store_id=:store_id');
			$result = $this->_read->fetchRow($select, array('subscriber_email' => $subscriberEmail, 'store_id' => $storeId));
		}
		else {
			$select = $this->_read->select()->from($this->getMainTable())->where('subscriber_email=:subscriber_email');
			$result = $this->_read->fetchRow($select, array('subscriber_email' => $subscriberEmail));
		}

		return $result ?: array();
	}

	public function loadByCustomer(Mage_Customer_Model_Customer $customer) {

		$select = $this->_read->select()->from($this->getMainTable())->where('customer_id=:customer_id');
		$result = $this->_read->fetchRow($select, array('customer_id' => $customer->getId()));

		if ($result)
			return $result;

		// par site web, il faut vérifier le store_id
		if (Mage::getStoreConfigFlag('customer/account_share/scope')) {

			$select = $this->_read->select()->from($this->getMainTable())->where('subscriber_email=:subscriber_email AND store_id=:store_id');
			$result = $this->_read->fetchRow($select, array('subscriber_email' => $customer->getData('email'),
				'store_id' => $customer->getData('store_id')));
		}
		else {
			$select = $this->_read->select()->from($this->getMainTable())->where('subscriber_email=:subscriber_email');
			$result = $this->_read->fetchRow($select, array('subscriber_email' => $customer->getData('email')));
		}

		return $result ?: array();
	}
}