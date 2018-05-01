<?php
/**
 * Created J/24/08/2017
 * Updated J/22/03/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
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

class Luigifab_Maillog_Model_Source_Bounce extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {

	public function getAllOptions() {

		if (is_null($this->_options)) {

			// pour les valeurs voir aussi
			// self::getBounceIds() et
			// Luigifab_Maillog_Model_Observer::bouncesFileImport() et
			// Luigifab_Maillog_Model_Observer::updateCustomersDatabase()
			$help = Mage::helper('maillog');
			$this->_options = array(
				array('value' => 0, 'label' => $help->__('No')),
				array('value' => 1, 'label' => $help->__('Yes')),
				array('value' => 2, 'label' => $help->__('Yes - forced by admin')),
				array('value' => 3, 'label' => $help->__('No - forced by admin')),
				array('value' => 4, 'label' => $help->__('No - forced by customer'))
			);
		}

		return $this->_options;
	}

	public function isBounce($value) {

		if (strpos($value, '@') !== false) {

			$email = (strpos($value, ',') !== false)  ? explode(',', $value) : $value;
			$email = (is_array($value)) ? array_shift($value) : $value;
			$email = (stripos($email, '<') !== false) ? substr($email, stripos($email, '<') + 1) : $email;
			$email = (stripos($email, '>') !== false) ? substr($email, 0, stripos($email, '>')) : $email;
			$value = -1;

			if (!empty($email)) {
				$customer = Mage::getResourceModel('customer/customer_collection')
					->addAttributeToFilter('email', $email)
					->addAttributeToSelect('is_bounce')
					->setPageSize(1)
					->getFirstItem();
				$value = $customer->getData('is_bounce');
			}
		}

		return (in_array(intval($value), $this->getBounceIds())) ? true : false;
	}

	public function getBounceIds() {
		return array(1, 2);
	}
}