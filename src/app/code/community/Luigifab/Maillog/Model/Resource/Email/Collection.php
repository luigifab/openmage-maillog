<?php
/**
 * Created D/22/03/2015
 * Updated J/14/05/2020
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

class Luigifab_Maillog_Model_Resource_Email_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {

	public function _construct() {
		$this->_init('maillog/email');
	}

	public function deleteAll() {

		$where = $this->getSelect()->getPart(Zend_Db_Select::WHERE);
		if (is_array($where) && !empty($where))
			Mage::getSingleton('core/resource')->getConnection('core_write')->delete($this->getMainTable(), implode(' ', $where));

		return $this;
	}
}