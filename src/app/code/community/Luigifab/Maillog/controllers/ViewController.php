<?php
/**
 * Created M/24/03/2015
 * Updated M/27/06/2023
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

		if (!empty($email->getId()) && !empty($email->getData('mail_parts'))) {

			$nb = (int) $this->getRequest()->getParam('part', 0);
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
		if (!empty($email->getId()))
			$this->markEmail($email);
	}

	public function elinkAction() {

		$request = $this->getRequest();
		$email   = Mage::getModel('maillog/email')->load($request->getParam('key', 0), 'uniqid');
		$link    = $request->getParam('lnk', 0);

		if (empty($link)) {
			$url = Mage::getBaseUrl();
			$this->getResponse()
				->setHttpResponseCode(302)
				->setHeader('Location', $url, true);
		}
		else if (empty($email->getId())) {

			// redirige même si l'email n'existe plus
			// Warning: Header may not contain more than a single header, new line detected Zend/Controller/Response/Abstract.php on line 363
			$url = Mage::getBaseUrl('web').Mage::helper('core')->urlDecode($link);
			Zend_Uri::setConfig(['allow_unwise' => true]);
			if (!Zend_Uri::check($url)) {
				//Mage::log(sprintf('Invalid decoded link (%s %s) for email #%d (%s)', $link, $url, $email->getId(), getenv('HTTP_USER_AGENT')), Zend_Log::WARN, 'maillog.log');
				$url = Mage::getBaseUrl();
			}

			$this->getResponse()
				->setHttpResponseCode(302)
				->setHeader('Location', $url, true);
		}
		else {
			// auto login
			// l'email existe, le client existe, le hash est toujours bon, le hash vient bien de l'email
			$cid = (int) $request->getParam('cid', 0);
			$key = $request->getParam('sum', 0);
			if (!empty($cid) && !empty($key) && Mage::getStoreConfigFlag('maillog/login_whitout_password/enabled')) {

				$session = Mage::getSingleton('customer/session');
				$scid    = (int) $session->getCustomerId();
				if ($scid != $cid)
					$session->logout();

				$customer = Mage::getModel('customer/customer')->load($cid);
				if (!$session->isLoggedIn() && !empty($customer->getId())) {
					$sum = substr(md5($customer->getId().$customer->getData('email').$customer->getData('password_hash')), 15); // not mb_substr
					if (($key == $sum) && (mb_stripos($email->getData('mail_body'), $cid.'/sum/'.$sum) !== false)) {
						$session->setCustomerAsLoggedIn($customer);
						$session->setData('maillog_auto_login', 1);
					}
				}
			}

			// redirection
			// Warning: Header may not contain more than a single header, new line detected Zend/Controller/Response/Abstract.php on line 363
			$url = Mage::getBaseUrl('web').Mage::helper('core')->urlDecode($link);
			Zend_Uri::setConfig(['allow_unwise' => true]);
			if (!Zend_Uri::check($url)) {
				//Mage::log(sprintf('Invalid decoded link (%s %s) for email #%d (%s)', $link, $url, $email->getId(), getenv('HTTP_USER_AGENT')), Zend_Log::WARN, 'maillog.log');
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

		$isNotRead = $email->getData('status') != 'read';
		$isNotUser = empty($email->getData('useragent'));

		// mark as read with or without userAgent
		// can mark again if read without userAgent
		if (($isNotRead || $isNotUser) && !empty($email->getData('sent_at'))) {

			// Sendinblue/1.0 (redirection-images 1.81.56; +https://sendinblue.com)
			// Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm
			// Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)
			// Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)
			// YahooMailProxy; https://help.yahoo.com/kb/yahoo-mail-proxy-SLN28749.html
			// Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via WP.pl WPImageProxy)
			// Mozilla/5.0 SeznamEmailProxy/1.0.5
			// @todo https://github.com/fabiomb/is_bot ?
			// @todo https://github.com/OpenMage/magento-lts/pull/3238/files ?
			$userAgent = getenv('HTTP_USER_AGENT');
			if ($isNotUser && !empty($userAgent) &&
				($userAgent != 'Mozilla/5.0') &&
				(stripos($userAgent, 'sendinblue.com') === false) &&
				(stripos($userAgent, 'bing.com/bingbot') === false) &&
				(stripos($userAgent, 'yandex.com/bots') === false) &&
				(stripos($userAgent, 'GoogleImageProxy') === false) &&
				(stripos($userAgent, 'YahooMailProxy') === false) &&
				(stripos($userAgent, 'WPImageProxy') === false) &&
				(stripos($userAgent, 'SeznamEmailProxy') === false)
			) {
				if ($isNotRead)
					$email->setData('status', 'read');
				$email->setData('useragent', $userAgent);
				$email->setData('referer', getenv('HTTP_REFERER'));
				$email->save();
			}
			else if ($isNotRead) {
				$email->setData('status', 'read');
				$email->save();
			}
		}
	}
}