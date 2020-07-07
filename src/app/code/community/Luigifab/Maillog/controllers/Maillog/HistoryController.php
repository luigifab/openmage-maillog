<?php
/**
 * Created D/22/03/2015
 * Updated J/18/06/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
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

class Luigifab_Maillog_Maillog_HistoryController extends Mage_Adminhtml_Controller_Action {

	protected function _validateSecretKey() {

		$result = parent::_validateSecretKey();

		if (!$result && ($this->getFullActionName() == 'adminhtml_maillog_history_view') && Mage::getSingleton('admin/session')->isLoggedIn()) {
			$this->getRequest()->setParam(Mage_Adminhtml_Model_Url::SECRET_KEY_PARAM_NAME, Mage::getSingleton('adminhtml/url')->getSecretKey());
			$result = parent::_validateSecretKey();
		}

		return $result;
	}

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog');
	}

	protected function _redirectBack() {

		if ($this->getRequest()->getParam('back') == 'order')
			$this->_redirect('*/sales_order/view', ['order_id' => $this->getRequest()->getParam('bid'), 'active_tab' => 'maillog_order_grid']);
		else if ($this->getRequest()->getParam('back') == 'customer')
			$this->_redirect('*/customer/edit', ['id' => $this->getRequest()->getParam('bid'), 'back' => 'edit', 'tab' => 'customer_info_tabs_maillog_customer_grid']);
		else
			$this->_redirect('*/*/index');
	}

	public function getUsedModuleName() {
		return 'Luigifab_Maillog';
	}

	public function loadLayout($ids = null, $generateBlocks = true, $generateXml = true) {
		$this->_title($this->__('Tools'))->_title($this->__('Transactional emails'));
		parent::loadLayout($ids, $generateBlocks, $generateXml);
		$this->_setActiveMenu('tools/maillog');
		return $this;
	}

	public function indexAction() {

		if ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_history_grid')->toHtml());
		else
			$this->loadLayout()->renderLayout();
	}

	public function testAction() {

		if (Mage::getStoreConfigFlag('maillog/email/enabled'))
			$this->_redirect('*/*/view', ['id' => Mage::getSingleton('maillog/observer')->sendEmailReport(null, true)]);
		else
			$this->_redirect('*/*/index');
	}

	public function viewAction() {

		$email = Mage::getModel('maillog/email')->load((int) $this->getRequest()->getParam('id', 0));

		if (!empty($email->getId())) {
			Mage::register('current_email', $email);
			$this->loadLayout()->renderLayout();
		}
		else {
			$this->_redirectBack();
		}
	}

	public function deleteAction() {

		try {
			if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {

				if (empty($id = $this->getRequest()->getParam('id')) || !is_numeric($id))
					Mage::throwException($this->__('The <em>%s</em> field is a required field.', 'id'));

				Mage::getModel('maillog/email')->load($id)->delete();
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d has been successfully deleted.', $id));
			}
		}
		catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}

		$this->_redirectBack();
	}

	public function resendAction() {

		try {
			if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {

				if (empty($id = $this->getRequest()->getParam('id')) || !is_numeric($id))
					Mage::throwException($this->__('The <em>%s</em> field is a required field.', 'id'));

				Mage::getModel('maillog/email')->load($id)->setData('status', 'pending')->save();
				Mage::getSingleton('adminhtml/session')->addNotice($this->__('Email number %d will be resent.', $id));
			}
		}
		catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}

		$this->_redirectBack();
	}
}