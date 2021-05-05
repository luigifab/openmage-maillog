<?php
/**
 * Created S/04/04/2015
 * Updated V/16/04/2021
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://www.luigifab.fr/openmage/maillog
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

class Luigifab_Maillog_Model_Observer extends Luigifab_Maillog_Helper_Data {

	// EVENT admin_system_config_changed_section_maillog[_sync] (adminhtml)
	public function updateConfig() {

		// suppression des anciens emails
		// suppression des anciennes synchros
		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/maillog_clean_old_data/schedule/cron_expr', 'path');

		$check = @unserialize(Mage::getStoreConfig('maillog/general/special_config'), ['allowed_classes' => false]);
		if (!empty($check) && is_array($check)) {
			foreach ($check as $value) {
				if (is_numeric($value)) {
					$check = true;
					break;
				}
			}
		}

		if (($check === true) || !empty(Mage::getStoreConfig('maillog_sync/general/lifetime'))) {
			$config->setData('value', '30 2 * * '.Mage::getStoreConfig('general/locale/firstday'));
			$config->setData('path', 'crontab/jobs/maillog_clean_old_data/schedule/cron_expr');
			$config->save();
		}
		else {
			$config->delete();
		}

		// import des emails invalides (bounces)
		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/maillog_bounces_import/schedule/cron_expr', 'path');

		if (Mage::getStoreConfigFlag('maillog_sync/bounces/enabled')) {
			$value = Mage::getStoreConfig('maillog_sync/bounces/cron_expr');
			$config->setData('value', (mb_strlen($value) < 9) ? '30 2 * * *' : $value);
			$config->setData('path', 'crontab/jobs/maillog_bounces_import/schedule/cron_expr');
			$config->save();
		}
		else {
			$config->delete();
		}

		// import des emails désabonnés
		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/maillog_unsubscribers_import/schedule/cron_expr', 'path');

		if (Mage::getStoreConfigFlag('maillog_sync/unsubscribers/enabled')) {
			$value = Mage::getStoreConfig('maillog_sync/unsubscribers/cron_expr');
			$config->setData('value', (mb_strlen($value) < 9) ? '30 2 * * *' : $value);
			$config->setData('path', 'crontab/jobs/maillog_unsubscribers_import/schedule/cron_expr');
			$config->save();
		}
		else {
			$config->delete();
		}

		// rapport par email
		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/maillog_send_report/schedule/cron_expr', 'path');

		if (Mage::getStoreConfigFlag('maillog/email/enabled')) {

			// quotidien, tous les jours à 6h00 (quotidien/daily)
			// hebdomadaire, tous les lundi à 6h00 (hebdomadaire/weekly)
			// mensuel, chaque premier jour du mois à 6h00 (mensuel/monthly)
			$frequency = Mage::getStoreConfig('maillog/email/frequency');

			// minute hour day-of-month month-of-year day-of-week (Dimanche = 0, Lundi = 1...)
			// 0      6    1            *             *           => monthly
			// 0      6    *            *             0|1         => weekly
			// 0      6    *            *             *           => daily
			if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY)
				$config->setData('value', '0 6 1 * *');
			else if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY)
				$config->setData('value', '0 6 * * '.Mage::getStoreConfig('general/locale/firstday'));
			else
				$config->setData('value', '0 6 * * *');

			$config->setData('path', 'crontab/jobs/maillog_send_report/schedule/cron_expr');
			$config->save();

			// email de test
			if (!empty(Mage::app()->getRequest()->getPost('maillog_email_test')))
				$this->sendEmailReport();
			else if (!empty(Mage::app()->getRequest()->getPost('maillog_sync_email_test')))
				$this->sendEmailReport();
		}
		else {
			$config->delete();
		}

		Mage::getConfig()->reinit(); // très important
	}


	// CRON maillog_send_report
	public function sendEmailReport($cron = null) {

		$oldLocale = Mage::getSingleton('core/translate')->getLocale();
		$newLocale = Mage::app()->getStore()->isAdmin() ? $oldLocale : Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE);
		$locales   = [];

		// recherche des langues (@todo) et des emails
		$emails = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog/email/recipient_email')));
		foreach ($emails as $email) {
			if (!in_array($email, ['hello@example.org', 'hello@example.com', '']))
				$locales[$newLocale][] = $email;
		}

		// génère et envoie le rapport
		foreach ($locales as $locale => $recipients) {

			Mage::getSingleton('core/translate')->setLocale($locale)->init('adminhtml', true);
			$frequency = Mage::getStoreConfig('maillog/email/frequency');
			$errors = [];

			// recherche des dates
			if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
				$frequency = $this->_('monthly');
				$dates = $this->getDateRange('month');
			}
			else if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
				$frequency = $this->_('weekly');
				$dates = $this->getDateRange('week');
			}
			else {
				$frequency = $this->_('daily');
				$dates = $this->getDateRange('day');
			}

			// chargement des emails
			// optimisation maximale de manière à ne charger que le nécessaire (pas le contenu des emails)
			$emails = Mage::getResourceModel('maillog/email_collection');
			$emails->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['email_id', 'status', 'mail_subject', 'created_at']); // opt. maximale
			$emails->addFieldToFilter('created_at', [
				'datetime' => true,
				'from' => $dates['start']->toString(Zend_Date::RFC_3339),
				'to'   => $dates['end']->toString(Zend_Date::RFC_3339)
			]);
			$emails->setOrder('email_id', 'desc');

			// recherche des erreurs
			foreach ($emails as $email) {

				if (!in_array($email->getData('status'), ['error', 'pending']))
					continue;

				$errors[] = sprintf('(%d) %s / %s / %s',
					count($errors) + 1,
					'<a href="'.$this->getEmailUrl('adminhtml/maillog_history/view', ['id' => $email->getId()]).'" style="font-weight:700; color:#E41101; text-decoration:none;">'.$this->__('Email %d: %s', $email->getId(), $email->getSubject()).'</a>',
					$this->__('Created At: %s', $this->formatDate($email->getData('created_at'))),
					$this->__('Status: %s', $this->__(ucfirst($email->getData('status'))))
				);
			}

			$vars = [
				'frequency'            => $frequency,
				'date_period_from'     => $dates['start']->toString(Zend_Date::DATETIME_FULL),
				'date_period_to'       => $dates['end']->toString(Zend_Date::DATETIME_FULL),
				'total_email'          => count($emails),
				'total_pending'        => count($emails->getItemsByColumnValue('status', 'pending')),
				'total_sending'        => count($emails->getItemsByColumnValue('status', 'sending')),
				'total_sent'           => count($emails->getItemsByColumnValue('status', 'sent')),
				'total_read'           => count($emails->getItemsByColumnValue('status', 'read')),
				'total_error'          => count($emails->getItemsByColumnValue('status', 'error')),
				'total_unsent'         => count($emails->getItemsByColumnValue('status', 'notsent')),
				'total_bounce'         => count($emails->getItemsByColumnValue('status', 'bounce')),
				'error_list'           => implode('</li><li style="margin:0.8em 0 0.5em;">', $errors),
				'import_bounces'       => trim(strip_tags($this->getImportStatus('bounces', 'bounces'), '<br> <span>')),
				'import_unsubscribers' => trim(strip_tags($this->getImportStatus('unsubscribers', 'unsubscribers'), '<br> <span>')),
				'sync'                 => Mage::getStoreConfigFlag('maillog_sync/general/enabled')
			];

			// chargement des statistiques des emails et des synchronisations
			// optimisation maximale de manière à ne faire que des COUNT en base de données
			// ne recherche pas les données au dela de la durée de vie maximale (email et sync)
			$emails = Mage::getResourceModel('maillog/email_collection');
			$syncs  = Mage::getResourceModel('maillog/sync_collection');

			foreach (['week_' => true, 'month_' => false] as $key => $isWeek) {

				for ($i = 1; $i <= 14; $i++) {

					$variation = ($i > 1) && ($i < 14);
					$dates     = $this->getDateRange($isWeek ? 'week' : 'month', $i - 1);
					$oldDates  = $this->getDateRange($isWeek ? 'week' : 'month', $i - 0);
					$where     = ['from' => $dates['start']->toString(Zend_Date::RFC_3339),
						'to' => $dates['end']->toString(Zend_Date::RFC_3339)];
					$oldWhere  = ['from' => $oldDates['start']->toString(Zend_Date::RFC_3339),
						'to' => $oldDates['end']->toString(Zend_Date::RFC_3339)];

					// affiche les dates
					if ($isWeek) {
						$vars['items'][$i][$key.'period'] = $this->__('Week %d', $dates['start']->toString(Zend_Date::WEEK)).
							'<br><small>'.$dates['start']->toString(Zend_Date::DATE_SHORT).' - '.
							$dates['end']->toString(Zend_Date::DATE_SHORT).'</small>';
					}
					else {
						$vars['items'][$i][$key.'period'] = ucfirst($dates['start']->toString(Zend_Date::MONTH_NAME).' '.
							$dates['start']->toString(Zend_Date::YEAR)).
							'<br><small>'.$dates['start']->toString(Zend_Date::DATE_SHORT).' - '.
							$dates['end']->toString(Zend_Date::DATE_SHORT).'</small>';
					}

					// calcul les statistiques
					$tmp = $this->getNumbers($where, $oldWhere, $emails, $variation);
					$vars['items'][$i][$key.'total_email']       = array_shift($tmp);
					$vars['items'][$i][$key.'variation_email']   = array_shift($tmp);
					$tmp = $this->getNumbers($where, $oldWhere, $emails, $variation, ['in'  => ['sent', 'read']]);
					$vars['items'][$i][$key.'percent_sent']      = array_shift($tmp);
					$vars['items'][$i][$key.'variation_sent']    = array_shift($tmp);
					$tmp = $this->getNumbers($where, $oldWhere, $emails, $variation, ['in'  => ['sent', 'read']], 'read');
					$vars['items'][$i][$key.'percent_read']      = array_shift($tmp);
					$vars['items'][$i][$key.'variation_read']    = array_shift($tmp);

					// calcul les statistiques
					$tmp = $this->getNumbers($where, $oldWhere, $syncs, $variation);
					$vars['items'][$i][$key.'total_sync']        = array_shift($tmp);
					$vars['items'][$i][$key.'variation_sync']    = array_shift($tmp);
					$tmp = $this->getNumbers($where, $oldWhere, $syncs, $variation, 'success');
					$vars['items'][$i][$key.'percent_success']   = array_shift($tmp);
					$vars['items'][$i][$key.'variation_success'] = array_shift($tmp);
				}
			}

			$this->sendReportToRecipients($locale, $recipients, $vars);
		}

		Mage::getSingleton('core/translate')->setLocale($oldLocale)->init('adminhtml', true);
	}

	private function getNumbers(array $where, array $oldWhere, object $collection, bool $variation = true, $status1 = null, $status2 = null) {

		$nb3 = 0;
		$nb4 = 0;
		$where['datetime'] = true;
		if (!empty($oldWhere))
			$oldWhere['datetime'] = true;

		// avec des resets sinon le where est conservé malgré le clone
		// période actuelle
		if (empty($status1)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->getSize(); // totalité
		}
		else if (empty($status2)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->getSize(); // totalité
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$data->addFieldToFilter('status', $status1);
			$nb2 = $data->getSize(); // filtré
		}
		else {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$data->addFieldToFilter('status', $status1);
			$nb1 = $data->getSize(); // totalité
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$data->addFieldToFilter('status', $status2);
			$nb2 = $data->getSize(); // filtré
		}

		// avec des resets sinon le where est conservé malgré le clone
		// période précédente
		if ($variation && !empty($oldWhere)) {
			if (empty($status1)) {
				$data = clone $collection;
				$data->getSelect()->reset(Zend_Db_Select::WHERE);
				$data->addFieldToFilter('created_at', $oldWhere);
				$nb3 = $data->getSize(); // totalité
			}
			else if (empty($status2)) {
				$data = clone $collection;
				$data->getSelect()->reset(Zend_Db_Select::WHERE);
				$data->addFieldToFilter('created_at', $oldWhere);
				$nb3 = $data->getSize(); // totalité
				$data = clone $collection;
				$data->getSelect()->reset(Zend_Db_Select::WHERE);
				$data->addFieldToFilter('created_at', $oldWhere);
				$data->addFieldToFilter('status', $status1);
				$nb4 = $data->getSize(); // filtré
			}
			else {
				$data = clone $collection;
				$data->getSelect()->reset(Zend_Db_Select::WHERE);
				$data->addFieldToFilter('created_at', $oldWhere);
				$data->addFieldToFilter('status', $status1);
				$nb3 = $data->getSize(); // totalité
				$data = clone $collection;
				$data->getSelect()->reset(Zend_Db_Select::WHERE);
				$data->addFieldToFilter('created_at', $oldWhere);
				$data->addFieldToFilter('status', $status2);
				$nb4 = $data->getSize(); // filtré
			}
		}

		// nombre d'email/sync période actuelle, variation par rapport à la période précédente
		if (empty($status1))
			return [$nb1, ($nb3 != 0) ? floor(($nb1 - $nb3) / $nb3 * 100) : ($variation ? 0 : '')];

		// pourcentage période actuelle, variation par rapport à la période précédente
		$pct1 = ($nb1 > 0) ? ($nb2 * 100) / $nb1 : 0;
		$pct2 = ($nb3 > 0) ? ($nb4 * 100) / $nb3 : 0;
		return [floor($pct1), ($pct2 != 0) ? floor(($pct1 - $pct2) / $pct2 * 100) : ($variation ? 0 : '')];
	}

	private function getDateRange(string $range, int $coeff = 1) {

		$dateStart = Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->setSecond(0);
		$dateEnd   = Mage::getSingleton('core/locale')->date()->setHour(23)->setMinute(59)->setSecond(59);

		// de 1 (pour Lundi) à 7 (pour Dimanche)
		// permet d'obtenir des semaines du lundi au dimanche
		$day = $dateStart->toString(Zend_Date::WEEKDAY_8601) - 1;

		if ($range == 'month') {
			$dateStart->setDay(3)->subMonth(1 * $coeff)->setDay(1);
			$dateEnd->setDay(3)->subMonth(1 * $coeff)->setDay($dateEnd->toString(Zend_Date::MONTH_DAYS));
		}
		else if ($range == 'week') {
			$dateStart->subDay($day + 7 * $coeff);
			$dateEnd->subDay($day + 7 * $coeff - 6);
		}
		else if ($range == 'day') {
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		return ['start' => $dateStart, 'end' => $dateEnd];
	}

	private function getEmailUrl(string $url, array $params = []) {

		if (Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_USE_REWRITES))
			return preg_replace('#/[^/]+\.php\d*/#', '/', Mage::helper('adminhtml')->getUrl($url, $params));
		else
			return preg_replace('#/[^/]+\.php(\d*)/#', '/index.php$1/', Mage::helper('adminhtml')->getUrl($url, $params));
	}

	private function sendReportToRecipients(string $locale, array $emails, array $vars = []) {

		$vars['config'] = $this->getEmailUrl('adminhtml/system/config');
		$vars['config'] = mb_substr($vars['config'], 0, mb_strrpos($vars['config'], '/system/config'));

		foreach ($emails as $email) {

			$sender   = Mage::getStoreConfig('maillog/email/sender_email_identity');
			$template = Mage::getModel('core/email_template');

			if (!empty($_GET) || !empty($_POST)) {
				$template->getMail()->createAttachment(
					gzencode(file_get_contents(Mage::getModuleDir('etc', 'Luigifab_Maillog').'/tidy.conf'), 9),
					'application/x-gzip',
					Zend_Mime::DISPOSITION_ATTACHMENT,
					Zend_Mime::ENCODING_BASE64,
					'tidy.gz'
				);
			}

			$template->setSentSuccess(false);
			$template->setDesignConfig(['store' => null]);
			$template->loadDefault('maillog_email_template', $locale);
			$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name'));
			$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email'));
			$template->setSentSuccess($template->send($email, null, $vars));
			//exit($template->getProcessedTemplate($vars));

			if (!$template->getSentSuccess())
				Mage::throwException($this->__('Can not send the report by email to %s.', $email));
		}
	}


	// EVENT customer_delete_after (global)
	public function customerDeleteSync(Varien_Event_Observer $observer) {

		$customer = $observer->getData('customer');

		Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('mail_recipients', ['like' => '%<'.$customer->getData('email').'>%'])
			->deleteAll();

		Mage::getResourceModel('maillog/sync_collection')
			->addFieldToFilter('action', ['like' => '%:customer:'.$customer->getId().':%'])
			->deleteAll();

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true))
			$this->sendSync($customer, 'customer', 'email', 'delete');
	}

	// EVENT customer_login (frontend)
	public function customerLoginSync(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true))
			$this->sendSync($observer->getData('customer'), 'customer', 'email', 'update');
	}

	// EVENT customer_address_save_after (frontend)
	public function addressSaveSync(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true))
			$this->sendSync($observer->getData('customer_address')->getCustomer(), 'customer', 'email', 'update');
	}

	// EVENT customer_save_commit_after (global)
	public function customerSaveSync(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true))
			$this->sendSync($observer->getData('customer'), 'customer', 'email', 'update');
	}

	// EVENT newsletter_subscriber_save_after (global)
	public function subscriberSaveSync(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true))
			$this->sendSync($observer->getData('subscriber'), 'subscriber', 'subscriber_email', 'update');
	}

	// EVENT newsletter_subscriber_delete_after (global)
	// si le client existe on met à jour sinon on supprime
	public function subscriberDeleteSync(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true)) {

			$subscriber = $observer->getData('subscriber');
			$action     = empty($subscriber->getData('customer_id')) ? 'delete' : 'update';

			if ($action == 'delete') {
				$syncs = Mage::getResourceModel('maillog/sync_collection');
				$syncs->addFieldToFilter('action', ['like' => '%:subscriber:'.$subscriber->getId().':%']);
				foreach ($syncs as $sync)
					$sync->setData('request', null)->save();
			}

			$this->sendSync($subscriber, 'subscriber', 'subscriber_email', $action);
		}
	}

	// EVENT sales_order_invoice_save_commit_after (global)
	// si le client existe on met à jour
	public function orderInvoiceSync(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_sync/general/enabled') && (Mage::registry('maillog_no_sync') !== true)) {
			$customer = Mage::getModel('customer/customer')->load($observer->getData('invoice')->getOrder()->getData('customer_id'));
			if (!empty($customer->getId()))
				$this->sendSync($customer, 'customer', 'email', 'update');
		}
	}


	// EVENT adminhtml_customer_prepare_save (adminhtml)
	// génère le store_id du client lors de la création d'un client (sinon c'est 0)
	// actif même si le module n'est pas activé
	public function setCustomerStoreId(Varien_Event_Observer $observer) {

		$customer = $observer->getData('customer');

		if (empty($customer->getId())) {
			if (!empty($customer->getData('sendemail_store_id')))
				$customer->setData('store_id', $customer->getData('sendemail_store_id'));
			else if (!empty($customer->getData('website_id')))
				$customer->setData('store_id', Mage::app()->getWebsite($customer->getData('website_id'))->getDefaultStore()->getId());
			else
				$customer->setData('store_id', Mage::app()->getDefaultStoreView()->getId());
		}
	}

	// EVENT newsletter_subscriber_save_before (adminhtml)
	// génère le store_id de l'abonné lors de l'enregistrement d'un abonné
	// se base sur le store_id du client
	// actif même si le module n'est pas activé
	public function setSubscriberStoreId(Varien_Event_Observer $observer) {

		$subscriber = $observer->getData('subscriber');
		$customer   = Mage::registry('current_customer');

		if (is_object($customer) && ($subscriber->getStoreId() != $customer->getStoreId()))
			$subscriber->setData('store_id', $customer->getStoreId());

		if (is_object($customer) && ($subscriber->getData('subscriber_email') != $customer->getOrigData('email')))
			$subscriber->setOrigData('subscriber_email', $customer->getOrigData('email'));
	}


	// CRON maillog_clean_old_data
	// réduit l'historique des synchronisations (qui est configuré en minutes) et des emails (qui est configuré en mois)
	public function cleanOldData($cron = null) {

		$msg = [];
		$cnt = 0;

		$val = Mage::getStoreConfig('maillog_sync/general/lifetime');
		if (!empty($val) && is_numeric($val)) {

			$syncs = Mage::getResourceModel('maillog/sync_collection');
			$syncs->addFieldToFilter('status', ['eq' => 'success']);
			$syncs->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$val.' MINUTE)')]);
			$cnt += $syncs->getSize();
			$syncs->deleteAll();

			$syncs = Mage::getResourceModel('maillog/sync_collection');
			$syncs->addFieldToFilter('status', ['neq' => 'success']);
			$syncs->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.(3 * $val).' MINUTE)')]);
			$cnt += $syncs->getSize();
			$syncs->deleteAll();

			$msg[] = 'Remove successful synchronizations after '.($val / 60 / 24).' days';
			$msg[] = empty($cnt) ? ' → no items to remove' : ' → '.$cnt.' item(s) removed';
			$msg[] = '';
		}

		$config = @unserialize(Mage::getStoreConfig('maillog/general/special_config'), ['allowed_classes' => false]);
		if (!empty($config) && is_array($config)) {

			foreach ($config as $key => $months) {

				if (is_numeric($months) && ($months >= 2)) {

					$cut    = mb_stripos($key, '_');
					$type   = mb_substr($key, 0, $cut);
					$action = mb_substr($key, $cut + 1);

					$emails = Mage::getResourceModel('maillog/email_collection');
					$emails->addFieldToFilter('deleted', ['neq' => 1]);
					$emails->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr(
						'DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$months.' MONTH)')]);

					// tous les emails
					if ($key == 'all_data') {
						$emails->addFieldToFilter('deleted', 0);
						$msg[] = 'Remove content and attachments for '.$type.' emails after '.$months.' months';
					}
					else if ($key == 'all_all') {
						$msg[] = 'Remove all data for '.$type.' emails after '.$months.' months';
					}
					// les emails sans type
					else if ($key == 'without_data') {
						$emails->addFieldToFilter('deleted', 0);
						$emails->addFieldToFilter('type', '--');
						$msg[] = 'Remove content and attachments for '.$type.' emails after '.$months.' months';
					}
					else if ($key == 'without_all') {
						$emails->addFieldToFilter('type', '--');
						$msg[] = 'Remove all data for '.$type.' emails after '.$months.' months';
					}
					// les emails avec un type
					else if ($action == 'data') {
						$emails->addFieldToFilter('deleted', 0);
						$emails->addFieldToFilter('type', $type);
						$msg[] = 'Remove content and attachments for '.$type.' emails after '.$months.' months';
					}
					else if ($action == 'all') {
						$emails->addFieldToFilter('type', $type);
						$msg[] = 'Remove all data for '.$type.' emails after '.$months.' months';
					}

					// action
					// supprime en partie l'email ou tout l'email
					$ids = $emails->getAllIds();
					$ids = (count($ids) > 100) ? implode(', ', array_slice($ids, 0, 100)).'...' : implode(', ', $ids);

					$msg[] = empty($cnt = $emails->getSize()) ? ' → no items to remove' : ' → '.$cnt.' item(s) removed ('.$ids.')';
					$msg[] = '';

					if (!empty($cnt)) {
						if ($action == 'all') {
							$emails->deleteAll();
						}
						else if ($action == 'data') {
							foreach ($emails as $email) {
								$email->setData('encoded_mail_recipients', null);
								$email->setData('encoded_mail_subject', null);
								$email->setData('mail_body', null);
								$email->setData('mail_header', null);
								$email->setData('mail_parameters', null);
								$email->setData('mail_parts', null);
								$email->setData('deleted', 1);
								$email->save();
							}
						}
					}
				}
			}
		}

		if (is_object($cron))
			$cron->setMessages(implode("\n", array_slice($msg, 0, -1)));
	}

	// CRON maillog_bounces_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message de la tâche cron et dans le fichier status.dat
	public function bouncesFileImport($cron = null, $source = null) {

		Mage::register('maillog_no_sync', true);
		$diff = ['started_at' => date('Y-m-d H:i:s'), 'errors' => []];

		try {
			$folder = Mage::getStoreConfig('maillog_sync/bounces/directory');
			$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = Mage::getStoreConfig('maillog_sync/bounces/format');
			$source = $this->todayFile($folder, $type = mb_substr($config, 0, 3));

			$newItems = $this->dataFromFile($folder, $source, $config);

			$oldItems = Mage::getResourceModel('customer/customer_collection');
			$oldItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['email']); // optimisation maximale
			$oldItems->addAttributeToFilter('is_bounce', 2); // 1/No 2/Yes 3/Yes-forced 4/No-forced

			$this->updateCustomersDatabase($newItems, $oldItems->getColumnValues('email'), $diff);
			$this->moveFiles($folder, $source, $type);
			$this->writeLog($folder, $source, $diff, $cron);

			Mage::unregister('maillog_no_sync');
		}
		catch (Throwable $e) {

			$error = empty($diff['errors']) ? $e->getMessage() : implode("\n", $diff['errors']);
			$this->writeLog($folder, $source, $error, $cron);

			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}
	}

	// CRON maillog_unsubscribers_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message de la tâche cron et dans le fichier status.dat
	public function unsubscribersFileImport($cron = null, $source = null) {

		Mage::register('maillog_no_sync', true);
		$diff = ['started_at' => date('Y-m-d H:i:s'), 'errors' => []];

		try {
			$folder = Mage::getStoreConfig('maillog_sync/unsubscribers/directory');
			$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = Mage::getStoreConfig('maillog_sync/unsubscribers/format');
			$source = $this->todayFile($folder, $type = mb_substr($config, 0, 3));

			$newItems = $this->dataFromFile($folder, $source, $config);

			$oldItems = Mage::getResourceModel('newsletter/subscriber_collection');
			$oldItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['subscriber_email']); // optimisation maximale
			$oldItems->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);

			$this->updateUnsubscribersDatabase($newItems, $oldItems->getColumnValues('subscriber_email'), $diff);
			$this->moveFiles($folder, $source, $type);
			$this->writeLog($folder, $source, $diff, $cron);

			Mage::unregister('maillog_no_sync');
		}
		catch (Throwable $e) {

			$error = empty($diff['errors']) ? $e->getMessage() : implode("\n", $diff['errors']);
			$this->writeLog($folder, $source, $error, $cron);

			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}
	}


	// 7 exceptions - c'est ici que tout se joue car si tout va bien nous avons un fichier et un dossier qui sont accessibles et modifiables
	// dossier de base : inexistant, non accessible en lecture, non accessible en écriture, vide
	// fichier : non accessible en lecture, non accessible en écriture, trop vieux
	private function todayFile(string $folder, string $type) {

		// vérifications du dossier
		if (!is_dir($folder))
			Mage::throwException('Sorry, the directory "'.$folder.'" is not set.');
		if (!is_readable($folder))
			Mage::throwException('Sorry, the directory "'.$folder.'" is not readable.');
		if (!is_writable($folder))
			Mage::throwException('Sorry, the directory "'.$folder.'" is not writable.');

		// recherche des fichiers
		// utilise un tableau pour pouvoir trier par date
		$allfiles = glob($folder.'*.'.$type);
		$files = [];

		foreach ($allfiles as $file)
			$files[(int) filemtime($file)] = basename($file); // (yes)

		if (empty($files))
			Mage::throwException('Sorry, there is no file in directory "'.$folder.'".');

		// du plus grand au plus petit, donc du plus récent au plus ancien
		// de manière à avoir le fichier le plus récent en premier car on souhaite traiter le fichier du jour uniquement
		krsort($files);
		$time = key($files);
		$file = current($files);

		// vérifications du fichier
		// pour la date, seul compte le jour
		if (!is_readable($folder.$file))
			Mage::throwException('Sorry, the file "'.$folder.$file.'" is not readable.');
		if (!is_writable($folder.$file))
			Mage::throwException('Sorry, the file "'.$folder.$file.'" is not writable.');
		if ($time < Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->getTimestamp())
			Mage::throwException('Sorry, the file "'.$folder.$file.'" is too old for today.');

		return $file;
	}

	// 1 exception - le fichier n'est pas en utf-8
	// lecture du fichier à importer en supprimant l'éventuel marqueur bom (en fonction de la configuration)
	// mise à jour de la base de données (ne touche pas à ce qui ne change pas - ajoute/supprime/modifie)
	// déplace et compresse les fichiers (base/done/skip)
	// enregistre le log final
	private function dataFromFile(string $folder, string $source, string $config) {

		$type = mb_substr($config, 0, 3);

		// type=txt
		// type=tsv2"  pour type=tsv delim=→ colum=2 separ="
		// type=csv;2" pour type=csv delim=; colum=2 separ="
		if ($type == 'csv') {
			$delim = mb_substr($config, 3, 1);
			$colum = mb_substr($config, 4, 1);
			$separ = mb_substr($config, 5, 1);
		}
		else if ($type == 'tsv') {
			$delim = '→';
			$colum = mb_substr($config, 3, 1);
			$separ = mb_substr($config, 4, 1);
		}

		$items = [];
		$colum = ($colum > 1) ? $colum - 1 : 1;
		$lines = trim(str_replace("\xEF\xBB\xBF", '', file_get_contents($folder.$source)));

		if (mb_detect_encoding($lines, 'utf-8', true) === false)
			Mage::throwException('Sorry, the file "'.$folder.$source.'" is not an utf-8 file.');

		$lines = explode("\n", $lines);
		foreach ($lines as $line) {

			$line = trim($line);

			if (mb_strlen($line) > 5) {
				if ($type == 'csv') {
					$delim = ($delim == '→') ? "\t" : $delim;
					$data  = (array) explode($delim, $line); // (yes)
					if (!empty($data[$colum]) && (mb_stripos($data[$colum], '@') !== false))
						$items[] = trim(str_replace($separ, '', $data[$colum]));
				}
				else if (($type == 'txt') && (mb_stripos($line, '@') !== false)) {
					$items[] = trim($line);
				}
			}
		}

		return $items;
	}

	private function updateCustomersDatabase(array $newItems, array $oldItems, array &$diff) {

		$diff['oldItems']    = count($oldItems);
		$diff['newItems']    = count($newItems);
		$diff['invalidated'] = [];
		$diff['validated']   = [];

		// traitement des adresses emails AJOUTÉES
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
		// une adresse email AJOUTÉE = une adresse email devenue invalide
		// 0/No 1/Yes 2/Yes-forced-admin 3/No-forced-admin 4/No-forced-customer
		$allEmails     = array_diff($newItems, $oldItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('customer/customer_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['entity_id', 'entity_type_id', 'email']); // optimisation maximale
			$items->addAttributeToSelect('is_bounce');
			// non, car cela génère un INNER JOIN, et donc cela merde si l'attribut n'a pas de ligne dans customer_entity_int
			//$items->addAttributeToFilter('is_bounce', ['nin' => [1, 2, 3, 4]]); // donc 0/No ou null
			$items->addAttributeToFilter('email', ['in' => $emails]);

			foreach ($emails as $email) {
				try {
					$customer = $items->getItemByColumnValue('email', $email);

					if (!empty($customer) && empty($customer->getData('is_bounce'))) { // n'est PAS forcément 0/No ou null

						$customer->setData('is_bounce', 1); // 1 pour Yes
						$customer->getResource()->saveAttribute($customer, 'is_bounce');
						//$customer->save();

						$diff['invalidated'][] = $email;
					}
				}
				catch (Throwable $e) {
					$diff['errors'][] = $email.' - '.$e->getMessage();
				}
			}
		}

		if (Mage::getStoreConfigFlag('maillog_sync/bounces/subscribe')) {

			// traitement des adresses emails SUPPRIMÉES
			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			// une adresse email SUPPRIMÉE = une adresse email devenue valide
			// 0/No 1/Yes 2/Yes-forced-admin 3/No-forced-admin 4/No-forced-customer
			$allEmails     = array_diff($oldItems, $newItems);
			$chunkedEmails = array_chunk($allEmails, 1000);

			foreach ($chunkedEmails as $emails) {

				$items = Mage::getResourceModel('customer/customer_collection');
				$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['entity_id', 'entity_type_id', 'email']); // opt. maximale
				$items->addAttributeToSelect('is_bounce');
				$items->addAttributeToFilter('is_bounce', ['nin' => [0, 2, 3, 4]]); // donc 1/Yes ou null
				$items->addAttributeToFilter('email', ['in' => $emails]);

				foreach ($emails as $email) {
					try {
						$customer = $items->getItemByColumnValue('email', $email);

						if (!empty($customer)) { // est forcément 1/Yes ou null

							$customer->setData('is_bounce', 0); // 0 pour No
							$customer->getResource()->saveAttribute($customer, 'is_bounce');
							//$customer->save();

							$diff['validated'][] = $email;
						}
					}
					catch (Throwable $e) {
						$diff['errors'][] = $email.' - '.$e->getMessage();
					}
				}
			}
		}
	}

	private function updateUnsubscribersDatabase(array $newItems, array $oldItems, array &$diff) {

		$diff['oldItems']     = count($oldItems);
		$diff['newItems']     = count($newItems);
		$diff['unsubscribed'] = [];
		$diff['subscribed']   = [];

		// traitement des adresses emails AJOUTÉES
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
		// une adresse email AJOUTÉE = une adresse désinscrite de la newsletter (STATUS_UNSUBSCRIBED)
		$allEmails = array_diff($newItems, $oldItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('newsletter/subscriber_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['subscriber_id', 'subscriber_email']); // optimisation maximale
			// oui, car pour être inscrit, il faut forcément une ligne dans newsletter_subscriber
			$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
			$items->addFieldToFilter('subscriber_email', ['in' => $emails]);

			foreach ($emails as $email) {
				try {
					$subscriber = $items->getItemByColumnValue('subscriber_email', $email);

					if (!empty($subscriber)) { // est forcément STATUS_SUBSCRIBED

						$subscriber->setData('change_status_at', date('Y-m-d H:i:s'));
						$subscriber->setData('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
						$subscriber->getResource()->save($subscriber);
						//$subscriber->save();

						$diff['unsubscribed'][] = $email;
					}
				}
				catch (Throwable $e) {
					$diff['errors'][] = $email.' - '.$e->getMessage();
				}
			}
		}

		if (Mage::getStoreConfigFlag('maillog_sync/unsubscribers/subscribe')) {

			// traitement des adresses emails SUPPRIMÉES
			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			// une adresse email SUPPRIMÉE = une adresse inscrite à la newsletter (STATUS_SUBSCRIBED)
			$allEmails = array_diff($oldItems, $newItems);
			$chunkedEmails = array_chunk($allEmails, 1000);

			foreach ($chunkedEmails as $emails) {

				$items = Mage::getResourceModel('newsletter/subscriber_collection');
				$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['subscriber_id', 'subscriber_email']);  // opt. maximale
				$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
				$items->addFieldToFilter('subscriber_email', ['in' => $emails]);

				foreach ($emails as $email) {
					try {
						$subscriber = $items->getItemByColumnValue('subscriber_email', $email);

						if (!empty($subscriber)) { // est forcément STATUS_UNSUBSCRIBED

							$subscriber->setData('change_status_at', date('Y-m-d H:i:s'));
							$subscriber->setData('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
							$subscriber->getResource()->save($subscriber);
							//$subscriber->save();

							$diff['subscribed'][] = $email;
						}
					}
					catch (Throwable $e) {
						$diff['errors'][] = $email.' - '.$e->getMessage();
					}
				}
			}
		}
	}

	private function moveFiles(string $folder, string &$source, string $type) {

		$uniq = 1;
		$date = Mage::getSingleton('core/locale')->date();
		$donedir = $folder.'done/'.$date->toString('YMM').'/';
		$skipdir = $folder.'skip/'.$date->toString('YMM').'/';

		if (!is_dir($donedir))
			mkdir($donedir, 0755, true);
		if (!is_dir($skipdir))
			mkdir($skipdir, 0755, true);

		// déplace et compresse le fichier traité
		$name = $date->toString('YMMdd-HHmmss').'.'.$type;
		rename($folder.$source, $donedir.$name.'.gz');
		file_put_contents($donedir.$name.'.gz', gzencode(file_get_contents($donedir.$name.'.gz'), 9));
		$source = $donedir.$name.'.gz';

		// déplace et compresse les fichiers ignorés
		// reste silencieux en cas d'erreur (car si le fichier n'est pas déplaçable, le fichier ne sera jamais traité)
		$files = glob($folder.'*.'.$type);
		foreach ($files as $file) {

			$file = basename($file);
			$name = $date->setTimestamp(filemtime($folder.$file))->toString('YMMdd-HHmmss').'-'.str_pad($uniq++, 3, '0', STR_PAD_LEFT).'.'.$type;

			@rename($folder.$file, $skipdir.$name.'.gz');
			if (is_file($skipdir.$name.'.gz') && is_writable($skipdir.$name.'.gz'))
				file_put_contents($skipdir.$name.'.gz', gzencode(file_get_contents($skipdir.$name.'.gz'), 9));
		}

		// supprime le dossier des fichiers ignorés si celui-ci est VIDE
		@rmdir($skipdir);
	}

	private function writeLog(string $folder, string $source, $diff, $cron = null) {

		$diff = is_string($diff) ? ['started_at' => date('Y-m-d H:i:s'), 'exception' => $diff] : $diff;

		// pour le message du cron
		if (is_object($cron)) {
			$text = str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '', print_r($diff, true)));
			$cron->setData('messages', $text);
			$diff['cron'] = $cron->getId();
		}

		// pour le status.dat
		if (!empty($source)) {
			$diff['size'] = mb_strlen(gzdecode(file_get_contents($source)));
			$diff['file'] = basename($source);
		}

		// pour le status.dat
		// n'affiche pas les adresses, uniquement les nombres d'adresses
		if (isset($diff['invalidated'])) {
			$diff['invalidated']  = empty($diff['invalidated'])  ? 0 : count($diff['invalidated']);
			$diff['validated']    = empty($diff['validated'])    ? 0 : count($diff['validated']);
		}

		if (isset($diff['unsubscribed'])) {
			$diff['unsubscribed'] = empty($diff['unsubscribed']) ? 0 : count($diff['unsubscribed']);
			$diff['subscribed']   = empty($diff['subscribed'])   ? 0 : count($diff['subscribed']);
		}

		$diff['errors']      = empty($diff['errors']) ? 0 : count($diff['errors']);
		$diff['finished_at'] = date('Y-m-d H:i:s');

		if (!is_dir($folder))
			mkdir($folder, 0755, true);

		file_put_contents($folder.'status.dat', json_encode($diff));
	}
}