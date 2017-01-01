<?php
/**
 * Created D/22/03/2015
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

class Luigifab_Maillog_Maillog_HistoryController extends Mage_Adminhtml_Controller_Action {

	protected function _validateSecretKey() {

		$result = parent::_validateSecretKey();

		if (Mage::getSingleton('admin/session')->isLoggedIn() && ($this->getFullActionName() === 'adminhtml_maillog_history_view') && !$result) {
			$this->getRequest()->setParam(Mage_Adminhtml_Model_Url::SECRET_KEY_PARAM_NAME, Mage::getSingleton('adminhtml/url')->getSecretKey());
			$result = parent::_validateSecretKey();
		}

		return $result;
	}

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog');
	}

	public function indexAction() {

		if (!is_null($this->getRequest()->getParam('isAjax')) && !is_null($this->getRequest()->getParam('back')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_history_tab')->toHtml());
		else if (!is_null($this->getRequest()->getParam('isAjax')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_history_grid')->toHtml());
		else
			$this->loadLayout()->_setActiveMenu('tools/maillog')->renderLayout();
	}

	public function viewAction() {

		$email = Mage::getModel('maillog/email')->load(intval($this->getRequest()->getParam('id', 0)));

		if ($email->getId() > 0) {
			Mage::register('current_email', $email);
			$this->loadLayout()->_setActiveMenu('tools/maillog')->renderLayout();
		}
		else {
			$this->redirectBack();
		}
	}

	public function deleteAction() {

		$this->setUsedModuleName('Luigifab_Maillog');

		try {
			if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {

				if ((($id = $this->getRequest()->getParam('id', false)) === false) || !is_numeric($id))
					Mage::throwException($this->__('The <em>%s</em> field is a required field.', 'id'));

				Mage::getModel('maillog/email')->load($id)->delete();
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d has been successfully deleted.', $id));
			}
		}
		catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}

		$this->redirectBack();
	}

	public function resendAction() {

		$this->setUsedModuleName('Luigifab_Maillog');

		try {
			if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {

				if ((($id = $this->getRequest()->getParam('id', false)) === false) || !is_numeric($id))
					Mage::throwException($this->__('The <em>%s</em> field is a required field.', 'id'));

				Mage::getModel('maillog/email')->load($id)->setStatus('pending')->save()->send();
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d has been successfully resent (warning, dates are not updated).', $id));
			}
		}
		catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}

		$this->redirectBack();
	}

	private function redirectBack() {

		if ($this->getRequest()->getParam('back') === 'order') {
			$this->_redirect('*/sales_order/view',
				array('order_id' => $this->getRequest()->getParam('bid'), 'active_tab' => 'maillog_grid_order'));
		}
		else if ($this->getRequest()->getParam('back') === 'customer') {
			$this->_redirect('*/customer/edit',
				array('id' => $this->getRequest()->getParam('bid'), 'back' => 'edit', 'tab' => 'customer_info_tabs_maillog_grid_customer'));
		}
		else {
			$this->_redirect('*/*/index');
		}
	}
}