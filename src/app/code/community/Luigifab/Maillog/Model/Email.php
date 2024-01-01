<?php
/**
 * Created D/22/03/2015
 * Updated S/23/12/2023
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

class Luigifab_Maillog_Model_Email extends Mage_Core_Model_Abstract {

	protected $_eventPrefix = 'maillog_email';
	protected $_isResetPassword = false;

	public function _construct() {
		$this->_init('maillog/email');
	}


	public function getStoreId() {
		return (int) $this->getData('store_id');
	}

	public function getSubject() {
		return Mage::helper('maillog')->escapeEntities($this->getData('mail_subject'));
	}

	public function getEmailParts() {
		$parts = $this->getData('mail_parts');
		$parts = empty($parts) ? [] : @unserialize(gzdecode($parts), ['allowed_classes' => ['Zend_Mime_Part']]);
		return empty($parts) ? [] : $parts;
	}


	public function setMailHeader($heads) {

		$storeId = $this->getStoreId();

		// Return-Path
		$value = Mage::getStoreConfig('system/smtp/return_path_email', $storeId);
		if (!empty($value) && Mage::getStoreConfigFlag('system/smtp/set_return_path', $storeId))
			$heads .= "\r\n".'Return-Path: <'.$value.'>';

		// Reply-To
		if (!empty($value = Mage::getStoreConfig('system/smtp/reply_to_email', $storeId)))
			$heads .= "\r\n".'Reply-To: <'.$value.'>';

		return $this->setData('mail_header', $heads);
	}

	public function setMailSender($data, bool $decode = true) {
		return $this->setData('mail_sender', $decode ? iconv_mime_decode($data, 0, 'utf-8') : $data);
	}

	public function setMailRecipients($data, bool $decode = true) {

		// <xyz@maìl.com> = boom
		// avec ICONV_MIME_DECODE_CONTINUE_ON_ERROR ça donne <xyz@mal.com>, autant avoir false
		return $this->setData('mail_recipients', $decode ? @iconv_mime_decode($data, 0, 'utf-8') : $data);
	}

	public function setMailSubject($data, bool $decode = true) {
		return $this->setData('mail_subject', trim($decode ? iconv_mime_decode($data, 0, 'utf-8') : $data));
	}

	public function setMailContent($vars, $parts) {

		$storeId = $this->getStoreId();
		$this->setData('uniqid', substr(sha1(time().$this->getData('mail_recipients').$this->getData('mail_subject')), 0, 30)); // not mb_substr
		$this->setData('diqinu', substr(sha1(time().$this->getData('encoded_mail_recipients')), 0, 10)); // not mb_substr

		// $parts[0]->getContent() = $zend->body lorsque le mail n'a pas de pièce jointe
		if (empty($parts) || is_string($parts)) {
			$body  = $parts;
			$parts = [];
		}
		else {
			$body = trim(quoted_printable_decode($parts[0]->getContent()));
			array_shift($parts);
		}

		if (empty($body)) {
			$body = '';
		}
		else {
			if (mb_stripos($body, '<!--@vars') !== false)
				$body = mb_substr($body, mb_stripos($body, '-->') + 3);

			if (mb_stripos($body, '/customer/account/resetpassword/') !== false)
				$this->_isResetPassword = true;
			else if (mb_stripos($body, '/index/resetpassword/') !== false)
				$this->_isResetPassword = true;

			// recherche et remplace <!-- maillog / maillog --> par rien
			if ((mb_stripos($body, '<!-- maillog') !== false) && (mb_stripos($body, 'maillog -->') !== false)) {
				$body = preg_replace('#\s*<!-- maillog\s*#', ' ', $body);
				$body = preg_replace('#\s*maillog -->\s*#', ' ', $body);
			}

			// minifie le code HTML
			// recherche et remplace #online# #online#storeId# #readimg# par leur valeurs si c'est un mail au format HTML
			if ((mb_stripos($body, '</p>') !== false) || (mb_stripos($body, '</td>') !== false) || (mb_stripos($body, '</div>') !== false)) {

				$minify = Mage::getStoreConfig('maillog/general/minify', $storeId);
				if (($minify == 1) && class_exists('tidy', false) && extension_loaded('tidy'))
					$body = $this->cleanWithTidy($body);
				else if ($minify == 2)
					$body = preg_replace(["#(?:\n+[\t ]*)+#", "#[\t ]+#"], ["\n", ' '], $body);

				if (mb_stripos($body, '#online#') !== false)
					$body = preg_replace_callback('/#online#(\d{0,5})/', function ($matches) use ($storeId) {
						return $this->getEmbedUrl('index', empty($matches[1]) ? ['_store' => $storeId] : ['_store' => (int) $matches[1]]);
					}, $body);

				if (mb_stripos($body, '#readimg#') !== false)
					$body = str_replace('#readimg#', '<img src="'.$this->getEmbedUrl('mark').'" width="1" height="1" alt="">', $body);
			}

			// recherche et remplace #uniqid# par sa valeur
			if (mb_stripos($body, '#uniqid#') !== false)
				$body = str_replace('#uniqid#', $this->getData('uniqid'), $body);

			// recherche et remplace #mailid# par rien
			// permet de trier les emails par Type
			if (mb_stripos($body, '#mailid#') !== false) {
				$mailid = mb_substr($body, mb_stripos($body, '#mailid#'));
				$mailid = mb_substr($mailid, strlen('#mailid#')); // not mb_strlen
				$mailid = mb_substr($mailid, 0, mb_stripos($mailid, '#'));
				$body = str_replace('#mailid#'.$mailid.'#', '', $body);
				$this->setData('type', trim($mailid));
			}
			else if (!empty($vars['this']) && is_object($vars['this'])) {
				$tid = $vars['this']->getTemplateId();
				if (is_numeric($tid)) {
					$mailid = $tid;
				}
				else {
					$mailid = Mage::getConfig()->getNode('global/template/email/'.$vars['this']->getTemplateId().'/file');
					$mailid = pathinfo((string) $mailid, PATHINFO_FILENAME);
				}
				$this->setData('type', $mailid);
			}

			// auto login
			// recherche et remplace les liens si un client est trouvé
			if (!empty($storeId) && !empty($vars) && Mage::getStoreConfigFlag('maillog/login_whitout_password/enabled', $storeId)) {

				foreach ($vars as $name => $data) {
					if (is_object($data)) {
						// object
						if ($name == 'customer') {
							$customer = $data;
							break;
						}
						// method
						if (method_exists($data, 'getCustomer')) {
							$customer = $data->getCustomer();
							break;
						}
						if (method_exists($data, 'getCustomerId')) {
							$customer = Mage::getModel('customer/customer')->load($data->getCustomerId());
							break;
						}
						// dynamic
						$test = $data->getCustomer();
						if ($test instanceof Mage_Customer_Model_Customer) {
							$customer = $test;
							break;
						}
						$test = $data->getCustomerId();
						if (!empty($test)) {
							$customer = Mage::getModel('customer/customer')->load($test);
							break;
						}
					}
				}

				if (isset($customer)) {

					$helper  = Mage::helper('core');
					$baseUrl = Mage::app()->getStore($storeId)->getBaseUrl('web');

					$customerId = $customer->getId();
					if (empty($customer->getData('email')) || empty($customer->getData('password_hash')))
						$customer->load($customerId);

					$customerKey = substr(md5($customerId.$customer->getData('email').$customer->getData('password_hash')), 15); // not mb

					$body = preg_replace_callback('#(<a[^>]+)href="'.$baseUrl.'([^"]+)"#', function ($matches) use (
						$helper, $baseUrl, $storeId, $customerId, $customerKey
					) {
						return $matches[1].'href="'.$this->getEmbedUrl('elink', [
							'_store' => $storeId,
							'cid'    => $customerId,
							'sum'    => $customerKey,
							'lnk'    => $helper->urlEncode(str_replace([$baseUrl, '&amp;'], ['', '&'], $matches[2])),
						]).'" rel="noindex,nofollow"';
					}, $body);
				}
			}
		}

		$this->setData('mail_body', trim($body));
		$this->setData('mail_parts', empty($parts) ? null : gzencode(serialize($parts), 9));

		return $this;
	}

	protected function cleanWithTidy(string $html) {

		$tidy = new Tidy();
		$tidy->parseString($html, Mage::getModuleDir('etc', 'Luigifab_Maillog').'/tidy.conf', 'utf8');
		$tidy->cleanRepair();

		$html = str_replace("\"\n   ", '"', tidy_get_output($tidy)); // doctype

		return preg_replace([
			'#css">\s+/\*<!\[CDATA\[\*/#',
			'#/\*]]>\*/\s+</style#',
			'#" />#',
			'#>\s*</script>#',
			'#-->\s{2,}#',
			'#\s*<!--\[if([^]]+)]>\s*<#',
			'#>\s*<!\[endif]-->#',
			'#>\s*/?/?<!\[CDATA\[#',
			'#\s*/?/?]]></script>#',
			'#<br ?/?>\s+#',
			'#</code>\s</pre>#',
			'#\s+</textarea>#',
			'#\n?<!--[^\->]+-->#',
		], [
			'css">',
			'</style',
			'"/>',
			'></script>',
			"-->\n",
			"\n<!--[if$1]><",
			'><![endif]-->',
			'>//<![CDATA[',
			"\n//]]></script>",
			'<br>', // ici pas de /
			'</code></pre>',
			'</textarea>',
			'',
		], $html);
	}

	public function getColors() {

		$config = Mage::helper('maillog')->getConfigUnserialized('maillog/general/special_config');

		if (!empty($config)) {

			foreach ($config as $key => $value) {
				if (!empty($value) && ($key == $this->getData('type').'_back_color'))
					$bgColor = $value;
				if (!empty($value) && ($key == $this->getData('type').'_text_color'))
					$ttColor = $value;
			}

			if (empty($bgColor) && !empty($config['without_back_color']) && in_array($this->getData('type'), ['--', '', null]))
				$bgColor = $config['without_back_color'];
			if (empty($ttColor) && !empty($config['without_text_color']) && in_array($this->getData('type'), ['--', '', null]))
				$ttColor = $config['without_text_color'];

			if (empty($bgColor) && !empty($config['all_back_color']))
				$bgColor = $config['all_back_color'];
			if (empty($ttColor) && !empty($config['all_text_color']))
				$ttColor = $config['all_text_color'];
		}

		return [
			empty($bgColor) ? Mage::getStoreConfig('maillog/general/default_bgcolor') : $bgColor,
			empty($ttColor) ? Mage::getStoreConfig('maillog/general/default_ttcolor') : $ttColor,
		];
	}

	public function isResetPassword() {
		return $this->_isResetPassword;
	}

	public function sendNow(bool $sendAndSave = false) {

		$start = time();
		$storeId = $this->getStoreId();
		if (!$sendAndSave && empty($this->getId()))
			Mage::throwException('You must load an email before trying to send it.');

		$this->setData('status', 'sending');
		$this->setData('exception', null);
		$this->save();

		try {
			$heads = str_replace(["\r", "\n"], ['', "\r\n"], $this->getData('mail_header'));
			$heads .= "\r\n".'X-Maillog: '.$this->getData('uniqid');

			$dests = $this->getData('mail_recipients'); // for example: test <test@example.org>, copy <copy@example.org>
			$allow = (!empty($dests) && Mage::getStoreConfigFlag('maillog/general/send', $storeId)) ?
				Mage::helper('maillog')->canSend($dests) : false;

			$encoding = Mage::getStoreConfig('maillog/general/encoding', $storeId);
			if ($encoding != 'quoted-printable')
				$heads = str_replace('Content-Transfer-Encoding: quoted-printable', 'Content-Transfer-Encoding: '.$encoding, $heads);

			// contenu de l'email
			$subject = $this->getData('encoded_mail_subject');
			$content = $this->getData('mail_body');
			$parts   = $this->getEmailParts();

			if (!empty($parts)) {

				preg_match('#boundary="([^"]+)"#', $heads, $bound);

				$body  = 'This is a message in Mime Format.  If you see this, your mail reader does not support this format.'."\r\n\r\n";
				$body .= '--'.$bound[1]."\r\n";
				$body .= 'Content-Type: '.(((mb_stripos($content, '</p>') !== false) || (mb_stripos($content, '</td>') !== false) || (mb_stripos($content, '</div>') !== false)) ? 'text/html' : 'text/plain').'; charset=utf-8'."\r\n";
				$body .= 'Content-Transfer-Encoding: '.$encoding."\r\n";
				$body .= 'Content-Disposition: inline'."\r\n\r\n";

				if ($encoding == 'base64')
					$body .= rtrim(chunk_split(base64_encode($content)));
				else //if ($encoding == 'quoted-printable')
					$body .= quoted_printable_encode($content);

				foreach ($parts as $part) {
					$body .= "\r\n\r\n";
					$body .= '--'.$bound[1]."\r\n";
					$body .= 'Content-Type: '.$part->type."\r\n";
					$body .= 'Content-Transfer-Encoding: '.$part->encoding."\r\n";
					$body .= 'Content-Disposition: '.$part->disposition.'; filename="'.$part->filename.'"'."\r\n\r\n";
					$body .= rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
				}

				$body .= "\r\n\r\n".'--'.$bound[1]."\r\n";
			}
			else if ($encoding == 'base64') {
				$body = rtrim(chunk_split(base64_encode($content)));
			}
			else { // quoted-printable
				$body = quoted_printable_encode($content);
			}

			$this->setData('size',
				mb_strlen($this->getData('encoded_mail_recipients')) + // utilise les destinataires originaux
				mb_strlen($subject) +                                  // utilise le sujet original
				mb_strlen($body) +                                     // utilise la nouvelle version encodée du contenu
				mb_strlen($heads) +                                    // utilise les entêtes originaux (où presque)
				mb_strlen((string) $this->getData('mail_parameters'))  // utilise les paramètres originaux
			);

			// action
			if (empty($subject) || ($allow !== true) || empty($content) || Mage::getSingleton('maillog/source_bounce')->isBounce($dests)) {
				$this->setData('status', $allow ? 'bounce' : 'notsent');
				$this->setData('duration', time() - $start);
				if (is_string($allow))
					$this->setData('exception', $allow);
			}
			else {
				// SMTP personnalisé avec CURL
				if (Mage::getStoreConfigFlag('maillog/general/smtp_enabled', $storeId)) {

					$from = $this->getData('mail_sender');
					$from = trim(empty($pos = mb_strpos($from, '<')) ? $from : mb_substr($from, $pos + 1, -1));

					// addTo addCc addBcc
					// $dests » mail_recipients, addTo
					// $heads » mail_header, addCc, addBcc
					// @see https://stackoverflow.com/a/2750359/2980105
					//
					// To: to1 <to1@example.org>,
					//  to2 <to2@example.org>,
					//  to3@example.org
					// Cc: cc1 <cc1@example.org>,
					//  cc2 <cc2@example.org>,
					//  cc2@example.org
					// Bcc: bcc1 <bcc2@example.org>,
					//  bcc2@example.org
					$addrs  = [];
					$recpts = [];
					$next   = '';
					foreach (array_merge(explode("\r\n", str_replace(',', ",\r\n ", 'To: '.$dests)), explode("\r\n", $heads)) as $head) {
						if (($next == 'To') || (mb_strpos($head, 'To:') === 0)) {
							$info = trim(empty($next) ? mb_substr($head, 3) : $head); // To: = 3
							$next = (mb_substr($head, -1) == ',') ? 'To' : '';
							$info = trim($info, ',');
							$addr = trim(empty($pos = mb_strpos($info, '<')) ? $info : mb_substr($info, $pos + 1, -1));
							$addrs[]  = empty($pos) ? $addr : '=?utf-8?B?'.base64_encode(trim(mb_substr($info, 0, $pos))).'?= <'.$addr.'>';
							$recpts[] = $addr;
						}
						else if (($next == 'Cc') || (mb_strpos($head, 'Cc:') === 0)) {
							$info = trim(empty($next) ? mb_substr($head, 3) : $head); // Cc: = 3
							$next = (mb_substr($info, -1) == ',') ? 'Cc' : '';
							$info = trim($info, ',');
							$addr = trim(empty($pos = mb_strpos($info, '<')) ? $info : mb_substr($info, $pos + 1, -1));
							$recpts[] = $addr;
						}
						else if (($next == 'Bcc') || (mb_strpos($head, 'Bcc:') === 0)) {
							$info = trim(empty($next) ? mb_substr($head, 4) : $head); // Bcc: = 4
							$next = (mb_substr($info, -1) == ',') ? 'Bcc' : '';
							$info = trim($info, ',');
							$addr = trim(empty($pos = mb_strpos($info, '<')) ? $info : mb_substr($info, $pos + 1, -1));
							$recpts[] = $addr;
							$heads = str_replace($head."\r\n", '', $heads);
						}
					}

					// all recipients (to and cc) except bcc
					$heads = 'To: '.implode(",\r\n ", $addrs)."\r\n".$heads;
					//echo '<pre>',htmlspecialchars($heads); exit;

					// 550, 5.7.1, delivery not authorized: message missing a valid messageId header are not accepted (gmail)
					// @see https://stackoverflow.com/q/14483861
					if (!empty($domain = Mage::getStoreConfig('maillog/general/smtp_domain', $storeId)))
						$heads .= "\r\n".'Message-ID: <'.time().'-'.$this->getData('uniqid').'@'.$domain.'>';

					// une trouvaille fantastique
					// @see https://gist.github.com/hdogan/8649cd9c25c75d0ab27e140d5eef5ce2
					$fp = fopen('php://memory', 'rb+');
					fwrite($fp, $heads."\r\n".'Subject: '.$subject."\r\n\r\n".$body);
					//rewind($fp); $stream = stream_get_contents($fp);
					rewind($fp);

					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, Mage::getStoreConfig('maillog/general/smtp_url', $storeId));
					if (!empty($user = Mage::getStoreConfig('maillog/general/smtp_username', $storeId)))
						curl_setopt($ch, CURLOPT_USERNAME, $user);
					if (!empty($pass = Mage::getStoreConfig('maillog/general/smtp_password', $storeId)))
						curl_setopt($ch, CURLOPT_PASSWORD, Mage::helper('core')->decrypt($pass));
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
					curl_setopt($ch, CURLOPT_TIMEOUT, 50);
					curl_setopt($ch, CURLOPT_MAIL_FROM, $from);   // sender - setFrom (email only)
					curl_setopt($ch, CURLOPT_MAIL_RCPT, $recpts); // all recipients - addTo addCc addBcc (email only)
					curl_setopt($ch, CURLOPT_UPLOAD, true);
					curl_setopt($ch, CURLOPT_INFILE, $fp);
					curl_setopt($ch, CURLOPT_READFUNCTION, static function ($ch, $fp, $length) { return fread($fp, $length); });
					//curl_setopt($ch, CURLOPT_VERBOSE, true);
					//curl_setopt($ch, CURLOPT_STDERR, $log = fopen('php://temp', 'w+'));

					$result = curl_exec($ch);
					$result = (($result === false) || (curl_errno($ch) !== 0)) ?
						trim('CURL_ERROR '.curl_errno($ch).' '.curl_error($ch)) : (empty($result) ? true : $result);
					curl_close($ch);
					fclose($fp);

					//rewind($log);
					//Mage::log(stream_get_contents($log));
					//Mage::log($stream);
					//fclose($log);
				}
				// PHP mail
				else {
					$result = mail(
						$this->getData('encoded_mail_recipients'), // version originale
						$subject,                                  // version originale
						$body,
						$heads,                                    // version originale (où presque)
						(string) $this->getData('mail_parameters') // version originale
					);
				}

				if (in_array($this->getData('sent_at'), ['', '0000-00-00 00:00:00', null]))
					$this->setData('sent_at', date('Y-m-d H:i:s'));
				if (!is_bool($result))
					Mage::throwException(sprintf('Error for email %d: %s', $this->getId(), $result));

				$this->setData('status', ($result === true) ? 'sent' : 'error');
			}
		}
		catch (Throwable $t) {
			Mage::logException($t);
			$this->setData('status', 'error');
			$this->setData('exception', $this->formatException($t));
		}

		$this->setData('duration', time() - $start);
		$this->save();

		return $this;
	}

	public function formatException(Throwable $t) {

		// $t->__toString()...
		// same as Mage::printException
		return get_class($t).': '.$t->getMessage()."\n".
			str_replace(
				str_contains(__FILE__, 'vendor/luigifab') ? dirname(BP) : BP,
				'',
				$t->getTraceAsString()."\n".'  thrown in '.$t->getFile().' on line '.$t->getLine()
			);
	}


	public function toHtml(bool $noMark = false) {

		if (empty($this->getId()))
			Mage::throwException('You must load an email before trying to display it.');

		$body = $this->getData('mail_body');
		$head = null;

		if (!empty($body)) {

			if (($pos = mb_stripos($body, '<head')) !== false) {
				$head = mb_substr($body, $pos);
				$head = mb_substr($head, mb_strpos($head, '>') + 1);
				$head = mb_substr($head, 0, mb_stripos($head, '</head>'));
				$head = preg_replace('#<title>.*</title>#s', '', $head);
			}

			if (($pos = mb_stripos($body, '<body')) !== false) {
				$body = mb_substr($body, $pos);
				$body = mb_substr($body, mb_strpos($body, '>') + 1);
				$body = mb_substr($body, 0, mb_stripos($body, '</body>'));
			}
			else if ((mb_stripos($body, '</p>') === false) && (mb_stripos($body, '</td>') === false) && (mb_stripos($body, '</div>') === false)) {
				$body = '<pre>'.$body.'</pre>';
			}

			// suppression de l'éventuelle image de marquage
			// lorsque l'administrateur visualise l'email en ligne
			if ($noMark && (mb_stripos($body, 'maillog/view/mark') !== false))
				$body = preg_replace('#[\-\s]*<img[^>]+maillog/view/mark[^>]+>[\-\s]*#', ' ', $body);

			// suppression de l'éventuel lien voir la version en ligne
			// lorsque le client (et non l'administrateur) visualise son email en ligne
			if (!$noMark && (mb_stripos($body, 'maillog/view/index') !== false))
				$body = preg_replace('#[\-\s]*<a[^>]+maillog/view/index.+</a>[\-\s]*#', ' ', $body);
		}

		return Mage::getBlockSingleton('core/template')
			->setTemplate('luigifab/maillog/email.phtml')
			->setData('email', $this)
			->setData('mail_head', $head)
			->setData('mail_body', $body)
			->toHtml();
	}

	public function getEmbedUrl(string $action, array $params = []) {

		$params = array_merge(['_secure' => false, 'key' => $this->getData('uniqid'), 'yek' => $this->getData('diqinu')], $params);
		if (empty($params['_store']) || Mage::app()->getStore()->isAdmin())
			$params['_store'] = Mage::app()->getDefaultStoreView()->getId();

		$url = preg_replace('#\?SID=.+$#', '', Mage::app()->getStore($params['_store'])->getUrl('maillog/view/'.$action, $params));
		if (Mage::getStoreConfigFlag('web/seo/use_rewrites', $params['_store']))
			$url = preg_replace('#/[^/]+\.php\d*/#', '/', $url);
		else
			$url = preg_replace('#/[^/]+\.php(\d*)/#', '/index.php$1/', $url);

		return $params['_secure'] ? str_replace('http:', 'https:', $url) : $url;
	}
}