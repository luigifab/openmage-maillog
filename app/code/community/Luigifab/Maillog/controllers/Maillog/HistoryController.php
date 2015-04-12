<?php
/**
 * Created D/22/03/2015
 * Updated S/04/04/2015
 * Version 6
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

class Luigifab_Maillog_Maillog_HistoryController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog');
	}

	public function indexAction() {

		if ($this->getRequest()->getParam('isAjax', false))
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
			$this->_redirect('*/*/index');
		}
	}

	public function viewmailAction() {

		$email = Mage::getModel('maillog/email')->load(intval($this->getRequest()->getParam('id', 0)));

		if ($email->getId() > 0)
			$this->getResponse()->setBody($email->printMail(true));
	}

	public function deleteAction() {

		$this->setUsedModuleName('Luigifab_Maillog');

		try {
			if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {

				if (($id = $this->getRequest()->getParam('id', false)) === false)
					Mage::throwException($this->__('The <em>%s</em> field is a required value.', 'id'));

				Mage::getModel('maillog/email')->load($id)->delete();
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d has been successfully deleted.', $id));
			}
		}
		catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}

		$this->_redirect('*/*/index');
	}

	public function resendAction() {

		$this->setUsedModuleName('Luigifab_Maillog');

		try {
			if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {

				if (($id = $this->getRequest()->getParam('id', false)) === false)
					Mage::throwException($this->__('The <em>%s</em> field is a required value.', 'id'));

				Mage::getModel('maillog/email')->load($id)->send(true);
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d has been successfully resent.', $id));
			}
		}
		catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}

		$this->_redirect('*/*/index');
	}
}