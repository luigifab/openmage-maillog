<?php
/**
 * Created W/11/11/2015
 * Updated M/15/01/2019
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

class Luigifab_Maillog_Maillog_SyncController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog_sync');
	}

	public function indexAction() {

		if ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax'))) {
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_sync_grid')->toHtml());
		}
		else {
			$this->setUsedModuleName('Luigifab_Maillog');
			$lock = Mage::helper('maillog')->getLock();

			if (is_file($lock)) {
				Mage::getSingleton('adminhtml/session')->addNotice($this->__('All customers data synchronization is in progress.'));
				Mage::getSingleton('adminhtml/session')->addNotice('➤ '.file_get_contents($lock).' <script type="text/javascript">self.setTimeout(function () { self.location.reload(); }, 5000);</script>');
			}

			$last = ''; /* Mage::getResourceModel('maillog/sync_collection')
				->addFieldToSort('created_at', 'desc')
				->addFieldToFilter('batch', array('notnull' => true))
				->setPageLimit(1)
				->getFirstItem()
				->getData('batch'); */

			if (!empty($last))
				Mage::getSingleton('adminhtml/session')->addNotice($this->__('Last full synchronization (key %s) finished at %s, %d%% success.', $last, '--', 95));

			$this->loadLayout()->_setActiveMenu('tools/maillog_sync')->renderLayout();
		}
	}

	public function syncallAction() {
		$this->_redirect('*/*/index');
	}

	public function downloadAction() {

		$file = Mage::helper('maillog')->getImportStatus($this->getRequest()->getParam('file'));

		if (!empty($file)) {

			$ip = !empty(getenv('HTTP_X_FORWARDED_FOR')) ? explode(',', getenv('HTTP_X_FORWARDED_FOR')) : false;
			$ip = !empty($ip) ? trim(array_pop($ip)) : trim(getenv('REMOTE_ADDR'));
			$ip = (preg_match('#^::f{4}:\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$#', $ip) === 1) ? mb_substr($ip, 7) : $ip;

			Mage::log(sprintf('Client %s download %s', $ip, $file), Zend_Log::DEBUG, 'maillog.log');
			$this->_prepareDownloadResponse(basename($file), file_get_contents($file), mime_content_type($file));
		}
	}
}