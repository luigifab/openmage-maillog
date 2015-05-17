<?php
/**
 * Created V/15/05/2015
 * Updated S/16/05/2015
 * Version 5
 *
 * Copyright 2015 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Maillog_Block_Adminhtml_History_Tab extends Luigifab_Maillog_Block_Adminhtml_History_Grid
 implements Mage_Adminhtml_Block_Widget_Tab_Interface {

	public function getTabLabel() {
		return $this->__('Transactionnal emails');
	}

	public function getTabTitle() {
		return null;
	}

	public function canShowTab() {
		return true;
	}

	public function isHidden() {
		return false;
	}


	public function __construct() {

		parent::__construct();

		if (!is_object(Mage::registry('current_order')) && ($this->getRequest()->getParam('back') === 'order')) {
			$order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('bid'));
			Mage::register('current_order', $order);
		}
		else if (!is_object(Mage::registry('current_customer')) && ($this->getRequest()->getParam('back') === 'customer')) {
			$customer = Mage::getModel('customer/customer')->load($this->getRequest()->getParam('bid'));
			Mage::register('current_customer', $customer);
		}

		if (is_object(Mage::registry('current_order'))) {
			$this->setId('maillog_grid_order');
			$this->back = array('back' => 'order', 'bid' => Mage::registry('current_order')->getId());
			$this->_defaultFilter = array(
				'mail_recipients' => Mage::registry('current_order')->getCustomerEmail(),
				'mail_subject' => Mage::registry('current_order')->getIncrementId()
			);
		}
		else if (is_object(Mage::registry('current_customer'))) {
			$this->setId('maillog_grid_customer');
			$this->back = array('back' => 'customer', 'bid' => Mage::registry('current_customer')->getId());
			$this->_defaultFilter = array('mail_recipients' => Mage::registry('current_customer')->getEmail());
		}
	}

	protected function _prepareCollection() {

		$collection = Mage::getResourceModel('maillog/email_collection');

		if (is_object(Mage::registry('current_order'))) {
			$collection->addFieldToFilter('mail_recipients', array('like' => '%'.Mage::registry('current_order')->getCustomerEmail().'%'));
			//$collection->addFieldToFilter('mail_subject', array('like' => '%'.Mage::registry('current_order')->getIncrementId().'%'));
		}
		else if (is_object(Mage::registry('current_customer'))) {
			$collection->addFieldToFilter('mail_recipients', array('like' => '%'.Mage::registry('current_customer')->getEmail().'%'));
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
}