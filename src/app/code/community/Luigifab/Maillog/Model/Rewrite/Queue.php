<?php
/**
 * Created D/05/04/2015
 * Updated D/11/06/2023
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

class Luigifab_Maillog_Model_Rewrite_Queue extends Mage_Core_Model_Email_Queue {

	public function addMessageToQueue() {

		// ci-dessous une partie de la méthode Mage_Core_Model_Email_Queue::send()
		// cela permet d'envoyer immédiatement l'email et donc de désactiver la file d'attente
		if (Mage::getStoreConfigFlag('maillog/general/enabled')) {

			$params = new Varien_Object($this->getMessageParameters());
			$mailer = new Zend_Mail('utf-8');

			if (!empty($params->getReturnPathEmail())) {
				$mailTransport = new Zend_Mail_Transport_Sendmail('-f'.$params->getReturnPathEmail());
				Zend_Mail::setDefaultTransport($mailTransport);
			}

			$mailer->setSubject('=?utf-8?B?'.base64_encode($params->getSubject()).'?=');
			$mailer->setFrom($params->getFromEmail(), $params->getFromName());

			foreach ($this->getRecipients() as [$email, $name, $type]) {
				if ($type == self::EMAIL_TYPE_BCC)
					$mailer->addBcc($email, '=?utf-8?B?'.base64_encode($name).'?=');
				else
					$mailer->addTo($email, '=?utf-8?B?'.base64_encode($name).'?=');
			}

			if (!empty($params->getReplyTo()))
				$mailer->setReplyTo($params->getReplyTo());
			if (!empty($params->getReturnTo()))
				$mailer->setReturnPath($params->getReturnTo());

			if (empty($params->getIsPlain()))
				$mailer->setBodyHTML($this->getData('message_body'));
			else
				$mailer->setBodyText($this->getData('message_body'));

			$mailer->send();
			return $this;
		}

		return parent::addMessageToQueue();
	}

	public function send() {

		if (Mage::getStoreConfigFlag('maillog/general/enabled'))
			Mage::throwException('Hello! This is the Luigifab/Maillog module. Please disable the "core_email_queue_send_all" cron job. For more information read: https://www.luigifab.fr/openmage/maillog');

		return parent::send();
	}

	public function cleanQueue() {

		if (Mage::getStoreConfigFlag('maillog/general/enabled'))
			Mage::throwException('Hello! This is the Luigifab/Maillog module. Please disable the "core_email_queue_clean_up" cron job. For more information read: https://www.luigifab.fr/openmage/maillog');

		return parent::cleanQueue();
	}
}