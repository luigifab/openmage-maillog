<?php
/**
 * Created M/24/03/2015
 * Updated D/03/05/2015
 * Version 8
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

		$email = $this->loadEmail();

		if ($email->getId() > 0)
			$this->getResponse()->setBody($email->printOnlineMail(false, true));
	}

	public function downloadAction() {

		$email = $this->loadEmail();

		if (($email->getId() > 0) && !is_null($email->getMailParts())) {

			$parts = unserialize(gzdecode($email->getMailParts()));
			$nb = intval($this->getRequest()->getParam('part', 0));

			foreach ($parts as $key => $part) {

				if ($key == $nb) {

					$data = rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
					$data = base64_decode($data);

					$this->getResponse()->setHttpResponseCode(200);
					$this->getResponse()->setHeader('Content-Type', $part->type, true);
					$this->getResponse()->setHeader('Content-Length', strlen($data));
					$this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="'.$part->filename.'"');
					$this->getResponse()->setHeader('Last-Modified', date('r'));
					$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
					$this->getResponse()->setHeader('Pragma', 'no-cache', true);
					$this->getResponse()->setBody($data);
					return;
				}
			}
		}

		$this->getResponse()->setHttpResponseCode(404);
	}

	public function markAction() {

		$email = $this->loadEmail();

		if (($email->getId() > 0) && ($email->getStatus() !== 'read'))
			$email->setStatus('read')->save();

		// read.gif (image de 1x1 pixel transparente)
		$data = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');

		$this->getResponse()->setHttpResponseCode(200);
		$this->getResponse()->setHeader('Content-Type', 'image/gif', true);
		$this->getResponse()->setHeader('Content-Length', strlen($data));
		$this->getResponse()->setHeader('Content-Disposition', 'inline; filename="pixel.gif"');
		$this->getResponse()->setHeader('Last-Modified', date('r'));
		$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
		$this->getResponse()->setHeader('Pragma', 'no-cache', true);
		$this->getResponse()->setBody($data);
	}

	private function loadEmail() {

		$email = Mage::getResourceModel('maillog/email_collection');
		$email->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0));

		return $email->getFirstItem();
	}
}