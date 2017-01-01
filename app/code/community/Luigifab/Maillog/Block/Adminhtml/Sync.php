<?php
/**
 * Created W/11/11/2015
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

class Luigifab_Maillog_Block_Adminhtml_Sync extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$type = (strlen(Mage::getStoreConfig('maillog/sync/type')) > 0) ?
			Mage::getSingleton('maillog/system_'.Mage::getStoreConfig('maillog/sync/type'))->getType() : false;

		$this->_controller = 'adminhtml_sync';
		$this->_blockGroup = 'maillog';
		$this->_headerText = ($type) ? $this->__('Customer synchonization with %s', $type) : $this->__('Customer synchonization');

		$this->_removeButton('add');
	}
}