<?php
/**
 * Created S/04/04/2015
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

class Luigifab_Maillog_Model_Observer extends Luigifab_Maillog_Helper_Data {

	// EVENT admin_system_config_changed_section_maillog
	public function updateConfig() {

		try {
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
				if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY)
					$config->setValue('0 6 1 * *');
				else if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY)
					$config->setValue('0 6 * * '.Mage::getStoreConfig('general/locale/firstday'));
				else
					$config->setValue('0 6 * * *');

				$config->setPath('crontab/jobs/maillog_send_report/schedule/cron_expr');
				$config->save();

				// email de test
				// s'il n'a pas déjà été envoyé dans la dernière heure (3600 secondes)
				// ou si le cookie maillog_print_email est présent, et ce, quoi qu'il arrive
				$cookie = (Mage::getSingleton('core/cookie')->get('maillog_print_email') === 'yes') ? true : false;
				$session = Mage::getSingleton('admin/session')->getLastMaillogReport();
				$timestamp = Mage::getSingleton('core/date')->timestamp();

				if (is_null($session) || ($timestamp > ($session + 3600)) || $cookie) {
					$this->sendEmailReport();
					Mage::getSingleton('admin/session')->setLastMaillogReport($timestamp);
				}
			}
			else {
				$config->delete();
			}

			// import des emails invalides (bounces)
			$config = Mage::getModel('core/config_data');
			$config->load('crontab/jobs/maillog_bounces_import/schedule/cron_expr', 'path');

			if (Mage::getStoreConfigFlag('maillog/email/enabled') && Mage::getStoreConfigFlag('maillog/bounces/enabled')) {
				$value = trim(Mage::getStoreConfig('maillog/bounces/cron_expr'));
				$config->setValue((strlen($value) < 9) ? '30 2 * * *' : $value);
				$config->setPath('crontab/jobs/maillog_bounces_import/schedule/cron_expr');
				$config->save();
			}
			else {
				$config->delete();
			}

			// import des emails désabonnés
			$config = Mage::getModel('core/config_data');
			$config->load('crontab/jobs/maillog_unsubscribers_import/schedule/cron_expr', 'path');

			if (Mage::getStoreConfigFlag('maillog/email/enabled') && Mage::getStoreConfigFlag('maillog/unsubscribers/enabled')) {
				$value = trim(Mage::getStoreConfig('maillog/unsubscribers/cron_expr'));
				$config->setValue((strlen($value) < 9) ? '30 2 * * *' : $value);
				$config->setPath('crontab/jobs/maillog_unsubscribers_import/schedule/cron_expr');
				$config->save();
			}
			else {
				$config->delete();
			}
		}
		catch (Exception $e) {
			Mage::throwException($e->getMessage());
		}
	}


	// CRON maillog_send_report
	public function sendEmailReport() {

		Mage::getSingleton('core/translate')->setLocale(Mage::getStoreConfig('general/locale/code'))->init('adminhtml', true);
		$frequency = Mage::getStoreConfig('maillog/email/frequency');
		$errors = array();

		// chargement des emails de la période
		// le mois dernier (mensuel/monthly), les septs derniers jour (hebdomadaire/weekly), hier (quotidien/daily)
		if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
			$frequency = $this->__('monthly');
			$date = $this->getDateRange('last_month');
		}
		else if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
			$frequency = $this->__('weekly');
			$date = $this->getDateRange('last_week');
		}
		else {
			$frequency = $this->__('daily');
			$date = $this->getDateRange('last_day');
		}

		// chargement des emails de la période
		// optimisation maximale de manière à ne charger que le nécessaire (pas le contenu des emails)
		$emails = Mage::getResourceModel('maillog/email_collection');
		$emails->setOrder('email_id', 'desc');
		$emails->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('email_id', 'status', 'mail_subject', 'created_at')); // opt. maximale
		$emails->addFieldToFilter('created_at', array(
			'from' => $date['start']->toString(Zend_Date::RFC_3339),
			'to' => $date['end']->toString(Zend_Date::RFC_3339),
			'datetime' => true
		));

		foreach ($emails as $email) {

			if (!in_array($email->getStatus(), array('error', 'pending')))
				continue;

			$link = '<a href="'.$this->getEmailUrl('adminhtml/maillog_history/view', array('id' => $email->getId())).'" style="font-weight:bold; color:red; text-decoration:none;">'.$this->__('Email %d: %s', $email->getId(), $email->getMailSubject()).'</a>';

			$hour  = $this->__('Created At: %s', Mage::getSingleton('core/locale')->date($email->getCreatedAt(), Zend_Date::ISO_8601));
			$state = $this->__('Status: %s', $this->__(ucfirst($email->getStatus())));

			array_push($errors, sprintf('(%d) %s / %s / %s', count($errors) + 1, $link, $hour, $state));
		}

		$data = array(
			'frequency'        => $frequency,
			'date_period_from' => $date['start']->toString(Zend_Date::DATETIME_FULL),
			'date_period_to'   => $date['end']->toString(Zend_Date::DATETIME_FULL),
			'total_email'      => count($emails),
			'total_pending'    => count($emails->getItemsByColumnValue('status', 'pending')),
			'total_sending'    => count($emails->getItemsByColumnValue('status', 'sending')),
			'total_sent'       => count($emails->getItemsByColumnValue('status', 'sent')),
			'total_read'       => count($emails->getItemsByColumnValue('status', 'read')),
			'total_error'      => count($emails->getItemsByColumnValue('status', 'error')),
			'total_unsent'     => count($emails->getItemsByColumnValue('status', 'unsent')),
			'total_bounce'     => count($emails->getItemsByColumnValue('status', 'bounce')),
			'error_list'       => (count($errors) > 0) ? implode('</li><li style="margin:0.8em 0 0.5em;">', $errors) : '',
			'import_bounces'       => strip_tags($this->getImportStatus('bounces', 'bounces'), '<br>'),
			'import_unsubscribers' => strip_tags($this->getImportStatus('unsubscribers', 'unsubscribers'), '<br>'),
			'sync'             => Mage::getStoreConfigFlag('maillog/sync/enabled')
		);

		// chargement des statistiques des emails et des synchronisations
		// optimisation maximale de manière à ne faire que des COUNT(*) en base de données
		// ne génère pas les données de la semaine courante le lundi et le mardi car cela est sans intérêt
		$periods = array('cur_week', 'last_week', 'old_week', 'last_month', 'old_month');
		$emails = Mage::getResourceModel('maillog/email_collection');
		$syncs  = Mage::getResourceModel('maillog/sync_collection');
		$today  = Mage::getSingleton('core/locale')->date()->toString(Zend_Date::WEEKDAY_8601);

		foreach ($periods as $period) {

			if (in_array($period, array('cur_week')) && in_array($today, array(1, 2))) {
				$data[$period.'_total_email'] = 0;
				continue;
			}

			$date  = $this->getDateRange($period);
			$where = array(
				'from' => $date['start']->toString(Zend_Date::RFC_3339),
				'to' => $date['end']->toString(Zend_Date::RFC_3339),
				'datetime' => true
			);

			$data[$period.'_period'] = $date['start']->toString(Zend_Date::DATE_SHORT).' - '.$date['end']->toString(Zend_Date::DATE_SHORT);
			$data[$period.'_total_email']    = $this->getNumber($where, $emails);
			$data[$period.'_percent_sent']   = $this->getNumber($where, $emails, array('in'  => array('sent', 'read')));
			$data[$period.'_percent_read']   = $this->getNumber($where, $emails, array('in'  => array('sent', 'read')), 'read');
			$data[$period.'_percent_unsent'] = $this->getNumber($where, $emails, array('nin' => array('sent', 'read')));
			$data[$period.'_total_sync']     = $this->getNumber($where, $syncs);
			$data[$period.'_percent_sync']   = $this->getNumber($where, $syncs,  'success');
		}

		// envoi des emails
		$this->sendReportToRecipients($data);
	}

	private function getNumber($where, $collection, $s1 = null, $s2 = null) {

		// ne fonctionne pas avec PHP 5.6 : (clone $collection)->getSize()
		// avec des resets sinon le where est conservé malgré le clone
		//Mage::log('-- getNumber --');

		if (is_null($s1)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);

			//Mage::log('nul/nul  '.((string) $data->getSelect()));
			return $data->getSize(); // totalité
		}

		if (is_null($s2)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->getSize(); // totalité
			//Mage::log('nb1/nul  '.((string) $data->getSelect()));

			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb2 = $data->addFieldToFilter('status', $s1)->getSize(); // filtré
			//Mage::log('nb1/nul  '.((string) $data->getSelect()));
		}
		else {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->addFieldToFilter('status', $s1)->getSize(); // totalité
			//Mage::log('nb1/nb2  '.((string) $data->getSelect()));

			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb2 = $data->addFieldToFilter('status', $s2)->getSize(); // filtré
			//Mage::log('nb1/nb2  '.((string) $data->getSelect()));
		}

		$percent = ($nb1 > 0) ? ($nb2 * 100) / $nb1 : 0;
		$percent = Zend_Locale_Format::toNumber($percent, array('precision' => 2));

		return str_replace(array(',00','.00'), '', $percent).'%<br /><small>('.$nb2.')</small>';
	}

	private function getDateRange($range) {

		$dateStart = Mage::getSingleton('core/locale')->date();
		$dateStart->setTimezone(Mage::getStoreConfig('general/locale/timezone'));
		$dateStart->setHour(0);
		$dateStart->setMinute(0);
		$dateStart->setSecond(0);

		$dateEnd = Mage::getSingleton('core/locale')->date();
		$dateEnd->setTimezone(Mage::getStoreConfig('general/locale/timezone'));
		$dateEnd->setHour(23);
		$dateEnd->setMinute(59);
		$dateEnd->setSecond(59);

		// de 1 (pour Lundi) à 7 (pour Dimanche)
		// permet d'obtenir des semaines du lundi au dimanche
		$day = $dateStart->toString(Zend_Date::WEEKDAY_8601) - 1;

		if ($range === 'last_month') {
			$dateStart->subMonth(1)->setDay(1);
			$dateEnd->subMonth(1)->setDay(1);
			$dateEnd->setDay($dateEnd->toString(Zend_Date::MONTH_DAYS));
			// Évite ce genre de chose... (date(n) = numéro du mois, date(t)/Zend_Date::MONTH_DAYS = nombre de jour du mois)
			// Période du dimanche 1 mars 2015 00:00:00 Europe/Paris au samedi 28 février 2015 23:59:59 Europe/Paris
			// Il est étrange que la variable dateEnd ne soit pas affectée
			if (date('n', $dateStart->getTimestamp()) === date('n', $dateEnd->getTimestamp()))
				$dateStart->subDay($dateStart->toString(Zend_Date::MONTH_DAYS));
		}
		else if ($range === 'old_month') {
			$dateStart->subMonth(2)->setDay(1);
			$dateEnd->subMonth(2)->setDay(1);
			$dateEnd->setDay($dateEnd->toString(Zend_Date::MONTH_DAYS));
			// Évite ce genre de chose... (date(n) = numéro du mois, date(t)/Zend_Date::MONTH_DAYS = nombre de jour du mois)
			// Période du dimanche 1 mars 2015 00:00:00 Europe/Paris au samedi 28 février 2015 23:59:59 Europe/Paris
			// Il est étrange que la variable dateEnd ne soit pas affectée
			if (date('n', $dateStart->getTimestamp()) === date('n', $dateEnd->getTimestamp()))
				$dateStart->subDay($dateStart->toString(Zend_Date::MONTH_DAYS));
		}
		else if ($range === 'cur_week') {
			$dateStart->subDay($day);
			$dateEnd->subDay(1);
		}
		else if ($range === 'last_week') {
			$dateStart->subDay($day + 7);
			$dateEnd->subDay($day + 1);
		}
		else if ($range === 'old_week') {
			$dateStart->subDay($day + 14);
			$dateEnd->subDay($day + 8);
		}
		else if ($range === 'last_day') {
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		return array('start' => $dateStart, 'end' => $dateEnd);
	}

	private function getEmailUrl($url, $params = array()) {

		if (Mage::getStoreConfigFlag('web/seo/use_rewrites'))
			return preg_replace('#/[^/]+\.php/#', '/', Mage::helper('adminhtml')->getUrl($url, $params));
		else
			return preg_replace('#/[^/]+\.php/#', '/index.php/', Mage::helper('adminhtml')->getUrl($url, $params));
	}

	private function sendReportToRecipients($vars) {

		$emails = explode(' ', trim(Mage::getStoreConfig('maillog/email/recipient_email')));
		$vars['config'] = $this->getEmailUrl('adminhtml/system/config');
		$vars['config'] = substr($vars['config'], 0, strrpos($vars['config'], '/system/config'));

		foreach ($emails as $email) {

			if (in_array($email, array('hello@example.org', 'hello@example.com', '')))
				continue;

			// sendTransactional($templateId, $sender, $recipient, $name, $vars = array(), $storeId = null)
			// pièce jointe uniquement lors de la sauvegarde de la configuration
			$template = Mage::getModel('core/email_template');

			if (strpos(Mage::helper('core/url')->getCurrentUrl(), 'section/maillog') !== false) {
				$template->getMail()->createAttachment(
					gzencode(file_get_contents(realpath(dirname(__FILE__).'/../etc/tidy.conf')), 9, FORCE_GZIP),
					'application/x-gzip',
					Zend_Mime::DISPOSITION_ATTACHMENT,
					Zend_Mime::ENCODING_BASE64,
					'tidy.gz'
				);
			}

			$template->sendTransactional(
				Mage::getStoreConfig('maillog/email/template'),
				Mage::getStoreConfig('maillog/email/sender_email_identity'),
				trim($email), null, $vars
			);

			if (!$template->getSentSuccess())
				Mage::throwException($this->__('Can not send the report by email to %s.', $email));

			//exit($template->getProcessedTemplate($vars));
		}
	}


	// EVENT customer_login (frontend)
	public function customerLoginSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {

			// demande à Magento d'enregistrer tout de suite les données
			// et non d'attendre l'événement "controller_action_postdispatch"
			Mage::getSingleton('log/visitor')->bindCustomerLogin($observer)->saveByRequest(null);

			$sync = Mage::getSingleton('maillog/sync');
			$sync->updateCustomer($observer->getEvent()->getCustomer(), Mage::app()->getStore()->getCode());
		}
	}

	// EVENT address_save_after (frontend)
	public function addressSaveSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {
			$sync = Mage::getSingleton('maillog/sync');
			$sync->updateAddress($observer->getEvent()->getCustomerAddress(), Mage::app()->getStore()->getCode());
		}
	}

	// EVENT customer_save_commit_after (global)
	public function customerSaveSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {
			$sync = Mage::getSingleton('maillog/sync');
			$sync->updateCustomer($observer->getEvent()->getCustomer(), Mage::app()->getStore()->getCode());
		}
	}

	// EVENT customer_delete_after (global)
	public function customerDeleteSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {
			$sync = Mage::getSingleton('maillog/sync');
			$sync->deleteCustomer($observer->getEvent()->getCustomer(), Mage::app()->getStore()->getCode());
		}
	}

	// EVENT newsletter_subscriber_save_after (global)
	public function subscriberSaveSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {
			$sync = Mage::getSingleton('maillog/sync');
			$sync->updateSubscriber($observer->getEvent()->getSubscriber(), Mage::app()->getStore()->getCode());
		}
	}

	// EVENT newsletter_subscriber_delete_after (global)
	public function subscriberDeleteSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {
			$sync = Mage::getSingleton('maillog/sync');
			$sync->deleteSubscriber($observer->getEvent()->getSubscriber(), Mage::app()->getStore()->getCode());
		}
	}

	// EVENT sales_order_invoice_save_commit_after (global)
	public function orderInvoiceSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/sync/enabled') &&
		    (Mage::registry('maillog_no_sync') !== true)) {
			$sync = Mage::getSingleton('maillog/sync');
			$sync->orderInvoice($observer->getEvent()->getInvoice(), Mage::app()->getStore()->getCode());
		}
	}


	// EVENT adminhtml_customer_prepare_save (adminhtml)
	// génération du store_id du client lors de la création d'un client
	// sinon Magento aura la merveilleuse idée de dire que le store_id est 0
	// actif même si le module n'est pas activé
	public function setCustomerStoreId($observer) {

		$customer = $observer->getEvent()->getCustomer();

		if ($customer->getId() < 1) {
			if ($customer->getSendemailStoreId() > 0)
				$customer->setData('store_id', $customer->getSendemailStoreId());
			else
				$customer->setData('store_id', Mage::app()->getWebsite($customer->getWebsiteId())->getDefaultStore()->getId());
		}
	}

	// EVENT newsletter_subscriber_save_before (adminhtml)
	// génération du store_id de l'abonné lors de l'enregistrement d'un abonné
	// se base sur le store_id du client ($subscriber->getStoreId() = 0 sur Magento 1.4/1.5)
	// actif même si le module n'est pas activé
	public function setSubscriberStoreId($observer) {

		$subscriber = $observer->getEvent()->getSubscriber();
		$customer = Mage::registry('current_customer');

		if (is_object($customer) && ($subscriber->getStoreId() !== $customer->getStoreId())) {
			$subscriber->setData('store_id', $customer->getStoreId());
			$subscriber->setOrigData('store_id', $customer->getStoreId());
		}
	}


	// CRON maillog_bounces_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message du cron et dans le fichier status.dat
	public function bouncesFileImport($cron = null) {

		Mage::register('maillog_no_sync', true);

		try {
			$folder = Mage::getStoreConfig('maillog/bounces/directory');
			$folder = Mage::getBaseDir('var').str_replace('//', '/', '/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = trim(Mage::getStoreConfig('maillog/bounces/format'));

			$lastFile = $this->searchTodayFile($folder, substr($config, 0, 3));
			$newItems = $this->getDataFromFile($folder, $lastFile, $config);

			$dbItems = Mage::getResourceModel('maillog/bounce_collection');
			$dbItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('email')); // optimisation maximale

			$diff = $this->updateBouncesDatabase($newItems, $dbItems->getColumnValues('email'), $lastFile);

			$this->moveFiles($folder, $lastFile, substr($config, 0, 3));
			$this->writeLog($folder, $lastFile, $diff, $cron);
		}
		catch (Exception $e) {
			$error = (isset($diff['errors'])) ? implode('<br />', $diff['errors']) : $e->getMessage();
			$this->writeLog($folder, (isset($lastFile)) ? $lastFile : null, $error, $cron, false);
			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}

		Mage::unregister('maillog_no_sync');
	}

	// CRON maillog_unsubscribers_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message du cron et dans le fichier status.dat
	public function unsubscribersFileImport($cron = null) {

		Mage::register('maillog_no_sync', true);

		try {
			$folder = Mage::getStoreConfig('maillog/unsubscribers/directory');
			$folder = Mage::getBaseDir('var').str_replace('//', '/', '/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = trim(Mage::getStoreConfig('maillog/unsubscribers/format'));

			$lastFile = $this->searchTodayFile($folder, substr($config, 0, 3));
			$newItems = $this->getDataFromFile($folder, $lastFile, $config);

			$dbItems = Mage::getResourceModel('newsletter/subscriber_collection');
			$dbItems->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
			$dbItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('subscriber_email')); // optimisation maximale

			$diff = $this->updateUnsubscribersDatabase($newItems, $dbItems->getColumnValues('subscriber_email'));

			$this->moveFiles($folder, $lastFile, substr($config, 0, 3));
			$this->writeLog($folder, $lastFile, $diff, $cron);
		}
		catch (Exception $e) {
			$error = (isset($diff['errors'])) ? implode('<br />', $diff['errors']) : $e->getMessage();
			$this->writeLog($folder, (isset($lastFile)) ? $lastFile : null, $error, $cron, false);
			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}

		Mage::unregister('maillog_no_sync');
	}

	// 7 exceptions - c'est ici que tout se joue car si tout va bien nous avons un fichier et un dossier accessibles et modifiables
	// dossier de base : inexistant, non accessible en lecture, non accessible en écriture, vide
	// fichier : non accessible en lecture, non accessible en écriture, trop vieux
	private function searchTodayFile($folder, $type) {

		// vérifications du dossier
		if (!is_dir($folder))
			throw new Exception('Sorry, the directory "'.$folder.'" is not set.');
		if (!is_readable($folder))
			throw new Exception('Sorry, the directory "'.$folder.'" is not readable.');
		if (!is_writable($folder))
			throw new Exception('Sorry, the directory "'.$folder.'" is not writable.');

		// recherche des fichiers
		// utilise un tableau pour pouvoir trier par date
		$allfiles = glob($folder.'*.'.$type);
		$files = array();

		foreach ($allfiles as $file)
			$files[filemtime($file)] = basename($file);

		if (empty($files))
			throw new Exception('Sorry, there is no file in directory "'.$folder.'".');

		// du plus grand au plus petit, donc du plus récent au plus ancien
		// de manière à avoir le fichier le plus récent en premier car on souhaite traiter le fichier du jour uniquement
		krsort($files);
		$time = key($files);
		$file = current($files);

		// vérifications du fichier
		// pour la date, seul compte le jour
		if (!is_readable($folder.$file))
			throw new Exception('Sorry, the file "'.$folder.$file.'" is not readable.');
		if (!is_writable($folder.$file))
			throw new Exception('Sorry, the file "'.$folder.$file.'" is not writable.');
		if ($time < Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->getTimestamp())
			throw new Exception('Sorry, the file "'.$folder.$file.'" is too old for today.');

		return $file;
	}

	// 1 exception
	// liste des erreurs du traitement du fichier
	private function writeLog($folder, $file, $diff, $cron, $throw = true) {

		$diff = (is_string($diff)) ? array('exception' => $diff) : $diff;
		$exception = $diff['errors'];

		// pour le message du cron et le status.dat
		if (!is_null($file)) {
			$diff['file'] = 'done/'.Mage::getSingleton('core/locale')->date()->toString('Y-MM').'/'.$file.'.gz';
			$diff['size'] = strlen(gzdecode(file_get_contents($folder.$diff['file'])));
		}
		if (is_object($cron)) {
			$cron->setMessages(str_replace($diff['file'], $folder.$diff['file'], print_r($diff, true)));
			$diff['cron'] = $cron->getId();
		}

		// pour le status.dat
		// n'affiche pas les adresses, uniquement les nombres d'adresses
		$diff['added']        = (!empty($diff['added']))        ? count($diff['added'])        : 0;
		$diff['removed']      = (!empty($diff['removed']))      ? count($diff['removed'])      : 0;
		$diff['subscribed']   = (!empty($diff['subscribed']))   ? count($diff['subscribed'])   : 0;
		$diff['unsubscribed'] = (!empty($diff['unsubscribed'])) ? count($diff['unsubscribed']) : 0;
		$diff['errors']       = (!empty($diff['errors']))       ? count($diff['errors'])       : 0;

		file_put_contents($folder.'status.dat', serialize($diff));

		if ($throw && !empty($exception))
			throw new Exception(implode(' // ', $exception));
	}

	// lecture du fichier à importer (en fonction de la configuration - format de fichier et localisation)
	// mise à jour de la base de données (ne touche pas à ce qui ne change pas - ajoute/supprime/modifie)
	// déplacement et compression des fichiers (ça devrait passer - base/done/skip)
	private function getDataFromFile($folder, $file, $config) {

		$type = substr($config, 0, 3);

		// type=txt
		// type=csv;2" pour type=csv delim=; colum=2 separ="
		if ($type === 'csv') {
			$delim = substr($config, 3, 1);
			$colum = substr($config, 4, 1);
			$separ = substr($config, 5, 1);
		}

		$items = array();
		$lines = explode("\n", trim(file_get_contents($folder.$file)));

		foreach ($lines as $line) {

			$line = trim($line);

			if (strlen($line) <= 5) {
				continue;
			}
			else if ($type === 'csv') {
				$data = explode($delim, $line);
				if (isset($data[$colum - 1]) && (strpos($data[$colum - 1], '@') !== false))
					array_push($items, trim(str_replace($separ, '', $data[$colum - 1])));
			}
			else if ($type === 'txt') {
				if (strpos($line, '@') !== false)
					array_push($items, trim($line));
			}
		}

		return $items;
	}

	private function updateBouncesDatabase($newItems, $dbItems, $source) {

		$model = Mage::getModel('maillog/bounce');
		$diff  = array('lines' => count($newItems), 'added' => array(), 'removed' => array(), 'errors' => array());

		// traitement des adresses emails AJOUTÉES par rapport à la dernière fois
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun des autres tableaux
		// une adresse email AJOUTÉE = une adresse invalide (donc une adresse email à ajouter dans la liste des adresses invalides en bdd)
		$emails = array_diff($newItems, $dbItems);

		foreach ($emails as $email) {
			try {
				$bounce = clone $model;
				$bounce->setCreatedAt(date('Y-m-d H:i:s'));
				$bounce->setEmail($email);
				$bounce->setSource($source);
				$bounce->save();

				array_push($diff['added'], $email);
			}
			catch (Exception $e) {
				array_push($diff['errors'], $email.' - '.$e->getMessage());
			}
		}

		// traitement des adresses emails SUPPRIMÉES par rapport à la dernière fois
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun des autres tableaux
		// une adresse email SUPPRIMÉE = une adresse devenu valide (donc une adresse email à supprimer de la liste des adresses invalides en bdd)
		$emails = array_diff($dbItems, $newItems);

		foreach ($emails as $email) {
			try {
				$bounce = clone $model;
				$bounce->load($email);
				$bounce->delete();

				array_push($diff['removed'], $email);
			}
			catch (Exception $e) {
				array_push($diff['errors'], $email.' - '.$e->getMessage());
			}
		}

		// log
		return $diff;
	}

	private function updateUnsubscribersDatabase($newItems, $dbItems) {

		// crash test express (memory_limit à 1 Go)
		// pour 329260 abonnés, il faut environ 7 minutes pour désabonner 63567/65567 abonnés
		//   s'il n'y a personne à désabonner il faut 2 secondes
		//
		// sans le lot de 1000, sans le reset columns, AVEC le in emails, sans le save via getResource
		//   il faut 30 minutes pour désabonner 19649 abonnés (30.9%) sur 63567/65567 abonnés
		//   si le calcul est bon il faut 97 minutes pour traiter les 63567/65567 abonnés
		//   s'il n'y a personne à désabonner il faut environ 2 secondes
		// c'est donc environ 13x plus rapide, comme quoi, je suis bon !
		//
		// sans le lot de 1000, sans le reset columns, sans le in emails, sans le save via getResource
		//   il faut 30 minutes pour désabonner 3496 abonnés (5.5%) sur 63567/65567 abonnés
		//   si le calcul est bon il faut 9 heures pour traiter les 63567/65567 abonnés
		//   s'il n'y a personne à désabonner il faut environ 13 minutes
		// c'est donc environ 77x plus rapide, comme quoi, je suis le meilleur !
		$diff = array('lines' => count($newItems), 'unsubscribed' => array(), 'subscribed' => array(), 'errors' => array());

		// traitement des adresses emails AJOUTÉES par rapport à la dernière fois
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun des autres tableaux
		// une adresse email AJOUTÉE = une adresse désinscrite de la newsletter (STATUS_UNSUBSCRIBED)
		$allEmails = array_diff($newItems, $dbItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('newsletter/subscriber_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('subscriber_id', 'subscriber_email')); // optimisation maximale
			$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
			$items->addFieldToFilter('subscriber_email', array('in' => $emails));

			foreach ($emails as $email) {
				try {
					$subscriber = $items->getItemByColumnValue('subscriber_email', $email);

					if (!is_null($subscriber)) { // est forcément STATUS_SUBSCRIBED

						$subscriber->setChangeStatusAt(date('Y-m-d H:i:s'));
						$subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
						$subscriber->getResource()->save($subscriber);
						//$subscriber->save(); // +1 min sur les 7 du test

						array_push($diff['unsubscribed'], $email);
					}
				}
				catch (Exception $e) {
					array_push($diff['errors'], $email.' - '.$e->getMessage());
				}
			}
		}

		// traitement des adresses emails SUPPRIMÉES par rapport à la dernière fois
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun des autres tableaux
		// une adresse email SUPPRIMÉE = une adresse inscrite à la newsletter (STATUS_SUBSCRIBED)
		$allEmails = array_diff($dbItems, $newItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('newsletter/subscriber_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('subscriber_id', 'subscriber_email')); // optimisation maximale
			$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
			$items->addFieldToFilter('subscriber_email', array('in' => $emails));

			foreach ($emails as $email) {
				try {
					$subscriber = $items->getItemByColumnValue('subscriber_email', $email);

					if (!is_null($subscriber)) { // est forcément STATUS_UNSUBSCRIBED

						$subscriber->setChangeStatusAt(date('Y-m-d H:i:s'));
						$subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
						$subscriber->getResource()->save($subscriber);
						//$subscriber->save(); // +1 min sur les 7 du test

						array_push($diff['subscribed'], $email);
					}
				}
				catch (Exception $e) {
					array_push($diff['errors'], $email.' - '.$e->getMessage());
				}
			}
		}

		// log
		return $diff;
	}

	private function moveFiles($folder, $file, $type) {

		$date = Mage::getSingleton('core/locale')->date()->toString('Y-MM');
		$donedir = $folder.'done/'.$date.'/';
		$skipdir = $folder.'skip/'.$date.'/';

		if (!is_dir($donedir))
			mkdir($donedir, 0777, true);
		if (!is_dir($skipdir))
			mkdir($skipdir, 0777, true);

		// déplace et compresse le fichier traité
		rename($folder.$file, $donedir.$file.'.gz');
		file_put_contents($donedir.$file.'.gz', gzencode(file_get_contents($donedir.$file.'.gz'), 9, FORCE_GZIP));

		// déplace et compresse les fichiers ignorés
		// reste silencieux en cas d'erreur (car de toute façon, si le fichier n'est pas déplaçable, le fichier ne sera jamais traité)
		$files = glob($folder.'*.'.$type);
		foreach ($files as $file) {

			$file = basename($file);
			@rename($folder.$file, $skipdir.$file.'.gz');

			if (is_file($skipdir.$file.'.gz') && is_readable($skipdir.$file.'.gz') && is_writable($skipdir.$file.'.gz'))
				file_put_contents($skipdir.$file.'.gz', gzencode(file_get_contents($skipdir.$file.'.gz'), 9, FORCE_GZIP));
		}

		// supprime le dossier des fichiers ignorés si celui-ci est VIDE
		@rmdir($skipdir);
	}
}