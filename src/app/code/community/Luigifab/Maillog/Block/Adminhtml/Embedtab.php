<?php
/**
 * Created V/15/05/2015
 * Updated S/16/12/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://github.com/luigifab/openmage-maillog
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

class Luigifab_Maillog_Block_Adminhtml_Embedtab extends Mage_Adminhtml_Block_Abstract implements Mage_Adminhtml_Block_Widget_Tab_Interface {

	protected $_ajax = false; // remove <update handle=""> when true

	public function getTabLabel() {
		return $this->__('Transactional emails');
	}

	public function getTabTitle() {
		return '';
	}

	public function isHidden() {
		return false;
	}

	public function canShowTab() {

		if (is_object($order = Mage::registry('current_order')))
			return empty($order->getId()) ? false : Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/maillog');

		if (is_object($customer = Mage::registry('current_customer')))
			return empty($customer->getId()) ? false : Mage::getSingleton('admin/session')->isAllowed('customer/manage/actions/maillog');

		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog');
	}

	public function getTabUrl() {

		if (!$this->_ajax)
			return '#';

		$query = empty($query = getenv('QUERY_STRING')) ? '' : '?'.$query;

		if (is_object($order = Mage::registry('current_order')))
			return $this->getUrl('*/maillog_history/index', ['back' => 'order', 'bid' => $order->getId()]).$query;

		if (is_object($customer = Mage::registry('current_customer')))
			return $this->getUrl('*/maillog_history/index', ['back' => 'customer', 'bid' => $customer->getId()]).$query;

		return $this->getUrl('*/maillog_history/index').$query;
	}

	public function getTabClass() {
	    return $this->_ajax ? 'ajax' : '';
	}

	public function getClass() {
		return $this->_ajax ? 'ajax' : '';
	}

	protected function _toHtml() {
		return $this->_ajax ? '' : $this->getLayout()->getBlock('adminhtml_maillog_embedtab')->toHtml();
	}
}