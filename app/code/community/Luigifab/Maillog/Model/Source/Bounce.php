<?php
/**
 * Created J/24/08/2017
 * Updated V/25/01/2019
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

class Luigifab_Maillog_Model_Source_Bounce extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {

	public function getAllOptions($withEmpty = true, $defaultValues = false) {

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

	public function isBounce($data) {

		if (mb_strpos($data, '@') !== false) {

			$email = (mb_strpos($data, ',') !== false)  ? explode(',', $data) : $data;
			$email = is_array($email) ? array_shift($email) : $email;
			$email = (mb_strpos($email, '<') !== false) ? mb_substr($email, mb_strpos($email, '<') + 1) : $email;
			$email = (mb_strpos($email, '>') !== false) ? mb_substr($email, 0, mb_strpos($email, '>')) : $email;
			$email = trim($email);
			$data  = -1;

			if (!empty($email)) {
				$data = Mage::getResourceModel('customer/customer_collection')
					->addAttributeToFilter('email', $email)
					->addAttributeToSelect('is_bounce')
					->getFirstItem()
					->getData('is_bounce');
			}
		}

		return in_array(intval($data), $this->getBounceIds());
	}

	public function getBounceIds() {
		return array(1, 2);
	}
}