<?php
/**
 * Created M/24/03/2015
 * Updated D/28/08/2022
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
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

		Mage::register('turpentine_nocache_flag', true, true);

		$email = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0))
			->setPageSize(1)
			->getFirstItem();

		if (!empty($email->getId())) {
			Mage::getSingleton('core/translate')->setLocale(Mage::getStoreConfig('general/locale/code'))->init('adminhtml', true);
			$this->getResponse()
				->setHttpResponseCode(200)
				->setHeader('Content-Type', 'text/html; charset=utf-8', true)
				->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
				->setBody($email->toHtml($this->getRequest()->getParam('nomark') == '1'));
		}
	}

	public function downloadAction() {

		Mage::register('turpentine_nocache_flag', true, true);

		$email = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0))
			->setPageSize(1)
			->getFirstItem();

		if (!empty($email->getId()) && !empty($email->getData('mail_parts'))) {

			$parts = $email->getEmailParts();
			$nb    = (int) $this->getRequest()->getParam('part', 0);

			foreach ($parts as $key => $part) {

				if ($key == $nb) {

					$data = rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
					$data = base64_decode($data);

					$type = $part->type;
					$disp = 'attachment; filename="'.$part->filename.'"';

					if ($type == 'application/x-gzip') {
						$found = true;
						$this->getResponse()
							->setHttpResponseCode(200)
							->setHeader('Content-Type', $type, true)
							->setHeader('Content-Disposition', $disp, true)
							->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
							->setBody($data);
					}
					else {
						// affichage pdf dans le navigateur
						if (($type == 'application/octet-stream') && (mb_substr($part->filename, -4) == '.pdf'))
							$type = 'application/pdf';
						if ($type == 'application/pdf')
							$disp = 'inline; filename="'.$part->filename.'"';

						// compresse avec gzip le fichier
						$found = true;
						$this->getResponse()
							->setHttpResponseCode(200)
							->setHeader('Content-Type', $type, true)
							->setHeader('Content-Disposition', $disp, true)
							->setHeader('Content-Encoding', 'gzip', true)
							->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
							->setBody(gzencode($data, 9));
					}
				}
			}
		}

		if (empty($found))
			$this->getResponse()->setHttpResponseCode(404);
	}

	public function markAction() {

		Mage::register('turpentine_nocache_flag', true, true);

		// read.gif (image de 1x1 pixel transparente)
		$data = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
		$this->getResponse()
			->setHttpResponseCode(200)
			->setHeader('Content-Type', 'image/gif', true)
			->setHeader('Content-Disposition', 'inline; filename="pixel.gif"', true)
			->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
			->setBody($data)
			->sendResponse();

		// marquage
		$email = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('uniqid', $this->getRequest()->getParam('key', 0))
			->setPageSize(1)
			->getFirstItem();

		if (!empty($email->getId()) && ($email->getData('status') != 'read')) {
			$email->setData('status', 'read');
			$email->setData('useragent', getenv('HTTP_USER_AGENT'));
			$email->setData('referer', getenv('HTTP_REFERER'));
			$email->save();
		}
	}
}