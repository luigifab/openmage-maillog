<?php
/**
 * Created D/22/03/2015
 * Updated J/07/05/2015
 * Version 8
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
		// puis remplace #online# et #readimg# par leur valeurs ou rien en fonction de la configuration
		if ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) {

			if ((Mage::getStoreConfig('maillog/content/minify') === 'tidy') && extension_loaded('tidy') && class_exists('tidy', false))
				$body = $this->cleanWithTidy($body);
			else if (in_array(Mage::getStoreConfig('maillog/content/minify'), array('tidy', 'manual')))
				$body = $this->cleanWithReplace($body);

			if ((Mage::getStoreConfig('maillog/content/online') === '1') && (strpos($body, '#online#') !== false)) {
				$url  = $this->getFrontUrl('maillog/view/index', array('_secure' => false, 'key' => $this->getUniqid()));
				$body = str_replace('#online#', $url, $body);
			}
			else if (strpos($body, '#online#') !== false) {
				$body = str_replace('#online#', '', $body);
			}

			if ((Mage::getStoreConfig('maillog/content/readimg') === '1') && (strpos($body, '#readimg#') !== false)) {
				$url  = $this->getFrontUrl('maillog/view/mark', array('_secure' => false, 'key' => $this->getUniqid()));
				$body = str_replace('#readimg#', '<img src="'.$url.'" width="1" height="1" alt="">', $body);
			}
			else if (strpos($body, '#readimg#') !== false) {
				$body = str_replace('#readimg#', '', $body);
			}
		}

		// recherche et remplace #uniqid# par sa valeur ou rien en fonction de la configuration
		if ((Mage::getStoreConfig('maillog/content/uniqid') === '1') && (strpos($body, '#uniqid#') !== false)) {
			$body = str_replace('#uniqid#', $this->getUniqid(), $body);
		}
		else if (strpos($body, '#uniqid#') !== false) {
			$body = str_replace('#uniqid#', '', $body);
		}

		// recherche et remplace #mailid# par sa valeur ou rien en fonction de la configuration
		if ((Mage::getStoreConfig('maillog/content/mailid') === '1') && (strpos($body, '#mailid#') !== false)) {
			$mailid = substr($body, strpos($body, '#mailid#'));
			$mailid = substr($mailid, strlen('#mailid#'));
			$mailid = substr($mailid, 0, strpos($mailid, '#'));
			$body = str_replace('#mailid#'.$mailid.'#', $mailid, $body);
			$this->setType(trim($mailid));
		}
		else if (strpos($body, '#mailid#') !== false) {
			$body = str_replace('#mailid#'.$mailid.'#', '', $body);
		}

		$this->setMailBody(trim($body));

		if (count($parts) > 1)
			$this->setMailParts(gzencode(serialize($parts)));
	}


	// envoi réel de l'email avec la fonction mail de PHP
	// envoi en arrière plan si la configuration le permet
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
	public function printOnlineMail($withoutMark, $withAttachments) {

		if ($this->getId() < 1)
			Mage::throwException('You must load an email before trying to print it.');

		$body = $this->getMailBody();

		// génération du code HTML et suppression de l'éventuelle l'image de marquage
		// ajout des pièces jointes
		if ((strpos($body, '</p>') !== false) || (strpos($body, '</td>') !== false) || (strpos($body, '</div>') !== false)) {

			$body = (strpos($body, '<body') === false) ? '<body>'."\n".$body."\n".'</body>' : $body;
			$body = (strpos($body, '<html') === false) ? '<html>'."\n".'<head>'."\n".'<title>'.htmlentities($this->getMailSubject()).'</title>'."\n".'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n".$body."\n".'</html>' : $body;
			$body = (strpos($body, '</head>') === false) ? str_replace('<body', '</head>'."\n".'<body', $body) : $body;

			if ($withoutMark) {
				$body = (strpos($body, 'maillog/view/mark') !== false) ?
					preg_replace('#\s*<img src=".+maillog/view/mark.+" width="1" height="1" alt="">#', ' #', $body) : $body;
			}

			if ($withAttachments && !is_null($this->getMailParts())) {

				$parts = unserialize(gzdecode($this->getMailParts()));

				// design des pièces jointes
				$html = '<style type="text/css">';
				$html .= 'ul.attachments { margin:3em 0 1em; padding:1em; font-size:0.7em; border-top:1px solid #CCC; }';
				$html .= 'ul.attachments li { display:inline-block; vertical-align:middle; }';
				$html .= 'ul.attachments li a {';
				$html .= '	display:inline-block; padding:0 1.7em 0 50px; height:48px;';
				$html .= '	color:inherit; text-decoration:none; background-repeat:no-repeat; background-position:center left;';
				$html .= '}';
				$html .= 'ul.attachments li a span:first-child { display:block; line-height:220%; font-weight:bold; }';
				$html .= 'ul.attachments li a span:last-child { display:block; line-height:70%; }';
				$html .= 'ul.attachments li a {';
				$html .= '	background-image:url("'.Mage::getDesign()->getSkinUrl('images/luigifab/maillog/humanity-file.svg').'"); }';
				$html .= 'ul.attachments li a[type="application/pdf"] {';
				$html .= '	background-image:url("'.Mage::getDesign()->getSkinUrl('images/luigifab/maillog/humanity-pdf.svg').'"); }';
				$html .= '</style>';
				$body = str_replace('</head>', $html."\n".'</head>', $body);

				// liens des pièces jointes
				$html = '';
				foreach ($parts as $key => $part) {

					if ($key > 0) {

						$this->setSize(strlen( base64_decode(rtrim(chunk_split(str_replace("\n", '', $part->getContent())))) ));
						$size = Mage::getBlockSingleton('maillog/adminhtml_history_grid')->decorateSize(null, $this, null, false);

						$html .= "\n".'<li><a href="'.Mage::getUrl('*/*/download', array('key' => $this->getUniqid(), 'part' => $key)).'" type="'.$part->type.'"><span>'.$part->filename.'</span> <span>'.$size.'</span></a></li>';
					}
				}
				$body = str_replace('</body>', '<ul class="attachments">'.$html."\n".'</ul>'."\n".'</body>', $body);
			}
		}

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
	private function getFrontUrl($key, $param) {
		return preg_replace('#/[a-z0-9_]+\.php/#', '/', Mage::app()->getDefaultStoreView()->getUrl($key, $param));
	}
}