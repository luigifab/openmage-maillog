<?php
/**
 * Created D/22/03/2015
 * Updated L/30/03/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
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

	public function _construct() {
		$this->_init('maillog/email');
	}

	protected function _beforeLoad() {
		$this->getResource()->getReadConnection()->query('SET NAMES utf8mb4;');
		return $this;
	}

	protected function _afterLoad() {
		$this->getResource()->getReadConnection()->query('SET NAMES '.$this->getResource()->_getCharacterSet().';');
		return $this;
	}

	public function getDefaultBgColor() {
		return '#F6F6F6';
	}

	public function getDefaultTxtColor() {
		return '#000';
	}


	public function getSubject() {
		return Mage::helper('maillog')->escapeEntities($this->getData('mail_subject'));
	}

	public function setMailSender($data) {
		$this->setData('mail_sender', iconv_mime_decode($data, 0, 'utf-8'));
	}

	public function setMailRecipients($data) {
		$this->setData('mail_recipients', iconv_mime_decode($data, 0, 'utf-8'));
	}

	public function setMailSubject($data) {
		$this->setData('mail_subject', trim(iconv_mime_decode($data, 0, 'utf-8')));
	}

	public function setMailContent($parts) {

		$this->setData('uniqid', mb_substr(sha1(time().$this->getData('mail_recipients').$this->getData('mail_subject')), 0, 30));

		// récupération des données du mail
		// $parts[0]->getContent() = $zend->body si le mail n'a pas de pièce jointe
		$body = trim(quoted_printable_decode($parts[0]->getContent()));
		array_shift($parts);

		if (mb_stripos($body, '<!--@vars') !== false)
			$body = mb_substr($body, mb_stripos($body, '-->') + 3);

		// recherche et remplace <!-- maillog / maillog --> par rien
		if ((mb_stripos($body, '<!-- maillog') !== false) && (mb_stripos($body, 'maillog -->') !== false)) {
			$body = preg_replace('#\s*<!-- maillog\s*#', ' ', $body);
			$body = preg_replace('#\s*maillog -->\s*#',   ' ', $body);
		}

		// minifie le code HTML en fonction de la configuration
		// remplace et remplace #online# #online#storeId# #readimg# par leur valeurs uniquement si c'est un mail au fomat HTML
		if ((mb_stripos($body, '</p>') !== false) || (mb_stripos($body, '</td>') !== false) || (mb_stripos($body, '</div>') !== false)) {

			if (Mage::getStoreConfigFlag('maillog/general/minify') && extension_loaded('tidy') && class_exists('tidy', false))
				$body = $this->cleanWithTidy($body);

			if (mb_stripos($body, '#online#') !== false)
				$body = preg_replace_callback('/#online#(\d{0,5})/', function ($matches) {
					return $this->getEmbedUrl('index', empty($matches[1]) ? [] : ['_store' => (int) $matches[1]]);
				}, $body);

			if (mb_stripos($body, '#readimg#') !== false)
				$body = str_replace('#readimg#', '<img src="'.$this->getEmbedUrl('mark').'" width="1" height="1" alt="">', $body);
		}

		// recherche et remplace #uniqid# par sa valeur
		if (mb_stripos($body, '#uniqid#') !== false)
			$body = str_replace('#uniqid#', $this->getData('uniqid'), $body);

		// recherche et remplace #mailid# par rien
		if (mb_stripos($body, '#mailid#') !== false) {
			$mailid = mb_substr($body, mb_stripos($body, '#mailid#'));
			$mailid = mb_substr($mailid, mb_strlen('#mailid#'));
			$mailid = mb_substr($mailid, 0, mb_stripos($mailid, '#'));
			$body = str_replace('#mailid#'.$mailid.'#', '', $body);
			$this->setData('type', trim($mailid));
		}

		$this->setData('mail_body', trim($body));
		$this->setData('mail_parts', empty($parts) ? null : gzencode(serialize($parts), 9));
	}

	private function cleanWithTidy($html) {

		$tidy = new Tidy();
		$tidy->parseString($html, Mage::getModuleDir('etc', 'Luigifab_Maillog').'/tidy.conf', 'utf8');
		$tidy->cleanRepair();

		$html = $tidy;
		$html = str_replace("\"\n   ", '"', $html); // doctype


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
			'#\n?<!--[^\-\->]+-->#'
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
			''
		], $html);
	}


	// action
	public function sendNow() {

		$now = time();
		if (empty($this->getId()))
			Mage::throwException('You must load an email before trying to send it.');

		try {
			$this->setData('status', 'sending');
			$this->save();

			$heads = $this->getData('mail_header');
			$email = $this->getData('mail_recipients');
			$allow = Mage::getStoreConfigFlag('maillog/general/send') ? Mage::helper('maillog')->canSend($email) : false;

			// modifie les entêtes
			$heads .= "\r\n".'X-Maillog: '.$this->getData('uniqid');
			$encoding = Mage::getStoreConfig('maillog/general/encoding');
			if ($encoding != 'quoted-printable')
				$heads = str_replace('Content-Transfer-Encoding: quoted-printable', 'Content-Transfer-Encoding: '.$encoding, $heads);

			//$subject = $this->getData('encoded_mail_subject');
			//if ((mb_substr($subject, 0, 2) != '=?') || (mb_substr($subject, 0, 2) != '?=')) {
			//	//if ($encoding == 'quoted-printable')
			//		//$subject = '=?utf-8?Q?'.quoted_printable_encode($subject).'?=';
			//	$subject = '=?utf-8?B?'.base64_encode($subject).'?=';
			//}

			// préparation des données
			if (empty($this->getData('mail_parts'))) {
				if ($encoding == 'quoted-printable')
					$body = quoted_printable_encode($this->getData('mail_body'));
				else if ($encoding == 'base64')
					$body = rtrim(chunk_split(base64_encode($this->getData('mail_body'))));
			}
			else {
				preg_match('#boundary="([^"]+)"#', $heads, $bound);
				$bound = $bound[1];

				$body = $this->getData('mail_body');
				$type = ((mb_stripos($body, '</p>') !== false) || (mb_stripos($body, '</td>') !== false) || (mb_stripos($body, '</div>') !== false)) ? 'text/html' : 'text/plain';

				$body  = 'This is a message in Mime Format.  If you see this, your mail reader does not support this format.'."\r\n\r\n";
				$body .= '--'.$bound."\r\n";
				$body .= 'Content-Type: '.$type.'; charset=utf-8'."\r\n";
				$body .= 'Content-Transfer-Encoding: '.$encoding."\r\n";
				$body .= 'Content-Disposition: inline'."\r\n\r\n";

				if ($encoding == 'quoted-printable')
					$body .= quoted_printable_encode($this->getData('mail_body'));
				else if ($encoding == 'base64')
					$body .= rtrim(chunk_split(base64_encode($this->getData('mail_body'))));

				$parts = @unserialize(gzdecode($this->getData('mail_parts')));
				foreach ($parts as $part) {
					$body .= "\r\n\r\n";
					$body .= '--'.$bound."\r\n";
					$body .= 'Content-Type: '.$part->type."\r\n";
					$body .= 'Content-Transfer-Encoding: '.$part->encoding."\r\n";
					$body .= 'Content-Disposition: '.$part->disposition.'; filename="'.$part->filename.'"'."\r\n\r\n";
					$body .= rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
				}

				$body .= "\r\n\r\n".'--'.$bound."\r\n";
			}

			$this->setData('size',
				mb_strlen($this->getData('encoded_mail_recipients')) + // utilise les destinataires originaux
				mb_strlen($this->getData('encoded_mail_subject')) +    // utilise le sujet original
				mb_strlen($body) +                                     // utilise la nouvelle version encodée du contenu
				mb_strlen($heads) +                                    // utilise les entêtes originaux (où presque)
				mb_strlen($this->getData('mail_parameters'))           // utilise les paramètres originaux
			);

			// action (sauf si l'email est vide)
			$empty = empty($this->getData('mail_body')) || empty($this->getData('encoded_mail_subject'));
			if ($empty || ($allow !== true) || Mage::getSingleton('maillog/source_bounce')->isBounce($email)) {
				$this->setData('status', $allow ? 'bounce' : 'notsent');
				$this->setData('duration', time() - $now);
				$this->save();
			}
			else {
				$result = mail(
					$this->getData('encoded_mail_recipients'), // version originale
					$this->getData('encoded_mail_subject'),    // version originale
					$body,
					$heads,                                    // version originale (où presque)
					$this->getData('mail_parameters')          // version originale
				);

				if (in_array($this->getData('sent_at'), ['', '0000-00-00 00:00:00', null]))
					$this->setData('sent_at', date('Y-m-d H:i:s'));

				$this->setData('status', ($result === true) ? 'sent' : 'error');
				$this->setData('duration', time() - $now);
				$this->save();
			}
		}
		catch (Exception $e) {
			Mage::logException($e);
		}
	}


	// prépare l'email pour un affichage dans une page web
	// supprime l'image de marquage lorsque demandé (très utile dans le back-office)
	public function toHtml($noMark) {

		if (empty($this->getId()))
			Mage::throwException('You must load an email before trying to display it.');

		$help = Mage::helper('maillog');
		$date = Mage::getSingleton('core/locale');

		$body   = $this->getData('mail_body');
		$sentat = $this->getData('sent_at');
		$sender = $this->getData('mail_sender');
		$parts  = empty($this->getData('mail_parts')) ? [] : @unserialize(gzdecode($this->getData('mail_parts')));

		// recherche des couleurs configurables
		$config = @unserialize(Mage::getStoreConfig('maillog/general/special_config'));

		if (!empty($config) && is_array($config)) {

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

		$bgColor = empty($bgColor) ? $this->getDefaultBgColor()  : $bgColor;
		$ttColor = empty($ttColor) ? $this->getDefaultTxtColor() : $ttColor;

		// gestion de la langue
		// soit la langue du back-office, soit la langue du front-office
		$oldLocale = Mage::getSingleton('core/translate')->getLocale();
		$newLocale = Mage::app()->getStore()->isAdmin() ? $oldLocale : Mage::getStoreConfig('general/locale/code');
		Mage::getSingleton('core/translate')->setLocale($newLocale)->init('adminhtml', true);

		// mail au format texte
		if ((mb_stripos($body, '</p>') === false) && (mb_stripos($body, '</td>') === false) && (mb_stripos($body, '</div>') === false))
			$body = '<pre>'.$body.'</pre>';

		// génération du code HTML
		// avec du code CSS histoire de faire jolie
		$design = Mage::getDesign()->setPackageName('default');
		$html = [];
		$body = (mb_stripos($body, '</head>') === false) ? $body : str_replace('</html>', '', mb_substr($body, mb_stripos($body, '</head>') + 7));
		$body =
			'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'."\n".
			'<html lang="'.mb_substr($newLocale, 0, 2).'">'."\n".
			'<head>'."\n".
				'<title>'.$this->getSubject().'</title>'."\n".
				'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n".
				'<meta name="robots" content="noindex,nofollow">'."\n".
				'<link rel="icon" type="image/x-icon" href="'.Mage::getDesign()->getSkinUrl('favicon.ico').'">'."\n".
				'<style type="text/css">'."\n".
				'body { margin:0; padding:0 2rem 2rem !important; overflow-y:scroll; }'."\n".
				'body > ul.attachments {'."\n".
				' display:flex; justify-content:center; margin:0 -2rem 2.4em;'."\n".
				' list-style:none; font-size:0.7rem; color:'.$ttColor.'; background-color:'.$bgColor.';'."\n".
				'}'."\n".
				'body > ul.attachments li { margin:1em 0; line-height:142%; }'."\n".
				'body > ul.attachments li:first-child {'."\n".
				' display:flex; flex-direction:column; justify-content:center; padding:0 4em 0 58px; height:60px;'."\n".
				' background:url("'.$design->getSkinUrl('images/luigifab/maillog/humanity-mail.svg').'") no-repeat left center;'."\n".
				'}'."\n".
				'body > ul.attachments li:first-child a { text-decoration:underline; color:'.$ttColor.'; }'."\n".
				'body > ul.attachments li a[type] {'."\n".
				' display:flex; flex-direction:column; justify-content:center; padding:0 1.7em 0 50px; height:60px;'."\n".
				' color:'.$ttColor.'; text-decoration:none; cursor:pointer; background-repeat:no-repeat; background-position:left center;'."\n".
				'}'."\n".
				'body > ul.attachments li a[type] { background-image:url("'.
					$design->getSkinUrl('images/luigifab/maillog/humanity-file.svg').'"); }'."\n".
				'body > ul.attachments li a[type="application/pdf"] { background-image:url("'.
					$design->getSkinUrl('images/luigifab/maillog/humanity-pdf.svg').'"); }'."\n".
				'body > p.emailold { margin:6em; text-align:center; font-size:13px; color:#E41101; }'."\n".
				'body > pre { margin:1em; white-space:pre-wrap; }'."\n".
				'@media print {'."\n".
				' body > ul.attachments { font-size:0.6rem; }'."\n".
				' body > ul.attachments span.print { display:none; }'."\n".
				' body > ul.attachments li:first-child a { text-decoration:none; }'."\n".
				'}'."\n".
				'</style>'."\n".
			'</head>'."\n".
			((mb_stripos($body, '<body') === false) ? '<body>'."\n".$body."\n".'</body>' : $body)."\n".
			'</html>';

		// suppression de l'éventuelle image de marquage
		// lorsque l'administrateur visualise l'email en ligne
		if ($noMark && (mb_stripos($body, 'maillog/view/mark') !== false))
			$body = preg_replace('#[\-\s]*<img[^>]+maillog/view/mark[^>]+>[\-\s]*#', ' ', $body);

		// suppression de l'éventuel lien voir la version en ligne
		// lorsque le client (et non l'administrateur) visualise son email en ligne
		if (!$noMark && (mb_stripos($body, 'maillog/view/index') !== false))
			$body = preg_replace('#[\-\s]*<a[^>]+maillog/view/index.+</a>[\-\s]*#', ' ', $body);

		// entête de l'email
		// fait pleins de choses
		if (true) {

			$html[] = '<ul class="attachments">';

			// informations du mail
			// sujet, date d'envoi, expéditeur, lien d'impression
			if (in_array($sentat, ['', '0000-00-00 00:00:00', null])) {
				$html[] = '<li><strong>'.$help->__('Subject: %s', $this->getSubject())."</strong>\n".
					(!empty($sender) ? '<span>'.$help->__('Sender: %s', $help->getHumanEmailAddress($sender))."</span>\n" : '').
					'<span class="print">'.
						$help->__('<a %s>Print</a> this email only if necessary.', 'href="javascript:self.print();"').
					'</span></li>';
			}
			else {
				$html[] = '<li><strong>'.$help->__('Subject: %s', $this->getSubject())."</strong>\n".
					'<span>'.$help->__('Sent At: %s', $date->date($sentat)->toString(Zend_Date::DATETIME_FULL))."</span>\n".
					(!empty($sender) ? '<span>'.$help->__('Sender: %s', $help->getHumanEmailAddress($sender))."</span>\n" : '').
					'<span class="print">'.
						$help->__('<a %s>Print</a> this email only if necessary.', 'href="javascript:self.print();"').
					'</span></li>';
			}

			// pièces jointes
			// nom et extension, taille, lien
			foreach ($parts as $key => $part) {

				$size = rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
				$size = $help->getNumberToHumanSize(mb_strlen(base64_decode($size)));
				$url  = $this->getEmbedUrl('download', ['_secure' => Mage::app()->getStore()->isCurrentlySecure(), 'part' => $key]);

				$html[] = '<li><a href="'.$url.'" type="'.$part->type.'"><strong>'.$part->filename.'</strong> <span>'.$size.'</span></a></li>';
			}

			$html[] = '</ul>';
		}

		// mail supprimé
		// deleted=1 via cleanOldData()
		if (!empty($this->getData('deleted')))
			$html[] = '<p class="emailold">'.$help->__('Sorry, your email is too old, it is not available online anymore.').'</p>';

		if ($newLocale != $oldLocale)
			Mage::getSingleton('core/translate')->setLocale($oldLocale)->init('adminhtml', true);

		return preg_replace('#<body([^>]*)>#', '<body$1>'."\n".implode("\n", $html), $body);
	}

	// adresse de la vue magasin de l'email
	// pour les URLs du back-office vers le front-office, utilise l'url de la vue magasin par défaut
	public function getEmbedUrl($action, $params = []) {

		$store  = is_object(Mage::getDesign()->getStore()) ? Mage::getDesign()->getStore() : Mage::app()->getStore();
		$params = array_merge(['_secure' => false, 'key' => $this->getData('uniqid')], $params);

		if (empty($params['_store']) || Mage::app()->getStore()->isAdmin())
			$params['_store'] = Mage::app()->getDefaultStoreView()->getId();

		$url = preg_replace('#\?SID=.+$#', '', $store->getUrl('maillog/view/'.$action, $params));

		if (Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_USE_REWRITES, $store->getId()))
			$url = preg_replace('#/[^/]+\.php\d*/#', '/', $url);
		else
			$url = preg_replace('#/[^/]+\.php(\d*)/#', '/index.php$1/', $url);

		return $params['_secure'] ? str_replace('http:', 'https:', $url) : $url;
	}
}