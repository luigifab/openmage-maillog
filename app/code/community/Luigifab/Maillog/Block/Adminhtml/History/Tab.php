<?php
/**
 * Created V/15/05/2015
 * Updated M/27/02/2018
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

use Mage_Adminhtml_Block_Widget_Tab_Interface as Mage_Adminhtml_BWT_Interface;
class Luigifab_Maillog_Block_Adminhtml_History_Tab extends Luigifab_Maillog_Block_Adminhtml_History_Grid implements Mage_Adminhtml_BWT_Interface {

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

	public function getId() {

		if (is_object($order = Mage::registry('current_order')))
			return 'maillog_order_grid_'.$order->getId();
		else if (is_object($customer = Mage::registry('current_customer')))
			return 'maillog_customer_grid_'.$customer->getId();
	}


	public function __construct() {

		parent::__construct();

		if (!is_object(Mage::registry('current_order')) && ($this->getRequest()->getParam('back') == 'order')) {
			$order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('bid'));
			Mage::register('current_order', $order);
		}
		else if (!is_object(Mage::registry('current_customer')) && ($this->getRequest()->getParam('back') == 'customer')) {
			$customer = Mage::getModel('customer/customer')->load($this->getRequest()->getParam('bid'));
			Mage::register('current_customer', $customer);
		}

		if (is_object($order = Mage::registry('current_order'))) {
			$this->setId('maillog_order_grid_'.$order->getId());
			$this->back = array('back' => 'order', 'bid' => $order->getId());
			$this->_defaultFilter = array('mail_subject' => $order->getData('increment_id'));
		}
		else if (is_object($customer = Mage::registry('current_customer'))) {
			$this->setId('maillog_customer_grid_'.$customer->getId());
			$this->back = array('back' => 'customer', 'bid' => $customer->getId());
			$this->_defaultFilter = array('mail_recipients' => $customer->getData('email'));
		}
	}

	protected function _prepareCollection() {

		$collection = Mage::getResourceModel('maillog/email_collection');

		if (is_object($order = Mage::registry('current_order'))) {
			$collection->addFieldToFilter('mail_recipients', array('like' => '%'.$order->getData('customer_email').'%'));
			//$collection->addFieldToFilter('mail_subject',  array('like' => '%'.$order->getData('increment_id').'%'));
		}
		else if (is_object($customer = Mage::registry('current_customer'))) {
			$collection->addFieldToFilter('mail_recipients', array('like' => '%'.$customer->getData('email').'%'));
		}

		$this->setCollection($collection);
		return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
	}

	public function getGridUrl($params = array()) {
		return $this->getUrl('*/maillog_history/index', array_merge($params, $this->back));
	}

	public function getRowUrl($row) {
		return $this->getUrl('*/maillog_history/view', array_merge($this->back, array('id' => $row->getId())));
	}


	public function _toHtml() {
		return ($this->getLayout()->getBlock('info')) ? $this->getLayout()->getBlock('info')->toHtml().parent::_toHtml() : parent::_toHtml();
	}
}