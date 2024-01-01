<?php
/**
 * Created M/24/03/2015
 * Updated D/24/12/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

		Mage::app()->getStore()->setConfig('dev/debug/template_hints', 0);
		Mage::app()->getStore()->setConfig('dev/debug/template_hints_blocks', 0);

		Mage::register('turpentine_nocache_flag', true, true);
		$this->getResponse()
			->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
			->setHeader('X-Robots-Tag', 'noindex, nofollow', true);

		$valid = false;
		$isBot = true;
		$userAgent = getenv('HTTP_USER_AGENT');

		if (!empty($userAgent) && (mb_stripos($userAgent, 'bot') === false)) {
			$browser = Mage::getSingleton('maillog/useragentparser')->parse($userAgent);
			if (!empty($browser['browser']) && (mb_stripos($browser['browser'], 'bot') === false)) {
				$isBot = false;
				// @see Mage_Log_Model_Visitor
				$ignoreAgents = Mage::getConfig()->getNode('global/ignore_user_agents');
				if (!empty($ignoreAgents)) {
					$ignoreAgents = $ignoreAgents->asArray();
					foreach ($ignoreAgents as $ignoreAgent) {
						if (mb_stripos($userAgent, $ignoreAgent) !== false) {
							$isBot = true;
							break;
						}
					}
				}
			}
		}

		// load and register email
		if (!$isBot) {
			$email = Mage::getModel('maillog/email')->load($this->getRequest()->getParam('key', 0), 'uniqid');
			$check = $email->getData('diqinu');
			if (!empty($check) && !empty($email->getId()) && ($this->getRequest()->getParam('yek', 0) == $check)) {
				$valid = true;
				Mage::register('current_email', $email);
			}
		}

		// no dispatch
		if (!$valid) {
			$this->setFlag('', Mage_Core_Controller_Front_Action::FLAG_NO_DISPATCH, true);
			if ($isBot)
				$this->getResponse()->setHttpResponseCode(404);
			else
				$this->getResponse()->setHttpResponseCode(302)->setHeader('Location', Mage::getBaseUrl(), true);
		}

		return parent::preDispatch();
	}

	public function indexAction() {

		$email  = Mage::registry('current_email');
		$noMark = $this->getRequest()->getParam('nomark') == '1';

		$this->getResponse()
			->setHttpResponseCode(200)
			->setHeader('Content-Type', 'text/html; charset=utf-8', true)
			->setBody($email->toHtml($noMark));
	}

	public function downloadAction() {

		$email = Mage::registry('current_email');
		$parts = $email->getEmailParts();
		$num   = (int) $this->getRequest()->getParam('part', 0);

		if (empty($parts[$num])) {
			$this->getResponse()->setHttpResponseCode(404);
		}
		else {
			$part = $parts[$num];
			$data = base64_decode(rtrim(chunk_split(str_replace("\n", '', $part->getContent()))));
			$disp = 'attachment; filename="'.$part->filename.'"';
			$type = $part->type;

			if ($type == 'application/x-gzip') {
				$this->getResponse()
					->setHttpResponseCode(200)
					->setHeader('Content-Type', $type, true)
					->setHeader('Content-Disposition', $disp, true)
					->setBody($data);
			}
			else {
				// display pdf in browser
				if (($type == 'application/octet-stream') && str_ends_with($part->filename, '.pdf'))
					$type = 'application/pdf';
				if ($type == 'application/pdf')
					$disp = 'inline; filename="'.$part->filename.'"';

				// compress with gzip
				$this->getResponse()
					->setHttpResponseCode(200)
					->setHeader('Content-Type', $type, true)
					->setHeader('Content-Disposition', $disp, true)
					->setHeader('Content-Encoding', 'gzip', true)
					->setBody(gzencode($data, 9));
			}
		}
	}

	public function markAction() {

		// read.gif (image de 1x1 pixel transparente)
		$data = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
		$this->getResponse()
			->setHttpResponseCode(200)
			->setHeader('Content-Type', 'image/gif', true)
			->setHeader('Content-Disposition', 'inline; filename="maillog.gif"', true)
			->setBody($data)
			->sendResponse();

		$this->mark();
	}

	public function elinkAction() {

		$email = Mage::registry('current_email');

		// auto login
		// l'email existe (key=uniqid, yek=diqinu), le client existe, le hash est toujours bon (ash=sum), le hash vient bien de l'email
		$cid  = (int) $this->getRequest()->getParam('cid', 0);
		$hash = $this->getRequest()->getParam('sum', 0);
		$date = strtotime($email->getData('created_at'));
		if (!empty($cid) && !empty($hash) && Mage::getStoreConfigFlag('maillog/login_whitout_password/enabled') && (($date + 3600) > time())) {

			$session = Mage::getSingleton('customer/session');
			if ((int) $session->getCustomerId() != $cid)
				$session->logout();

			$customer = Mage::getModel('customer/customer')->load($cid);
			if (!$session->isLoggedIn() && !empty($customer->getId())) {
				$sum = substr(md5($customer->getId().$customer->getData('email').$customer->getData('password_hash')), 15); // not mb_substr
				if (($hash == $sum) && (mb_stripos($email->getData('mail_body'), $cid.'/sum/'.$sum) !== false)) {
					$session->setCustomerAsLoggedIn($customer);
					$session->setData('maillog_auto_login', 1);
				}
			}
		}

		// trouve le lien
		// Warning: Header may not contain more than a single header, new line detected Zend/Controller/Response/Abstract.php on line 363
		$lnk = $this->getRequest()->getParam('lnk', 0);
		$url = Mage::getBaseUrl('web').Mage::helper('core')->urlDecode($lnk);
		Zend_Uri::setConfig(['allow_unwise' => true]);
		if (!Zend_Uri::check($url)) {
			//Mage::log(sprintf('Invalid decoded link (%s %s) for email #%d (%s)', $lnk, $url, $email->getId(), getenv('HTTP_USER_AGENT')), Zend_Log::WARN, 'maillog.log');
			$url = Mage::getBaseUrl('web');
		}

		$this->getResponse()
			->setHttpResponseCode(302)
			->setHeader('Location', $url, true)
			->sendResponse();

		$this->mark();
	}

	protected function mark() {

		$email = Mage::registry('current_email');
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
			$userAgent = getenv('HTTP_USER_AGENT');
			if ($isNotUser && !empty($userAgent) && ($userAgent != 'Mozilla/5.0') &&
				(mb_stripos($userAgent, 'sendinblue.com') === false) &&
				(mb_stripos($userAgent, 'bing.com/bingbot') === false) &&
				(mb_stripos($userAgent, 'yandex.com/bots') === false) &&
				(mb_stripos($userAgent, 'GoogleImageProxy') === false) &&
				(mb_stripos($userAgent, 'YahooMailProxy') === false) &&
				(mb_stripos($userAgent, 'WPImageProxy') === false) &&
				(mb_stripos($userAgent, 'SeznamEmailProxy') === false)
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