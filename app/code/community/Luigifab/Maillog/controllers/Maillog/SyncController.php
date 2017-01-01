<?php
/**
 * Created W/11/11/2015
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

class Luigifab_Maillog_Maillog_SyncController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('tools/maillog_sync');
	}

	public function indexAction() {

		if (!is_null($this->getRequest()->getParam('isAjax')))
			$this->getResponse()->setBody($this->getLayout()->createBlock('maillog/adminhtml_sync_grid')->toHtml());
		else
			$this->loadLayout()->_setActiveMenu('tools/maillog_sync')->renderLayout();
	}

	public function downloadAction() {

		$basedir = realpath(Mage::getBaseDir('var').'/'.Mage::getStoreConfig('maillog/'.$this->getRequest()->getParam('file').'/directory')).'/';

		if (is_file($basedir.'status.dat') && is_readable($basedir.'status.dat')) {

			$file = unserialize(file_get_contents($basedir.'status.dat'));
			$file = (isset($file['file'])) ? $basedir.$file['file'] : null;

			if (is_file($file) && is_readable($file))
				$this->_prepareDownloadResponse(basename($file), file_get_contents($file), mime_content_type($file));
		}
	}
}