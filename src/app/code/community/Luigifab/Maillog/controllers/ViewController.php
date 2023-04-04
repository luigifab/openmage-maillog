<?php
/**
 * Created M/24/03/2015
 * Updated V/31/03/2023
 *
 * Copyright 2015-2023 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

class Luigifab_Maillog_ViewController extends Mage_Core_Controller_Front_Action {

	public function preDispatch() {
		Mage::register('turpentine_nocache_flag', true, true);
		parent::preDispatch();
	}

	public function indexAction() {

		$email = Mage::getModel('maillog/email')->load($this->getRequest()->getParam('key', 0), 'uniqid');

		if (empty($email->getId())) {
			$this->getResponse()->setHttpResponseCode(404);
		}
		else {
			$this->getResponse()
				->setHttpResponseCode(200)
				->setHeader('Content-Type', 'text/html; charset=utf-8', true)
				->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
				->setBody($email->toHtml($this->getRequest()->getParam('nomark') == '1'));
		}
	}

	public function downloadAction() {

		$email = Mage::getModel('maillog/email')->load($this->getRequest()->getParam('key', 0), 'uniqid');
		$nb = (int) $this->getRequest()->getParam('part', 0);

		if (!empty($email->getId()) && !empty($email->getData('mail_parts'))) {

			$parts = $email->getEmailParts();
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
		$email = Mage::getModel('maillog/email')->load($this->getRequest()->getParam('key', 0), 'uniqid');
		$this->markEmail($email);
	}

	public function elinkAction() {

		$email = Mage::getModel('maillog/email')->load($this->getRequest()->getParam('key', 0), 'uniqid');
		$link  = $this->getRequest()->getParam('lnk', 0);

		if (empty($link)) {
			$url = Mage::getBaseUrl();
			$this->getResponse()
				->setHttpResponseCode(302)
				->setHeader('Location', $url, true)
				->sendResponse();
		}
		else if (empty($email->getId())) {
			// redirige même si l'email n'existe plus
			// Warning: Header may not contain more than a single header, new line detected Zend/Controller/Response/Abstract.php on line 363
			$url = Mage::getBaseUrl('web').Mage::helper('core')->urlDecode($link);
			Zend_Uri::setConfig(['allow_unwise' => true]);
			if (!Zend_Uri::check($url)) {
				Mage::log(sprintf('Invalid decoded link (%s %s) for email #%d (%s)', $link, $url, $email->getId(), getenv('HTTP_USER_AGENT')), Zend_Log::WARN, 'maillog.log');
				$url = Mage::getBaseUrl();
			}

			$this->getResponse()
				->setHttpResponseCode(302)
				->setHeader('Location', $url, true)
				->sendResponse();
		}
		else {
			$cid = $this->getRequest()->getParam('cid', 0);
			$key = $this->getRequest()->getParam('sum', 0);

			// auto login
			// l'email existe, le client existe, le hash est toujours bon, le hash vient bien de l'email
			if (!empty($cid) && !empty($key) && Mage::getStoreConfigFlag('maillog/login_whitout_password/enabled')) {

				$session = Mage::getSingleton('customer/session');
				if ($session->getCustomerId() != $cid)
					$session->logout();

				$customer = Mage::getModel('customer/customer')->load($cid);
				if (!empty($customer->getId())) {
					$sum = substr(md5($customer->getId().$customer->getData('email').$customer->getData('password_hash')), 15);
					if (($key == $sum) && (mb_stripos($email->getData('mail_body'), $cid.'/sum/'.$sum) !== false)) {
						$session->setCustomerAsLoggedIn($customer);
						$session->setData('maillog_auto_loginin', 1);
					}
				}
			}

			// redirection
			// Warning: Header may not contain more than a single header, new line detected Zend/Controller/Response/Abstract.php on line 363
			$url = Mage::getBaseUrl('web').Mage::helper('core')->urlDecode($link);
			Zend_Uri::setConfig(['allow_unwise' => true]);
			if (!Zend_Uri::check($url)) {
				Mage::log(sprintf('Invalid decoded link (%s %s) for email #%d (%s)', $link, $url, $email->getId(), getenv('HTTP_USER_AGENT')), Zend_Log::WARN, 'maillog.log');
				$url = Mage::getBaseUrl();
			}

			$this->getResponse()
				->setHttpResponseCode(302)
				->setHeader('Location', $url, true)
				->sendResponse();

			// marquage
			$this->markEmail($email);
		}
	}

	protected function markEmail($email) {

		if (!empty($email->getData('sent_at')) && ($email->getData('status') != 'read')) {

			// ignore par exemple : Sendinblue/1.0 (redirection-images 1.81.56; +https://sendinblue.com)
			$userAgent = getenv('HTTP_USER_AGENT');
			if (empty($userAgent) || ((stripos($userAgent, 'Sendinblue') === false) && (stripos($userAgent, 'redirection') === false))) {

				$email->setData('status', 'read');
				$email->setData('useragent', $userAgent);
				$email->setData('referer', getenv('HTTP_REFERER'));
				$email->save();
			}
		}
	}
}