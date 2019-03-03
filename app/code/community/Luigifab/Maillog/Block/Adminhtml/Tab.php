<?php
/**
 * Created V/15/05/2015
 * Updated M/05/02/2019
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

use Mage_Adminhtml_Block_Widget_Tab_Interface as Mage_Adminhtml_BWT_Interface;
class Luigifab_Maillog_Block_Adminhtml_Tab extends Mage_Adminhtml_Block_Abstract implements Mage_Adminhtml_BWT_Interface {

	public function getTabLabel() {
		return $this->__('Transactional emails');
	}

	public function getTabTitle() {
		return null;
	}

	public function isHidden() {
		return false;
	}

	public function canShowTab() {

		if (!Mage::getSingleton('admin/session')->isAllowed('tools/maillog'))
			return false;
		else if (is_object(Mage::registry('current_order')) && !empty(Mage::registry('current_order')->getId()))
			return true;
		else if (is_object(Mage::registry('current_customer')) && !empty(Mage::registry('current_customer')->getId()))
			return true;
		else
			return false;
	}


	protected function _toHtml() {
		return !empty($block = $this->getLayout()->getBlock('adminhtml_maillog_embedtab')) ? $block->toHtml() : parent::_toHtml();
	}
}