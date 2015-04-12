<?php
/**
 * Created D/22/03/2015
 * Updated D/05/04/2015
 * Version 11
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

class Luigifab_Maillog_Helper_Data extends Mage_Core_Helper_Abstract {

	public function getVersion() {
		return (string) Mage::getConfig()->getModuleConfig('Luigifab_Maillog')->version;
	}

	public function sendMail($zendMail, $mailData) {

		$email = Mage::getModel('maillog/email');
		$email->setStatus('pending');
		$email->setCreatedAt(strftime('%Y-%m-%d %H:%M:%S', time()));

		// enregistre la version originale des entêtes, des paramètres, des destinataires et du sujet => AUCUN TRAITEMENT
		// pour l'envoi réel et le calcul de la taille approximative de l'email
		$email->setMailHeader($zendMail->header);
		$email->setMailParameters($zendMail->parameters);
		$email->setEncodedMailRecipients($zendMail->recipients);
		$email->setEncodedMailSubject($mailData->getSubject());

		// enregistre la version décodée des destinataires et du sujet => UN SEUL TRAITEMENT LE DÉCODAGE
		// pour la recherche en base de données et l'affichage
		$email->setMailRecipients($zendMail->recipients);
		$email->setMailSubject($mailData->getSubject());

		// enregistre la version décodée du contenu du mail => NOMBREUX TRAITEMENTS
		// pour les différents traitements, l'affichage, l'envoi réel et le calcul de la taille approximative de l'email
		$email->setMailBody($zendMail->body);

		$email->save();
		$email->send();
	}
}