<?php
/**
 * Created D/05/04/2015
 * Updated D/05/04/2015
 * Version 1
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

class Luigifab_Maillog_Model_Rewrite_Queue extends Mage_Core_Model_Email_Queue {

	public function addMessageToQueue() {

		// ci-dessous une partie de la méthode Mage_Core_Model_Email_Queue::send()
		// cela permet d'envoyer immédiatement l'email et donc de désactiver la fil d'attente de Magento 1.9.1.0+
		if (Mage::getStoreConfig('maillog/general/enabled') === '1') {

			$parameters = new Varien_Object($this->getMessageParameters());
			if ($parameters->getReturnPathEmail() !== null) {
				$mailTransport = new Zend_Mail_Transport_Sendmail('-f'.$parameters->getReturnPathEmail());
				Zend_Mail::setDefaultTransport($mailTransport);
			}

			$mailer = new Zend_Mail('utf-8');

			foreach ($this->getRecipients() as $recipient) {
				list($email, $name, $type) = $recipient;
				switch ($type) {
					case self::EMAIL_TYPE_BCC:
						$mailer->addBcc($email, '=?utf-8?B?'.base64_encode($name).'?=');
						break;
					case self::EMAIL_TYPE_TO:
					case self::EMAIL_TYPE_CC:
					default:
						$mailer->addTo($email, '=?utf-8?B?'.base64_encode($name).'?=');
						break;
				}
			}

			if ($parameters->getIsPlain())
				$mailer->setBodyText($this->getMessageBody());
			else
				$mailer->setBodyHTML($this->getMessageBody());

			$mailer->setSubject('=?utf-8?B?'.base64_encode($parameters->getSubject()).'?=');
			$mailer->setFrom($parameters->getFromEmail(), $parameters->getFromName());

			if ($parameters->getReplyTo() !== null)
				$mailer->setReplyTo($parameters->getReplyTo());
			if ($parameters->getReturnTo() !== null)
				$mailer->setReturnPath($parameters->getReturnTo());

			$mailer->send();

			return $this;
		}
		else {
			return parent::addMessageToQueue();
		}
	}

	public function send() {

		if (Mage::getStoreConfig('maillog/general/enabled') === '1')
			Mage::throwException('Hello! This is the Luigifab/Maillog module. Please disable the "core_email_queue_send_all" cron job. For more informations, read: https://redmine.luigifab.info/projects/magento/wiki/maillog');
		else
			return parent::send();
	}

	public function cleanQueue() {

		if (Mage::getStoreConfig('maillog/general/enabled') === '1')
			Mage::throwException('Hello! This is the Luigifab/Maillog module. Please disable the "core_email_queue_clean_up" cron job. For more informations, read: https://redmine.luigifab.info/projects/magento/wiki/maillog');
		else
			return parent::cleanQueue();
	}
}