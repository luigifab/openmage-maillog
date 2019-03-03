<?php
/**
 * Created D/05/04/2015
 * Updated M/15/01/2019
 *
 * Copyright 2015-2019 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

class Luigifab_Maillog_Model_Rewrite_Queue extends Mage_Core_Model_Email_Queue {

	public function addMessageToQueue() {

		// ci-dessous une partie de la méthode Mage_Core_Model_Email_Queue::send()
		// cela permet d'envoyer immédiatement l'email et donc de désactiver la file d'attente de Magento 1.9.1.0 et +
		if (Mage::getStoreConfigFlag('maillog/general/enabled')) {

			$parameters = $this->getMessageParameters();

			if (!empty($parameters['return_path_email'])) {
				$mailTransport = new Zend_Mail_Transport_Sendmail('-f'.$parameters['return_path_email']);
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

			if (!empty($parameters['is_plain']) && $parameters['is_plain'])
				$mailer->setBodyText($this->getData('message_body'));
			else
				$mailer->setBodyHTML($this->getData('message_body'));

			$mailer->setSubject('=?utf-8?B?'.base64_encode($parameters['subject']).'?=');
			$mailer->setFrom($parameters['from_email'], !empty($parameters['from_name']) ? $parameters['from_name'] : null);

			if (!empty($parameters['reply_to']))
				$mailer->setReplyTo($parameters['reply_to']);
			if (!empty($parameters['return_to']))
				$mailer->setReturnPath($parameters['return_to']);

			$mailer->send();
			return $this;
		}
		else {
			return parent::addMessageToQueue();
		}
	}

	public function send() {

		if (Mage::getStoreConfigFlag('maillog/general/enabled'))
			Mage::throwException('Hello! This is the Luigifab/Maillog module. Please disable the "core_email_queue_send_all" cron job. For more information read: https://www.luigifab.fr/magento/maillog');
		else
			return parent::send();
	}

	public function cleanQueue() {

		if (Mage::getStoreConfigFlag('maillog/general/enabled'))
			Mage::throwException('Hello! This is the Luigifab/Maillog module. Please disable the "core_email_queue_clean_up" cron job. For more information read: https://www.luigifab.fr/magento/maillog');
		else
			return parent::cleanQueue();
	}

	public function specialCheckRewrite() {
		return true;
	}
}