<?php
/**
 * Created D/22/03/2015
 * Updated J/03/09/2015
 * Version 16
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

		// $parts[0]->getContent() = $zend->body si le mail n'a pas de pièce jointe
		$body = trim(quoted_printable_decode($parts[0]->getContent()));

		if (stripos($body, '<!--@vars') === 0)
			$body = substr($body, stripos($body, '-->') + 3);

		$this->setUniqid(substr(sha1(time().$this->getMailRecipients().$this->getMailSubject()), 0, 30));

		// minifie le code HTML avec ou sans Tidy ou pas en fonction de la configuration
		// puis remplace #online# et #readimg# par leur valeurs
		if ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) {

			if ((Mage::getStoreConfig('maillog/general/minify') === 'tidy') && extension_loaded('tidy') && class_exists('tidy', false))
				$body = $this->cleanWithTidy($body);
			else if (in_array(Mage::getStoreConfig('maillog/general/minify'), array('tidy', 'manual')))
				$body = $this->cleanWithReplace($body);

			if (strpos($body, '#online#') !== false)
				$body = str_replace('#online#', $this->getMaillogUrl('index'), $body);

			if (strpos($body, '#readimg#') !== false)
				$body = str_replace('#readimg#', '<img src="'.$this->getMaillogUrl('mark').'" width="1" height="1" alt="">', $body);
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
			$this->setType(trim($mailid));
		}

		$this->setMailBody(trim($body));

		if (count($parts) > 1)
			$this->setMailParts(gzencode(serialize($parts)));
	}


	// envoi réel de l'email avec la fonction mail de PHP
	// ou envoi en arrière plan si la configuration le permet
	public function send($now = false) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to send it.');

		if ($now || (Mage::getStoreConfig('maillog/general/background') !== '1')) {

			// sans pièce jointe
			if (is_null($this->getMailParts())) {
				$body = quoted_printable_encode($this->getMailBody());
			}
			// avec pièce jointe
			else {
				preg_match('#boundary="([^"]+)"#', $this->getMailHeader(), $bound);
				$bound = $bound[1];

				$body = 'This is a message in Mime Format.  If you see this, your mail reader does not support this format.';
				$parts = unserialize(gzdecode($this->getMailParts()));

				foreach ($parts as $key => $part) {

					$body .= "\r\n\r\n";

					if ($key == 0) {
						$body .= '--'.$bound."\r\n";
						$body .= 'Content-Type: '.$part->type.'; charset='.$part->charset."\r\n";
						$body .= 'Content-Transfer-Encoding: '.$part->encoding."\r\n";
						$body .= 'Content-Disposition: '.$part->disposition."\r\n\r\n";
						$body .= quoted_printable_encode($this->getMailBody());
					}
					else {
						$body .= '--'.$bound."\r\n";
						$body .= 'Content-Type: '.$part->type."\r\n";
						$body .= 'Content-Transfer-Encoding: '.$part->encoding."\r\n";
						$body .= 'Content-Disposition: '.$part->disposition.'; filename="'.$part->filename.'"'."\r\n\r\n";
						$body .= rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
					}
				}

				$body .= "\r\n\r\n".'--'.$bound."\r\n";
			}

			$this->setSize(
				strlen($this->getEncodedMailRecipients()) + // utilise les destinataires originaux
				strlen($this->getEncodedMailSubject()) +    // utilise le sujet original
				strlen($body) +                             // utilise la nouvelle version encodée du contenu
				strlen($this->getMailHeader()) +            // utilise les entêtes originaux
				strlen($this->getMailParameters())          // utilise les paramètres originaux
			);

			$result = mail(
				$this->getEncodedMailRecipients(),          // version originale
				$this->getEncodedMailSubject(),             // version originale
				$body,
				$this->getMailHeader(),                     // version originale
				$this->getMailParameters()                  // version originale
			);

			if (in_array($this->getSentAt(), array('', '0000-00-00 00:00:00', null)))
				$this->setSentAt(strftime('%Y-%m-%d %H:%M:%S', time()));

			$this->setStatus(($result === true) ? 'sent' : 'error');
			$this->save();
		}
		else {
			$program = substr(__FILE__, 0, strpos(__FILE__, 'Model')).'lib/mail.php';
			exec('php '.$program.' '.$this->getId().' >/dev/null 2>&1 &');
		}
	}


	// préparation de l'email pour un affichage dans une page web
	// supprime l'image de marquage si demandé
	public function toHtml($noMark) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to print it.');

		Mage::getSingleton('core/translate')->setLocale(Mage::getStoreConfig('general/locale/code'))->init('adminhtml', true);

		$body = $this->getMailBody();
		$parts = (!is_null($this->getMailParts())) ? unserialize(gzdecode($this->getMailParts())) : array();
		array_shift($parts); // dégomme la première pièce jointe

		// mail au format texte
		if ((strpos($body, '</p>') === false) && (strpos($body, '</td>') === false) && (strpos($body, '</div>') === false))
			$body = '<pre>'.$body.'</pre>';

		// génération du code HTML
		// avec du code CSS
		$body = (strpos($body, '<body') === false) ? '<body style="margin:0;">'."\n".$body."\n".'</body>' : $body;
		$body = (strpos($body, '<html') === false) ?
			//'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'."\n".
			'<html>'."\n".
			'<head>'."\n".
				'<title>'.htmlentities($this->getMailSubject()).'</title>'."\n".
				'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n".
				'<style type="text/css">'."\n".
				'body > ul.attachments { margin:0 0 2.5em; padding:1em; text-align:center; font-size:0.7em; background-color:#F6F6F6; }'."\n".
				'body > ul.attachments li { display:inline-block; text-align:left; vertical-align:middle; }'."\n".
				'body > ul.attachments li:first-child {'."\n".
				'	padding:0 4em 0 60px; line-height:140%; white-space:pre-wrap;'."\n".
				'	background:url("'.Mage::getDesign()->setPackageName('default')->getSkinUrl('images/luigifab/maillog/humanity-mail.svg').'") no-repeat left -3px;'."\n".
				'}'."\n".
				'body > ul.attachments li:first-child a { text-decoration:underline; color:#555; }'."\n".
				'body > ul.attachments li a[type] {'."\n".
				'	display:inline-block; padding:0 1.7em 0 50px; height:48px;'."\n".
				'	color:inherit; text-decoration:none; background-repeat:no-repeat; background-position:center left;'."\n".
				'}'."\n".
				'body > ul.attachments li a[type] span:first-child { display:block; line-height:220%; font-weight:bold; }'."\n".
				'body > ul.attachments li a[type] span:last-child { display:block; line-height:70%; }'."\n".
				'body > ul.attachments li a[type] { background-image:url("'.
					Mage::getDesign()->setPackageName('default')->getSkinUrl('images/luigifab/maillog/humanity-file.svg').'"); }'."\n".
				'body > ul.attachments li a[type="application/pdf"] { background-image:url("'.
					Mage::getDesign()->setPackageName('default')->getSkinUrl('images/luigifab/maillog/humanity-pdf.svg').'"); }'."\n".
				'body > pre { white-space:pre-wrap; }'."\n".
				'@media print {'."\n".
				'	body > ul.attachments { font-size:0.6em; }'."\n".
				'	body > ul.attachments li:first-child a { text-decoration:none; }'."\n".
				'}'."\n".
				'</style>'."\n".
			'</head>'."\n".
			$body."\n".
			'</html>' : $body;

		// suppression de l'éventuelle image de marquage
		if ($noMark && (strpos($body, 'maillog/view/mark') !== false))
			$body = preg_replace('#\s*<img[^>]+maillog/view/mark[^>]+>#', '', $body);

		// suppression de l'éventuel lien voir la version en ligne
		if (!$noMark && (strpos($body, 'maillog/view/index') !== false))
			$body = preg_replace('#\s*<a[^>]+maillog/view/index.+</a>#', '&nbsp;', $body);

		// informations du mail
		// sujet - date d'envoi - lien d'impression
		$html = array();
		$html[] = '<ul class="attachments">';

		$that = Mage::helper('maillog');
		$date = Mage::getSingleton('core/locale'); //date($date, $format, $locale = null, $useTimezone = null)

		if (!in_array($this->getSentAt(), array('', '0000-00-00 00:00:00', null)))
			$html[] = '<li><strong>'.$that->__('Subject: %s', htmlentities($this->getMailSubject()))."</strong>\n".$that->__('Sent At: %s', $date->date($this->getSentAt(), Zend_Date::ISO_8601)->toString(Zend_Date::DATETIME_FULL))."\n".$that->__('<a href="%s">Print</a> this email only if necessary.', 'javascript:window.print();').'</li>';
		else
			$html[] = '<li><strong>'.$that->__('Subject: %s', htmlentities($this->getMailSubject()))."</strong>\n".$that->__('<a href="%s">Print</a> this email only if necessary.', 'javascript:window.print();').'</li>';

		// pièces jointes
		// nom et extension - taille - lien
		foreach ($parts as $key => $part) {

			$size = rtrim(chunk_split(str_replace("\n", '', $part->getContent())));
			$size = $that->getNumberToHumanSize(strlen(base64_decode($size)));
			$url  = $this->getMaillogUrl('download', array('_secure' => Mage::app()->getStore()->isCurrentlySecure(), 'part' => $key + 1));

			$html[] = '<li><a href="'.$url.'" type="'.$part->type.'"><span>'.$part->filename.'</span> <span>'.$size.'</span></a></li>';
		}

		$html[] = '</ul>';
		$body = str_replace('<body style="margin:0;">', '<body style="margin:0;">'."\n".implode("\n", $html), $body);

		return $body;
	}


	// minification, recherches et remplacements
	private function cleanWithTidy($html) {

		$search = array(
			'#\n?<!\-\-[^\-\->]+\-\->#',
			'#css">\s+\/\*<!\[CDATA\[\*\/#',
			'#\/\*\]\]>\*\/\s+<\/style#',
			'#" \/>#',
			'#<\/li>\s*<li#',
			'#>\s*<\/script>#',
			'#\-\->\s{2,}#',
			'#\s*<!\-\-\[if IE ([0-9]+)\]>\s*<#',
			'#type=\'text\/javascript\'>\s*\/\/<!#',
			'#\s*\/\/\]\]><\/script>#',
			'#<br ?\/>\s+#',
			'#<br>\s+#',
			'#</code>\s</pre>#'
		);
		$replace = array(
			'',
			'css">',
			'</style',
			'"/>',
			'</li><li',
			'></script>',
			"-->\n",
			"\n<!--[if IE $1]><",
			'type="text/javascript">//<!',
			"\n//]]></script>",
			'<br/>',
			'<br>',
			'</code></pre>'
		);

		$tidy = new Tidy();
		$tidy->parseString($html, realpath(dirname(__FILE__).'/../etc/tidy.conf'), 'utf8');
		$tidy->cleanRepair();

		$html = $tidy;
		$html = preg_replace($search, $replace, $html);

		return $html;
	}

	private function cleanWithReplace($html) {

		$html = str_replace("\t", '', $html);
		$html = str_replace("\r", "\n", $html);

		$html = preg_replace('# {4,}#', '', $html);
		$html = preg_replace('#\n?<!\-\-[^\-\->]+\-\->#', '', $html);
		$html = preg_replace('#\n{2,}#', "\n", $html);

		return $html;
	}

	// adresse de la vue magasin par défaut
	// avec HTTPS si le back-office est sur HTTPS (voir $param)
	// avec HTTPS si le back-office est sur HTTPS même si le front-office n'est pas sur HTTPS (voir $param)
	public function getMaillogUrl($key = 'index', $param = array()) {

		$param = array_merge(array('_secure' => false, 'key' => $this->getUniqid()), $param);
		$url = Mage::app()->getDefaultStoreView()->getUrl('maillog/view/'.$key, $param);

		if (Mage::getStoreConfig('web/seo/use_rewrites', Mage::app()->getDefaultStoreView()->getStoreId()) === '1')
			$url = preg_replace('#/[a-z0-9_]+\.php/#', '/', $url);

		return ($param['_secure']) ? str_replace('http:', 'https:', $url) : $url;
	}
}