<?php
/**
 * Created S/14/11/2015
 * Updated M/08/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Maillog_Model_Bounce extends Mage_Core_Model_Abstract {

	public function _construct() {
		$this->_init('maillog/bounce');
	}

	public function load($data, $field = null) {
		preg_match_all('#(?:<)([^>]+)(?:>)#', $data, $emails);
		return (isset($emails[1][0])) ? parent::load($emails[1][0], 'email') : parent::load($data, 'email');
	}

	public function isBounce() {
		return ($this->getId() > 0);
	}
}