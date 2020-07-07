<?php
/**
 * Created M/24/03/2015
 * Updated V/15/05/2020
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

class Luigifab_Maillog_ViewController extends Mage_Core_Controller_Front_Action {

	public function indexAction() {

		$email = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0))
			->setPageSize(1)
			->getFirstItem();

		if (!empty($email->getId()))
			$this->getResponse()->setBody($email->toHtml($this->getRequest()->getParam('nomark') == '1'));
	}

	public function downloadAction() {

		$email = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0))
			->setPageSize(1)
			->getFirstItem();

		if (!empty($email->getId()) && !empty($email->getData('mail_parts'))) {

			$parts = @unserialize(gzdecode($email->getData('mail_parts')), ['allowed_classes' => ['Zend_Mime_Part']]);
			$parts = (!empty($parts) && is_array($parts)) ? $parts : [];

			$nb = (int) $this->getRequest()->getParam('part', 0);

			foreach ($parts as $key => $part) {

				if ($key == $nb) {

					$data = rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
					$data = base64_decode($data);

					$type = $part->type;
					$disp = 'attachment; filename="'.$part->filename.'"';

					// affichage pdf dans le navigateur
					if (($type == 'application/octet-stream') && (mb_substr($part->filename, -4) == '.pdf'))
						$type = 'application/pdf';
					if ($type == 'application/pdf')
						$disp = 'inline; filename="'.$part->filename.'"';

					return $this->getResponse()
						->setHttpResponseCode(200)
						->setHeader('Content-Type', $type, true)
						->setHeader('Content-Length', strlen($data)) // surtout pas de mb_strlen
						->setHeader('Content-Disposition', $disp)
						->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
						->setHeader('Last-Modified', date('r'))
						->setBody($data);
				}
			}
		}

		return $this->getResponse()->setHttpResponseCode(404);
	}

	public function markAction() {

		$email = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0))
			->setPageSize(1)
			->getFirstItem();

		if (!empty($email->getId()) && ($email->getData('status') != 'read'))
			$email->setData('status', 'read')->save();

		// read.gif (image de 1x1 pixel transparente)
		$data = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');

		$this->getResponse()
			->setHttpResponseCode(200)
			->setHeader('Content-Type', 'image/gif', true)
			->setHeader('Content-Length', strlen($data)) // surtout pas de mb_strlen
			->setHeader('Content-Disposition', 'inline; filename="pixel.gif"')
			->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
			->setHeader('Last-Modified', date('r'))
			->setBody($data);
	}
}