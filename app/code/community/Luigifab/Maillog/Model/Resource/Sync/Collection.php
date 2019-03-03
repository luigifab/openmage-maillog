<?php
/**
 * Created W/11/11/2015
 * Updated S/25/08/2018
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

class Luigifab_Maillog_Model_Resource_Sync_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {

	public function _construct() {
		$this->_init('maillog/sync');
	}

	public function addFieldToSort($field, $direction) {
		$this->getSelect()->order($field.' '.$direction);
		return $this;
	}

	public function setPageLimit($itemCountPerPage, $offset = 1) {
		$this->getSelect()->limit($itemCountPerPage, $itemCountPerPage * ($offset - 1));
		return $this;
	}

	public function deleteAll() {

		$where = $this->getSelect()->getPart(Zend_Db_Select::WHERE);
		if (is_array($where) && !empty($where))
			Mage::getSingleton('core/resource')->getConnection('core_write')->delete($this->getMainTable(), implode(' ', $where));

		return $this;
	}
}