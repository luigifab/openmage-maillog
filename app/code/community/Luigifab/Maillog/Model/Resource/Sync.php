<?php
/**
 * Created W/11/11/2015
 * Updated J/16/01/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

class Luigifab_Maillog_Model_Resource_Sync extends Mage_Core_Model_Resource_Db_Abstract {

	public function _construct() {
		$this->_init('maillog/sync', 'sync_id');
	}

	public function _getCharacterSet() {

		if (empty($this->names)) {
			$this->names = $this->getReadConnection()->fetchAll('SHOW SESSION VARIABLES LIKE "character_set_client";');
			$this->names = empty($this->names[0]['Value']) ? 'utf8' : $this->names[0]['Value'];
		}

		return $this->names;
	}

	protected function _beforeLoad() {
		$this->getReadConnection()->query('SET NAMES utf8mb4;');
		return $this;
	}

	protected function _afterLoad() {
		$this->getReadConnection()->query('SET NAMES '.$this->_getCharacterSet().';');
		return $this;
	}

	protected function _beforeSave() {
		$this->getReadConnection()->query('SET NAMES utf8mb4;');
		return $this;
	}

	protected function _afterSave() {
		$this->getReadConnection()->query('SET NAMES '.$this->_getCharacterSet().';');
		return $this;
	}
}