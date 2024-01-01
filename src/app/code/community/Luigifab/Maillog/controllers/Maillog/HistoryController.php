<?php
/**
 * Created D/22/03/2015
 * Updated D/17/12/2023
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

class Luigifab_Maillog_Maillog_HistoryController extends Mage_Adminhtml_Controller_Action {

	protected function _validateSecretKey() {

		$result = parent::_validateSecretKey();

		if (!$result && ($this->getFullActionName() == 'adminhtml_maillog_history_view')) {
			$this->getRequest()->setParam(Mage_Adminhtml_Model_Url::SECRET_KEY_PARAM_NAME, Mage::getSingleton('adminhtml/url')->getSecretKey());
			$result = parent::_validateSecretKey();
		}

		return $result;
	}

	protected function _isAllowed() {

		if ($this->getRequest()->getParam('back') == 'order')
			return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/maillog');

		if ($this->getRequest()->getParam('back') == 'customer')
			return Mage::getSingleton('admin/session')->isAllowed('customer/manage/actions/maillog');

		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog');
	}

	protected function _redirectBack() {

		$request = $this->getRequest();

		if ($request->getParam('back') == 'order')
			$this->_redirect('*/sales_order/view', ['order_id' => $request->getParam('bid'), 'active_tab' => 'maillog_order_grid']);
		else if ($request->getParam('back') == 'customer')
			$this->_redirect('*/customer/edit', ['id' => $request->getParam('bid'), 'back' => 'edit', 'tab' => 'customer_info_tabs_maillog_customer_grid']);
		else
			$this->_redirect('*/*/index');
	}

	public function getUsedModuleName() {
		return 'Luigifab_Maillog';
	}

	public function loadLayout($ids = null, $generateBlocks = true, $generateXml = true) {
		parent::loadLayout($ids, $generateBlocks, $generateXml);
		$this->_title($this->__('Tools'))->_title($this->__('Transactional emails'))->_setActiveMenu('tools/maillog');
		return $this;
	}

	public function indexAction() {

		if ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_history_grid')->toHtml());
		else
			$this->loadLayout()->renderLayout();
	}

	public function previewAction() {

		$this->loadLayout();
		$block = $this->getLayout()->createBlock('adminhtml/widget_button')
			->setData('type', 'button')
			->setData('label', $this->__('Back'))
			->setData('class', 'back')
			->setData('onclick', 'setLocation(\''.$this->getUrl('*/system_config/edit', ['section' => 'maillog']).'\');');

		$html  = '<div class="content-header"><table cellspacing="0"><tbody><tr><td><h3 class="icon-head">'.$this->__('Transactional emails').'</h3></td><td class="form-buttons">'.$block->toHtml().'</td></tr></tbody></table></div>';
		$html .= '<div class="eprev">'.Mage::getSingleton('maillog/report')->send(null, true, true).'</div>';

		$this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('core/text')->setText($html));
		$this->renderLayout();
	}

	public function viewAction() {

		$email = Mage::getModel('maillog/email')->load((int) $this->getRequest()->getParam('id', 0));

		if (empty($email->getId())) {
			$this->_redirectBack();
		}
		else {
			Mage::register('current_email', $email);
			$this->loadLayout()->renderLayout();
		}
	}

	public function sendAction() {

		try {
			$id = (int) $this->getRequest()->getParam('id', 0);
			if (!empty($id) && !Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {
				$email = Mage::getModel('maillog/email')->load($id);
				if ($email->getData('status') == 'pending') {
					$email->sendNow();
					Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d was sent.', $id));
				}
				else if (!in_array($email->getData('status'), ['notsent', 'bounce', ''])) {
					$email->setData('status', 'pending')->setData('exception', null)->save();
					Mage::getSingleton('adminhtml/session')->addNotice($this->__('Email number %d will be sent again.', $id));
				}
			}
		}
		catch (Throwable $t) {
			Mage::getSingleton('adminhtml/session')->addError($t->getMessage());
		}

		$this->_redirect('*/*/view', $this->getRequest()->getParams());
	}

	public function deleteAction() {

		try {
			$id = (int) $this->getRequest()->getParam('id', 0);
			if (!empty($id) && !Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {
				Mage::getModel('maillog/email')->load($id)->delete();
				Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Email number %d has been successfully deleted.', $id));
			}
		}
		catch (Throwable $t) {
			Mage::getSingleton('adminhtml/session')->addError($t->getMessage());
		}

		$this->_redirectBack();
	}
}