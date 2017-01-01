<?php
/**
 * Created D/22/03/2015
 * Updated W/21/12/2016
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

class Luigifab_Maillog_Model_Email extends Mage_Core_Model_Abstract {

	public function _construct() {
		$this->_init('maillog/email');
	}


	// traitement des données
	public function setMailRecipients($data) {
		$this->setData('mail_recipients', iconv_mime_decode($data, 0, 'utf-8'));
	}

	public function setMailSubject($data) {
		$this->setData('mail_subject', iconv_mime_decode($data, 0, 'utf-8'));
	}

	public function setMailContent($parts) {

		$this->setData('uniqid', substr(sha1(time().$this->getMailRecipients().$this->getMailSubject()), 0, 30));

		// récupération des données du mail
		// $parts[0]->getContent() = $zend->body si le mail n'a pas de pièce jointe
		$body = trim(quoted_printable_decode($parts[0]->getContent()));
		array_shift($parts);

		if (stripos($body, '<!--@vars') === 0)
			$body = substr($body, stripos($body, '-->') + 3);

		// minifie le code HTML avec ou sans Tidy en fonction de la configuration
		// remplace #online# et #readimg# par leur valeurs (il est possible d'ajouter le storeId à #online#)
		// uniquement si c'est un mail au fomat HTML
		if ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) {

			if ((Mage::getStoreConfig('maillog/general/minify') === 'tidy') && extension_loaded('tidy') && class_exists('tidy', false))
				$body = $this->cleanWithTidy($body);
			else if (in_array(Mage::getStoreConfig('maillog/general/minify'), array('tidy', 'manual')))
				$body = $this->cleanWithReplace($body);

			if (strpos($body, '#online#') !== false)
				$body = preg_replace_callback('/#online#([0-9]{0,5})/', function ($matches) {
					return $this->getEmbedUrl('index', (isset($matches[1])) ? array('_store' => intval($matches[1])) : array());
				}, $body);

			if (strpos($body, '#readimg#') !== false)
				$body = str_replace('#readimg#', '<img src="'.$this->getEmbedUrl('mark').'" width="1" height="1" alt="">', $body);
		}

		// recherche et remplace #uniqid# par sa valeur
		if (strpos($body, '#uniqid#') !== false)
			$body = str_replace('#uniqid#', $this->getUniqid(), $body);

		// recherche et remplace #mailid# par rien
		if (strpos($body, '#mailid#') !== false) {
			$mailid = substr($body, strpos($body, '#mailid#'));
			$mailid = substr($mailid, strlen('#mailid#'));
			$mailid = substr($mailid, 0, strpos($mailid, '#'));
			$body = str_replace('#mailid#'.$mailid.'#', '', $body);
			$this->setData('type', trim($mailid));
		}

		$this->setData('mail_body', trim($body));
		$this->setData('mail_parts', (!empty($parts)) ? gzencode(serialize($parts), 9, FORCE_GZIP) : null);

		if (Mage::getSingleton('core/cookie')->get('maillog_print_email') === 'yes')
			exit('<p style="margin:0; padding:0.5em 1em; font-size:12px; color:white; background-color:red;">Cookie "maillog_print_email=yes" detected!</p>'.$body);
	}

	// nettoyage du code html
	private function cleanWithTidy($html) {

		$html = str_replace('></option>', '>&nbsp;</option>', $html);
		$html = str_replace('<pre></pre>', '<pre>&nbsp;</pre>', $html);

		$tidy = new Tidy();
		$tidy->parseString($html, realpath(dirname(__FILE__).'/../etc/tidy.conf'), 'utf8');
		$tidy->cleanRepair();

		$html = $tidy;
		$html = $this->cleanWithReplace($html);
		$html = str_replace("\"\n   ", '"', $html);
		$html = str_replace('>&nbsp;</option>', '></option>', $html);
		$html = preg_replace('#<pre>\s+<\/pre>#', '<pre></pre>', $html);
		$html = preg_replace('#\s+<\/textarea>#', '</textarea>', $html);

		return $html;
	}

	private function cleanWithReplace($html) {

		$html = str_replace("\t", '', $html);
		$html = str_replace("\r", "\n", $html);

		$html = preg_replace('# {4,}#', '', $html);
		$html = preg_replace('#\n?<!\-\-[^\-\->]+\-\->#', '', $html);
		$html = preg_replace('#\n{2,}#', "\n", $html);

		$search = array(
			'#css">\s+\/\*<!\[CDATA\[\*\/#',
			'#\/\*\]\]>\*\/\s+<\/style#',
			'#" \/>#',
			'#<\/li>\s*<li#',
			'#>\s*<\/script>#',
			'#\-\->\s{2,}#',
			'#\s*<!\-\-\[if([^\]]+)\]>\s*<#',
			'#>\s*<!\[endif\]\-\->#',
			'#>\s*\/?\/?<!\[CDATA\[#',
			'#\s*\/?\/?\]\]><\/script>#',
			'#<br ?\/?>\s+#',
			'#</code>\s</pre>#'
		);
		$replace = array(
			'css">',
			'</style',
			'"/>',
			'</li><li',
			'></script>',
			"-->\n",
			"\n<!--[if$1]><",
			'><![endif]-->',
			'>//<![CDATA[',
			"\n//]]></script>",
			'<br>', // ici pas de /
			'</code></pre>'
		);

		return preg_replace($search, $replace, $html);
	}


	// envoi réel de l'email si la configuration le permet
	// ou envoi en arrière plan si la configuration le permet
	public function send($now = false) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to send it.');

		$bounce = Mage::getModel('maillog/bounce')->load($this->getMailRecipients());

		$isBounce = $bounce->isBounce(); // boolean
		$isBackground = Mage::getStoreConfigFlag('maillog/general/background'); // boolean
		$canSend = Mage::getStoreConfigFlag('maillog/general/send'); // boolean

		if ($now || $isBounce || !$isBackground || !$canSend) {

			if (is_null($this->getMailParts())) {
				$body = quoted_printable_encode($this->getMailBody());
			}
			else {
				preg_match('#boundary="([^"]+)"#', $this->getMailHeader(), $bound);
				$bound = $bound[1];

				$body = $this->getMailBody();
				$encoding = ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) ?
					'text/html' : 'text/plain';

				$body  = 'This is a message in Mime Format.  If you see this, your mail reader does not support this format.'."\r\n\r\n";
				$body .= '--'.$bound."\r\n";
				$body .= 'Content-Type: '.$encoding.'; charset=utf-8'."\r\n";
				$body .= 'Content-Transfer-Encoding: quoted-printable'."\r\n";
				$body .= 'Content-Disposition: inline'."\r\n\r\n";
				$body .= quoted_printable_encode($this->getMailBody());

				$parts = unserialize(gzdecode($this->getMailParts()));

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
				strlen($this->getEncodedMailRecipients()) + // utilise les destinataires originaux
				strlen($this->getEncodedMailSubject()) +    // utilise le sujet original
				strlen($body) +                             // utilise la nouvelle version encodée du contenu
				strlen($this->getMailHeader()) +            // utilise les entêtes originaux
				strlen($this->getMailParameters())          // utilise les paramètres originaux
			);

			if ($isBounce) {
				$this->setData('status', 'bounce')->save();
				$bounce->setData('notsent', $bounce->getData('notsent') + 1)->save();
			}
			else if (!$canSend) {
				$this->setData('status', 'notsent')->save();
			}
			else {
				$this->setData('status', 'sending')->save();

				$result = mail(
					$this->getEncodedMailRecipients(), // version originale
					$this->getEncodedMailSubject(),    // version originale
					$body,
					$this->getMailHeader(),            // version originale
					$this->getMailParameters()         // version originale
				);

				if (in_array($this->getSentAt(), array('', '0000-00-00 00:00:00', null)))
					$this->setData('sent_at', date('Y-m-d H:i:s'));

				$this->setData('status', ($result === true) ? 'sent' : 'error')->save();
			}
		}
		else {
			$program = substr(__FILE__, 0, strpos(__FILE__, 'Model')).'lib/mail.php';
			exec('php '.$program.' '.$this->getId().' >/dev/null 2>&1 &');
		}
	}

	// préparation de l'email pour un affichage dans une page web
	// supprime l'image de marquage lorsque demandé (très utile dans le back-office)
	public function toHtml($noMark) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to display it.');

		Mage::getSingleton('core/translate')->setLocale(Mage::getStoreConfig('general/locale/code'))->init('adminhtml', true);

		$help = Mage::helper('maillog');
		$date = Mage::getSingleton('core/locale'); //date($date, $format, $locale = null, $useTimezone = null)
		$body = $this->getMailBody();

		// mail au format texte
		if ((strpos($body, '</p>') === false) && (strpos($body, '</td>') === false) && (strpos($body, '</div>') === false))
			$body = '<pre>'.$body.'</pre>';

		// génération du code HTML
		// avec du code CSS histoire de faire jolie
		$body = (strpos($body, '<body') === false) ? '<body style="margin:0;">'."\n".$body."\n".'</body>' : $body;
		$body = (strpos($body, '<html') === false) ?
			//'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'."\n".
			'<html>'."\n".
			'<head>'."\n".
				'<title>'.htmlentities($this->getMailSubject()).'</title>'."\n".
				'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n".
				'<style type="text/css">'."\n".
				'body > ul.attachments {'."\n".
				'	display:flex; justify-content:center; margin:0 0 2.4em; list-style:none;'."\n".
				'	font-size:0.7rem; border-bottom:1px solid #EEE; background-color:#F6F6F6;'."\n".
				'}'."\n".
				'body > ul.attachments li { margin:1em 0; line-height:142%; }'."\n".
				'body > ul.attachments li:first-child {'."\n".
				'	display:flex; flex-direction:column; justify-content:center; padding:0 4em 0 58px; height:48px;'."\n".
				'	background:url("'.Mage::getDesign()->setPackageName('default')->getSkinUrl('images/luigifab/maillog/humanity-mail.svg').'") no-repeat left center;'."\n".
				'}'."\n".
				'body > ul.attachments li:first-child a { text-decoration:underline; color:#555; }'."\n".
				'body > ul.attachments li a[type] {'."\n".
				'	display:flex; flex-direction:column; justify-content:center; padding:0 1.7em 0 50px; height:48px;'."\n".
				'	color:inherit; text-decoration:none; background-repeat:no-repeat; background-position:left center;'."\n".
				'}'."\n".
				'body > ul.attachments li a[type] { background-image:url("'.
					Mage::getDesign()->setPackageName('default')->getSkinUrl('images/luigifab/maillog/humanity-file.svg').'"); }'."\n".
				'body > ul.attachments li a[type="application/pdf"] { background-image:url("'.
					Mage::getDesign()->setPackageName('default')->getSkinUrl('images/luigifab/maillog/humanity-pdf.svg').'"); }'."\n".
				'body > pre { margin:1em; white-space:pre-wrap; }'."\n".
				'@media print {'."\n".
				'	body > ul.attachments { font-size:0.6rem; }'."\n".
				'	body > ul.attachments span.print { display:none; }'."\n".
				'	body > ul.attachments li:first-child a { text-decoration:none; }'."\n".
				'}'."\n".
				'</style>'."\n".
			'</head>'."\n".
			$body."\n".
			'</html>' : $body;

		// suppression de l'éventuelle image de marquage
		// lorsque l'administrateur visualise l'email en ligne
		if ($noMark && (strpos($body, 'maillog/view/mark') !== false))
			$body = preg_replace('#\s*<img[^>]+maillog/view/mark[^>]+>#', '', $body);

		// suppression de l'éventuel lien voir la version en ligne
		// lorsque le client (et non l'administrateur) visualise son email en ligne
		if (!$noMark && (strpos($body, 'maillog/view/index') !== false))
			$body = preg_replace('#\s*<a[^>]+maillog/view/index.+</a>#', '&nbsp;', $body);

		// informations du mail
		// sujet, date d'envoi, lien d'impression
		$html = array('<ul class="attachments">');
		$print = 'href="javascript:window.print();"';

		if (!in_array($this->getSentAt(), array('', '0000-00-00 00:00:00', null))) {
			$html[] = '<li><strong>'.$help->__('Subject: %s', htmlentities($this->getMailSubject()))."</strong>\n".
				'<span>'.$help->__('Sent At: %s', $date->date($this->getSentAt(), Zend_Date::ISO_8601)->toString(Zend_Date::DATETIME_FULL))."</span>\n".
				'<span class="print">'.$help->__('<a %s>Print</a> this email only if necessary.', $print).'</span></li>';
		}
		else {
			$html[] = '<li><strong>'.$help->__('Subject: %s', htmlentities($this->getMailSubject()))."</strong>\n".
				'<span class="print">'.$help->__('<a %s>Print</a> this email only if necessary.', $print).'</span></li>';
		}

		// pièces jointes
		// nom et extension - taille - lien
		$parts = (!is_null($this->getMailParts())) ? unserialize(gzdecode($this->getMailParts())) : array();
		foreach ($parts as $key => $part) {

			$size = rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
			$size = $help->getNumberToHumanSize(strlen(base64_decode($size)));
			$url  = $this->getEmbedUrl('download', array('_secure' => Mage::app()->getStore()->isCurrentlySecure(), 'part' => $key));

			$html[] = '<li><a href="'.$url.'" type="'.$part->type.'"><strong>'.$part->filename.'</strong> <span>'.$size.'</span></a></li>';
		}

		$html[] = '</ul>';
		$body = preg_replace('#<body([^>]+)>#', '<body$1>'."\n".implode("\n", $html), $body);

		return $body;
	}

	// adresse de la vue magasin du mail
	// pour les urls dans le back-office vers le front-office, on utilise l'url de la vue magasin par défaut
	public function getEmbedUrl($action, $params = array()) {

		$store  = (is_object(Mage::getDesign()->getStore())) ? Mage::getDesign()->getStore() : Mage::app()->getStore();
		$params = array_merge(array('_secure' => false, 'key' => $this->getUniqid()), $params);

		if (Mage::app()->getStore()->isAdmin() || !isset($params['_store']))
			$params['_store'] = Mage::app()->getDefaultStoreView()->getId();

		$url = preg_replace('#\?SID=.+$#', '', $store->getUrl('maillog/view/'.$action, $params));

		if (Mage::getStoreConfigFlag('web/seo/use_rewrites', $store->getStoreId()))
			$url = preg_replace('#/[^/]+\.php[0-9]?/#', '/', $url);
		else
			$url = preg_replace('#/[^/]+\.php[0-9]?/#', '/index.php/', $url);

		return ($params['_secure']) ? str_replace('http:', 'https:', $url) : $url;
	}
}