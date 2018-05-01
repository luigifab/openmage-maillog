<?php
/**
 * Created W/11/11/2015
 * Updated J/22/03/2018
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

class Luigifab_Maillog_Maillog_SyncController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog_sync');
	}

	public function indexAction() {

		if ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_sync_grid')->toHtml());
		else
			$this->loadLayout()->_setActiveMenu('tools/maillog_sync')->renderLayout();
	}

	public function downloadAction() {

		$basedir = Mage::getBaseDir('var').'/'.Mage::getStoreConfig('maillog/'.$this->getRequest()->getParam('file').'/directory').'/';

		if (is_file($basedir.'status.dat') && is_readable($basedir.'status.dat')) {

			$file = @unserialize(file_get_contents($basedir.'status.dat'));

			if (!empty($file['file'])) {

				$file = $file['file'];
				$file = $basedir.'done/'.substr($file, 0, strpos($file, '-') - 2).'/'.$file;

				$ip = (!empty(getenv('HTTP_X_FORWARDED_FOR'))) ? explode(',', getenv('HTTP_X_FORWARDED_FOR')) : false;
				$ip = (!empty($ip)) ? array_pop($ip) : getenv('REMOTE_ADDR');
				$ip = (preg_match('#^::ffff:[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$#', $ip) === 1) ? substr($ip, 7) : $ip;

				Mage::log(sprintf('Client %s download %s', $ip, $file), Zend_Log::DEBUG, 'maillog.log');

				if (is_file($file) && is_readable($file))
					$this->_prepareDownloadResponse(basename($file), file_get_contents($file), mime_content_type($file));
			}
		}
	}
}