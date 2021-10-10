<?php
/**
 * Created W/11/11/2015
 * Updated M/05/10/2021
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Maillog_SyncController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog_sync');
	}

	public function getUsedModuleName() {
		return 'Luigifab_Maillog';
	}

	public function loadLayout($ids = null, $generateBlocks = true, $generateXml = true) {
		$this->_title($this->__('Tools'))->_title($this->__('Customers synchronization'));
		parent::loadLayout($ids, $generateBlocks, $generateXml);
		$this->_setActiveMenu('tools/maillog_sync');
		return $this;
	}

	public function syncallAction() {
		$this->_redirect('*/*/index');
	}

	public function indexAction() {

		if ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_sync_grid')->toHtml());
		else
			$this->loadLayout()->renderLayout();
	}

	public function downloadAction() {

		$file = Mage::helper('maillog')->getImportStatus($this->getRequest()->getParam('file'));

		if (!empty($file)) {

			$ip = empty(getenv('HTTP_X_FORWARDED_FOR')) ? false : explode(',', getenv('HTTP_X_FORWARDED_FOR'));
			$ip = empty($ip) ? getenv('REMOTE_ADDR') : reset($ip);
			$ip = (preg_match('#^::f{4}:\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$#', $ip) === 1) ? substr($ip, 7) : $ip;

			Mage::log(sprintf('Client %s download %s', $ip, $file), Zend_Log::INFO, 'maillog.log');
			$this->_prepareDownloadResponse(basename($file), file_get_contents($file), mime_content_type($file));
		}
	}
}