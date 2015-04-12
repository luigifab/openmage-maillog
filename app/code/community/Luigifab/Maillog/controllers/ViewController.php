<?php
/**
 * Created M/24/03/2015
 * Updated D/05/04/2015
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

class Luigifab_Maillog_ViewController extends Mage_Core_Controller_Front_Action {

	public function indexAction() {

		$email = Mage::getResourceModel('maillog/email_collection');
		$email->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0));

		if (count($email) > 0)
			$this->getResponse()->setBody($email->getFirstItem()->printMail());
	}

	public function markAction() {

		$email = Mage::getResourceModel('maillog/email_collection');
		$email->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0));
		$email->addFieldToFilter('status', array('neq' => 'read'));

		if (count($email) > 0)
			$email->getFirstItem()->setStatus('read')->save();

		// read.gif (image de 1x1 pixel transparente)
		$data = 'R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

		$this->getResponse()->setHttpResponseCode(200);
		$this->getResponse()->setHeader('Content-Type', 'image/gif');
		$this->getResponse()->setHeader('Content-Length', strlen($data));
		$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
		$this->getResponse()->setHeader('Pragma', 'no-cache', true);
		$this->getResponse()->setBody(base64_decode($data));

		exit(0); // important
	}
}