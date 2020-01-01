<?php
/**
 * Created L/27/11/2017
 * Updated D/13/10/2019
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

class Luigifab_Maillog_Model_Rewrite_Subscriber extends Mage_Newsletter_Model_Subscriber {

	public function loadByEmail($subscriberEmail, $storeId = 0) {
		$this->addData($this->getResource()->loadByEmail($subscriberEmail, $storeId));
		return $this;
	}

	public function sendConfirmationRequestEmail() {

		$storeId = $this->getStoreId();
		$layout  = Mage::getStoreConfig(self::XML_PATH_CONFIRM_EMAIL_TEMPLATE, $storeId);
		$sender  = Mage::getStoreConfig(self::XML_PATH_CONFIRM_EMAIL_IDENTITY, $storeId);

		if (empty($layout) || empty($sender) || $this->getImportMode())
			return $this;

		$template = Mage::getModel('core/email_template');
		$template->setSentSuccess(false);
		$template->setDesignConfig(['store' => null]);
		$template->loadDefault($layout, Mage::getStoreConfig('general/locale/code', $storeId));
		$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name', $storeId));
		$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email', $storeId));
		$template->setSentSuccess($template->send($this->getEmail(), $this->getName(), ['subscriber' => $this]));

		return $this;
	}

	public function sendConfirmationSuccessEmail() {

		$storeId = $this->getStoreId();
		$layout  = Mage::getStoreConfig(self::XML_PATH_SUCCESS_EMAIL_TEMPLATE, $storeId);
		$sender  = Mage::getStoreConfig(self::XML_PATH_SUCCESS_EMAIL_IDENTITY, $storeId);

		if (!Mage::getStoreConfigFlag('newsletter/subscription/success_send', $storeId))
			return $this;
		if (empty($layout) || empty($sender) || $this->getImportMode())
			return $this;

		$template = Mage::getModel('core/email_template');
		$template->setSentSuccess(false);
		$template->setDesignConfig(['store' => null]);
		$template->loadDefault($layout, Mage::getStoreConfig('general/locale/code', $storeId));
		$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name', $storeId));
		$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email', $storeId));
		$template->setSentSuccess($template->send($this->getEmail(), $this->getName(), ['subscriber' => $this]));

		return $this;
	}

	public function sendUnsubscriptionEmail() {

		$storeId = $this->getStoreId();
		$layout  = Mage::getStoreConfig(self::XML_PATH_UNSUBSCRIBE_EMAIL_TEMPLATE, $storeId);
		$sender  = Mage::getStoreConfig(self::XML_PATH_UNSUBSCRIBE_EMAIL_IDENTITY, $storeId);

		if (!Mage::getStoreConfigFlag('newsletter/subscription/un_send', $storeId))
			return $this;
		if (empty($layout) || empty($sender) || $this->getImportMode())
			return $this;

		$template = Mage::getModel('core/email_template');
		$template->setSentSuccess(false);
		$template->setDesignConfig(['store' => null]);
		$template->loadDefault($layout, Mage::getStoreConfig('general/locale/code', $storeId));
		$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name', $storeId));
		$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email', $storeId));
		$template->setSentSuccess($template->send($this->getEmail(), $this->getName(), ['subscriber' => $this]));

		return $this;
	}
}