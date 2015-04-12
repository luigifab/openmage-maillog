<?php
/**
 * Created D/22/03/2015
 * Updated D/05/04/2015
 * Version 4
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


	// traitement des données d'un email
	// décode les données (destintaires, sujet, contenu), traite le contenu, génère un identifiant unique, calcul la taille
	public function setMailRecipients($data) {
		$this->setData('mail_recipients', iconv_mime_decode($data, 0, 'utf-8'));
	}

	public function setMailSubject($data) {
		$this->setData('mail_subject', iconv_mime_decode($data, 0, 'utf-8'));
	}

	public function setMailBody($body) {

		$this->setUniqid();
		$body = quoted_printable_decode($body);

		// minifie le code HTML avec ou sans Tidy ou pas puis remplace #online# et #readimg#
		// recherche et remplace #uniqid# et #mailid#
		if ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) {

			if ((Mage::getStoreConfig('maillog/content/minify') === 'tidy') && extension_loaded('tidy') && class_exists('tidy', false))
				$body = $this->cleanWithTidy($body);
			else if (in_array(Mage::getStoreConfig('maillog/content/minify'), array('tidy', 'manual')))
				$body = $this->cleanWithReplace($body);

			if ((Mage::getStoreConfig('maillog/content/online') === '1') && (strpos($body, '#online#') !== false)) {
				$url  = $this->getFrontUrl('maillog/view/index', array('_secure' => false, 'key' => $this->getUniqid()));
				$body = str_replace('#online#', $url, $body);
			}

			if ((Mage::getStoreConfig('maillog/content/readimg') === '1') && (strpos($body, '#readimg#') !== false)) {
				$url  = $this->getFrontUrl('maillog/view/mark', array('_secure' => false, 'key' => $this->getUniqid()));
				$body = str_replace('#readimg#', '<img src="'.$url.'" width="1" height="1" alt="">', $body);
			}
		}

		if ((Mage::getStoreConfig('maillog/content/uniqid') === '1') && (strpos($body, '#uniqid#') !== false)) {
			$body = str_replace('#uniqid#', $this->getUniqid(), $body);
		}

		if ((Mage::getStoreConfig('maillog/content/mailid') === '1') && (strpos($body, '#mailid#') !== false)) {
			$mailid = substr($body, strpos($body, '#mailid#'));
			$mailid = substr($mailid, strlen('#mailid#'));
			$mailid = substr($mailid, 0, strpos($mailid, '#'));
			$body = str_replace('#mailid#'.$mailid.'#', $mailid, $body);
			$this->setData('type', trim($mailid));
		}

		$this->setData('mail_body', trim($body));
		$this->setSize();
	}

	public function setUniqid() {

		$this->setData('uniqid', substr(sha1(time().$this->getMailRecipients().$this->getMailSubject()), 0, 30));
		return $this;
	}

	public function setSize() {

		$this->setData('size',
			strlen($this->getEncodedMailRecipients()) +             // utilise les destinataires originaux
			strlen($this->getEncodedMailSubject()) +                // utilise le sujet original
			strlen(quoted_printable_encode($this->getMailBody())) + // utilise la nouvelle version du contenu
			strlen($this->getMailHeader()) +                        // utilise les entêtes originaux
			strlen($this->getMailParameters())                      // utilise les paramètres originaux
		);

		return $this;
	}


	// envoi réel de l'email avce la fonction mail de PHP
	// envoi en arrière plan si la configuration le permet
	public function send($now = false) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to send it.');

		if ($now || (Mage::getStoreConfig('maillog/general/background') !== '1')) {

			$result = mail(
				$this->getEncodedMailRecipients(),             // version originale
				$this->getEncodedMailSubject(),                // version originale
				quoted_printable_encode($this->getMailBody()), // encode le nouveau contenu du mail
				$this->getMailHeader(),                        // version originale
				$this->getMailParameters()                     // version originale
			);

			if ($this->getStatus() === 'pending')
				$this->setData('sent_at', strftime('%Y-%m-%d %H:%M:%S', time()));

			$this->setData('status', ($result === true) ? 'sent' : 'error');
			$this->save();
		}
		else {
			$program = substr(__FILE__, 0, strpos(__FILE__, 'Model')).'lib/mail.php';
			exec('php '.$program.' '.$this->getId().' >/dev/null 2>&1 &');
		}
	}


	// préparation de l'email pour un affichage dans une page web
	// supprime l'image de marquage si demandé
	public function printMail($removeMark = false) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to print it.');

		$body  = $this->getMailBody();
		$title = htmlentities($this->getMailSubject());

		// génération du code HTML et suppression de l'éventuelle l'image de marquage
		if ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) {

			$body = (strpos($body, '<body') === false) ? '<body>'."\n".$body."\n".'</body>' : $body;
			$body = (strpos($body, '<html') === false) ? '<html>'."\n".'<head>'."\n".'<title>'.$title.'</title>'."\n".'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n".$body."\n".'</html>' : $body;
			$body = (strpos($body, '</head>') === false) ? str_replace('<body', '</head>'."\n".'<body', $body) : $body;

			if ($removeMark) {
				$body = (strpos($body, 'maillog/view/mark') !== false) ?
					preg_replace('#\s*<img src=".+maillog/view/mark.+" width="1" height="1" alt="">#', ' ##', $body) : $body;
			}
		}

		return $body;
	}


	// minification, recherches et remplacements
	private function cleanWithTidy($html) {

		// minification
		$tidy = new Tidy();
		$tidy->parseString($html, array(
			'output-html'     => true,
			'output-xhtml'    => false,
			'word-2000'       => false,
			'indent'          => false,
			'break-before-br' => true,
			'wrap'            => 0,
			'input-encoding'  => 'utf8',
			'output-encoding' => 'utf8',
			'output-bom'      => false,
			'tidy-mark'       => false,
			'doctype'         => 'transitional',
			'show-body-only'  => true
		), 'utf8');

		$tidy->cleanRepair();
		$html = $tidy;

		// recherches et remplacements
		$search = array(
			'#\n?<!\-\-[^\-\->]+\-\->#',
			'#" \/>#',
			'#<\/li>\s*<li#',
			'#>\s*<\/script>#',
			'#type=\'text\/javascript\'>\s*\/\/<!#',
			'#\s*\/\/\]\]><\/script>#',
			'#<br\/>\s+#',
			'#<br>\s+#'
		);
		$replace = array(
			'',
			'"/>',
			'</li><li',
			'></script>',
			'type="text/javascript">//<!',
			"\n//]]></script>",
			'<br/>',
			'<br>'
		);
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
	private function getFrontUrl($key, $param) {
		return preg_replace('#/[a-z0-9_]+\.php/#', '/', Mage::app()->getDefaultStoreView()->getUrl($key, $param));
	}
}