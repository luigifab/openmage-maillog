<?php
/**
 * Created S/04/04/2015
 * Updated M/30/04/2019
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

class Luigifab_Maillog_Model_Observer extends Luigifab_Maillog_Helper_Data {

	// EVENT admin_system_config_changed_section_maillog (adminhtml)
	public function updateConfig() {

		// suppression des anciens emails
		// suppression des anciennes synchros
		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/maillog_clean_old_data/schedule/cron_expr', 'path');

		$check = @unserialize(Mage::getStoreConfig('maillog/general/special_config'));
		if (!empty($check) && is_array($check)) {
			foreach ($check as $key => $value) {
				if (is_numeric($value)) {
					$check = true;
					break;
				}
			}
		}

		if (($check === true) || !empty(Mage::getStoreConfig('maillog/sync/lifetime'))) {
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

		if (Mage::getStoreConfigFlag('maillog/bounces/enabled')) {
			$value = Mage::getStoreConfig('maillog/bounces/cron_expr');
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

		if (Mage::getStoreConfigFlag('maillog/unsubscribers/enabled')) {
			$value = Mage::getStoreConfig('maillog/unsubscribers/cron_expr');
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
			if (!empty(Mage::app()->getRequest()->getPost('maillog_test_email')))
				$this->sendEmailReport(null, true);
		}
		else {
			$config->delete();
		}
	}


	// CRON maillog_send_report
	public function sendEmailReport($cron = null, $test = false) {

		$oldLocale = Mage::getSingleton('core/translate')->getLocale();
		$newLocale = Mage::app()->getStore()->isAdmin() ? $oldLocale : Mage::getStoreConfig('general/locale/code');
		Mage::getSingleton('core/translate')->setLocale($newLocale)->init('adminhtml', true);

		$frequency = Mage::getStoreConfig('maillog/email/frequency');
		$errors = $items = array();

		// chargement des emails de la période
		// le mois dernier (mensuel/monthly), les septs derniers jour (hebdomadaire/weekly), hier (quotidien/daily)
		if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
			$frequency = $this->_('monthly');
			$dates = $this->getDateRange('last_month');
		}
		else if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
			$frequency = $this->_('weekly');
			$dates = $this->getDateRange('last_week');
		}
		else {
			$frequency = $this->_('daily');
			$dates = $this->getDateRange('last_day');
		}

		// chargement des emails de la période
		// optimisation maximale de manière à ne charger que le nécessaire (pas le contenu des emails)
		$emails = Mage::getResourceModel('maillog/email_collection');
		$emails->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('email_id', 'status', 'mail_subject', 'created_at')); // opt. maximale
		$emails->setOrder('email_id', 'desc');
		$emails->addFieldToFilter('created_at', array(
			'datetime' => true,
			'from' => $dates['start']->toString(Zend_Date::RFC_3339),
			'to' => $dates['end']->toString(Zend_Date::RFC_3339)
		));

		foreach ($emails as $email) {

			if (!in_array($email->getData('status'), array('error', 'pending')))
				continue;

			$link = '<a href="'.$this->getEmailUrl('adminhtml/maillog_history/view', array('id' => $email->getId())).'" style="font-weight:700; color:red; text-decoration:none;">'.$this->__('Email %d: %s', $email->getId(), $email->getSubject()).'</a>';

			$state = $this->__('Status: %s', $this->__(ucfirst($email->getData('status'))));
			$hour  = $this->__('Created At: %s', Mage::helper('maillog')->formatDate($email->getData('created_at')));

			$errors[] = sprintf('(%d) %s / %s / %s', count($errors) + 1, $link, $hour, $state);
		}

		$vars = array(
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
			'error_list'           => !empty($errors) ? implode('</li><li style="margin:0.8em 0 0.5em;">', $errors) : '',
			'import_bounces'       => trim(strip_tags($this->getImportStatus('bounces', 'bounces'))),
			'import_unsubscribers' => trim(strip_tags($this->getImportStatus('unsubscribers', 'unsubscribers'))),
			'sync'                 => Mage::getStoreConfigFlag('maillog/sync/enabled'),
			'items'                => &$items
		);

		$search  = array("\n",   ').<br>',                                                 '.Sorry,');
		$replace = array('<br>', ').<br><span style="font-size:11px; line-height:14px;">', '.<br>Sorry,');
		$vars['import_bounces']       = str_replace($search, $replace, $vars['import_bounces']).'</span>';
		$vars['import_unsubscribers'] = str_replace($search, $replace, $vars['import_unsubscribers']).'</span>';

		// chargement des statistiques des emails et des synchronisations
		// optimisation maximale de manière à ne faire que des COUNT en base de données
		// ne génère pas les données de la semaine courante dans le rapport du lundi et du mardi
		// ne recherche pas les données au dela de la durée de vie maximale (email et sync)
		$emails = Mage::getResourceModel('maillog/email_collection');
		$syncs  = Mage::getResourceModel('maillog/sync_collection');
		$val    = Mage::getStoreConfig('maillog/sync/lifetime') * 60; // lifetime sync (en secondes)
		$max    = 0; // lifetime email (en mois)

		$config = @unserialize(Mage::getStoreConfig('maillog/general/special_config'));
		if (!empty($config) && is_array($config)) {
			foreach ($config as $key => $months) {
				if (is_numeric($months) && ($months >= 2) && (mb_strpos($key, '_all') !== false))
					$max = ($months > $max) ? $months : $max;
			}
		}

		$curWeek = false;
		if (!in_array(Mage::getSingleton('core/locale')->date()->toString(Zend_Date::WEEKDAY_8601), array(1, 2)))
			$curWeek = true;

		// de 1 à  6 : semaines
		// de 7 à 18 : mois
		for ($i = 1, $j = 6; $i < 18; $i++) {

			// cherche les dates
			if (($i == 1) && $curWeek)
				$dates = $this->getDateRange('cur_week');
			else if ($curWeek)
				$dates = $this->getDateRange(($i <= $j) ? 'last_week' : 'last_month', ($i <= $j) ? $i - 2 : $i - $j);
			else
				$dates = $this->getDateRange(($i <= $j) ? 'last_week' : 'last_month', ($i <= $j) ? $i - 1 : $i - $j);

			$where = array(
				'datetime' => true,
				'from' => $dates['start']->toString(Zend_Date::RFC_3339),
				'to' => $dates['end']->toString(Zend_Date::RFC_3339)
			);

			// prépare les données
			if ($i > $j)
				$items[$i]['period'] = ucfirst($dates['start']->toString(Zend_Date::MONTH_NAME).' '.$dates['start']->toString(Zend_Date::YEAR));
			else if (($i == 1) && $curWeek)
				$items[$i]['period'] = $this->__('Week %d', $dates['start']->toString(Zend_Date::WEEK)).'*';
			else
				$items[$i]['period'] = $this->__('Week %d', $dates['start']->toString(Zend_Date::WEEK));

			$items[$i]['period'] .= '<br /><small>'.$dates['start']->toString(Zend_Date::DATE_SHORT).' - '.
				$dates['end']->toString(Zend_Date::DATE_SHORT).'</small>';

			// calcul les statistiques
			// email et sync
			if ((!empty($max) && ($max > ($i - $j))) || empty($max)) {
				$items[$i]['total_email']    = $this->getNumber($where, $emails);
				$items[$i]['percent_sent']   = $this->getNumber($where, $emails, array('in'  => array('sent', 'read')));
				$items[$i]['percent_read']   = $this->getNumber($where, $emails, array('in'  => array('sent', 'read')), 'read');
				$items[$i]['percent_unsent'] = $this->getNumber($where, $emails, array('nin' => array('sent', 'read')));
			}

			if (empty($val) || (!empty($val) && (($dates['now']->getTimestamp() - $val) <= $dates['start']->getTimestamp()))) {
				$items[$i]['total_sync']   = $this->getNumber($where, $syncs);
				$items[$i]['percent_sync'] = $this->getNumber($where, $syncs,  'success');
			}

			// ne va pas au dela de la durée de vie maximale
			if (empty($items[$i]['total_email']) && empty($items[$i]['total_sync'])) {
				unset($items[$i]);
				break;
			}
		}

		// envoi des emails
		$vars['test'] = $test;
		if ($vars['test']) {
			$vars['variable'] = 50;
			$vars['variable2'] = array(array('abc' => 4), array('abc' => 6));
			$vars['variable3'] = Mage::getResourceModel('catalog/product_collection')->setPageSize(3);
		}

		$this->sendReportToRecipients($newLocale, $vars);

		if ($newLocale != $oldLocale)
			Mage::getSingleton('core/translate')->setLocale($oldLocale)->init('adminhtml', true);
	}

	private function getNumber($where, $collection, $status1 = null, $status2 = null) {

		// ne fonctionne pas avec PHP 5.6 : (clone $collection)->getSize()
		// avec des resets sinon le where est conservé malgré le clone
		if (empty($status1)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			return $data->getSize(); // totalité
		}
		if (empty($status2)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->getSize(); // totalité
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb2 = $data->addFieldToFilter('status', $status1)->getSize(); // filtré
		}
		else {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->addFieldToFilter('status', $status1)->getSize(); // totalité
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			$data->addFieldToFilter('created_at', $where);
			$nb2 = $data->addFieldToFilter('status', $status2)->getSize(); // filtré
		}

		$percent = ($nb1 > 0) ? ($nb2 * 100) / $nb1 : 0;
		$percent = Zend_Locale_Format::toNumber($percent, array('precision' => 2));

		return str_replace(array(',00','.00'), '', $percent).'%<br /><small>('.$nb2.')</small>';
	}

	private function getDateRange($range, $coeff = 0) {

		$dateNow   = Mage::getSingleton('core/locale')->date();
		$dateStart = Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->setSecond(0);
		$dateEnd   = Mage::getSingleton('core/locale')->date()->setHour(23)->setMinute(59)->setSecond(59);

		// de 1 (pour Lundi) à 7 (pour Dimanche)
		// permet d'obtenir des semaines du lundi au dimanche
		$day = $dateStart->toString(Zend_Date::WEEKDAY_8601) - 1;

		if ($range == 'last_month') {
			$dateEnd->subMonth(1 * $coeff)->setDay($dateEnd->toString(Zend_Date::MONTH_DAYS));
			$dateStart->subMonth(1 * $coeff)->setMonth($dateEnd->getMonth())->setDay(1);
		}
		else if ($range == 'cur_week') {
			$dateStart->subDay($day);
			$dateEnd->subDay(1);
		}
		else if ($range == 'last_week') {
			$dateStart->subDay($day + 7 * $coeff + 7);
			$dateEnd->subDay($day + 7 * $coeff + 1);
		}
		else if ($range == 'last_day') {
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		return array('now' => $dateNow, 'start' => $dateStart, 'end' => $dateEnd);
	}

	private function getEmailUrl($url, $params = array()) {

		if (Mage::getStoreConfigFlag('web/seo/use_rewrites'))
			return preg_replace('#/[^/]+\.php\d*/#', '/', Mage::helper('adminhtml')->getUrl($url, $params));
		else
			return preg_replace('#/[^/]+\.php(\d*)/#', '/index.php$1/', Mage::helper('adminhtml')->getUrl($url, $params));
	}

	private function sendReportToRecipients($locale, $vars) {

		$emails = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog/email/recipient_email')));
		$vars['config'] = $this->getEmailUrl('adminhtml/system/config');
		$vars['config'] = mb_substr($vars['config'], 0, mb_strrpos($vars['config'], '/system/config'));

		foreach ($emails as $email) {

			if (in_array($email, array('hello@example.org', 'hello@example.com', '')))
				continue;

			// le setLocale utilisé plus haut ne permet pas d'utiliser le template email de la langue choisie
			// donc le sendTransactional est fait en manuel (identique de Magento 1.4 à 1.9) pour utiliser la locale que l'on veut
			$sender = Mage::getStoreConfig('maillog/email/sender_email_identity');
			$template = Mage::getModel('core/email_template');

			if (mb_strpos(Mage::helper('core/url')->getCurrentUrl(), 'section/maillog') !== false) {
				$template->getMail()->createAttachment(
					gzencode(file_get_contents(Mage::getModuleDir('etc', 'Luigifab_Maillog').'/tidy.conf'), 9),
					'application/x-gzip',
					Zend_Mime::DISPOSITION_ATTACHMENT,
					Zend_Mime::ENCODING_BASE64,
					'tidy.gz'
				);
			}

			// sendTransactional($templateId, $sender, $recipient, $name, $vars = array(), $storeId = null)
			//$template->sendTransactional(
			//	Mage::getStoreConfig('maillog/email/template'),
			//	Mage::getStoreConfig('maillog/email/sender_email_identity'),
			//	$email, null, $vars
			//);

			$template->setSentSuccess(false);
			$template->loadDefault('maillog_email_template', $locale);
			$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name'));
			$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email'));
			$template->setSentSuccess($template->send($email, null, $vars));

			if (!$template->getSentSuccess())
				Mage::throwException($this->__('Can not send the report by email to %s.', $email));

			//exit($template->getProcessedTemplate($vars));
		}
	}


	// EVENT customer_delete_after (global)
	public function customerDeleteClean($observer) {

		$customer = $observer->getData('customer');

		$emails = Mage::getResourceModel('maillog/email_collection');
		$emails->addFieldToFilter('deleted', array('neq' => 1));
		$emails->addFieldToFilter('mail_recipients', array('like' => '%<'.$customer->getData('email').'>%'));

		// même chose que cleanOldData ou presque
		foreach ($emails as $email) {
			$email->setData('mail_recipients', 'customer@removed');
			$email->setData('mail_subject', str_replace($customer->getData('lastname'), 'Removed', $email->getData('mail_subject')));
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

	// EVENT customer_delete_after (global)
	public function customerDeleteSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true)) {

			$syncs = Mage::getResourceModel('maillog/sync_collection');
			$syncs->addFieldToFilter('action', array('like' => '%:customer:'.$observer->getData('customer')->getId().':%'));
			$syncs->deleteAll();

			Mage::helper('maillog')->sendSync($observer->getData('customer'), 'customer', 'email', 'delete');
		}
	}

	// EVENT customer_login (frontend)
	public function customerLoginSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true))
			Mage::helper('maillog')->sendSync($observer->getData('customer'), 'customer', 'email', 'update');
	}

	// EVENT address_save_after (frontend)
	public function addressSaveSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true))
			Mage::helper('maillog')->sendSync($observer->getData('customer_address')->getCustomer(), 'customer', 'email', 'update');
	}

	// EVENT customer_save_commit_after (global)
	public function customerSaveSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true))
			Mage::helper('maillog')->sendSync($observer->getData('customer'), 'customer', 'email', 'update');
	}

	// EVENT newsletter_subscriber_save_after (global)
	public function subscriberSaveSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true))
			Mage::helper('maillog')->sendSync($observer->getData('subscriber'), 'subscriber', 'subscriber_email', 'update');
	}

	// EVENT newsletter_subscriber_delete_after (global)
	// si le client existe on met à jour le contact sinon on supprime le contact
	public function subscriberDeleteSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true)) {

			$subscriber = $observer->getData('subscriber');
			$action     = !empty($subscriber->getData('customer_id')) ? 'update' : 'delete';

			if ($action == 'delete') {
				$syncs = Mage::getResourceModel('maillog/sync_collection');
				$syncs->addFieldToFilter('action', array('like' => '%:subscriber:'.$subscriber->getId().':%'));
				foreach ($syncs as $sync)
					$sync->setData('request', null)->save();
			}

			Mage::helper('maillog')->sendSync($subscriber, 'subscriber', 'subscriber_email', $action);
		}
	}

	// EVENT sales_order_invoice_save_commit_after (global)
	// si le client existe on met à jour le contact
	public function orderInvoiceSync($observer) {

		if (Mage::getStoreConfigFlag('maillog/sync/enabled') && (Mage::registry('maillog_no_sync') !== true)) {
			$customer = Mage::getModel('customer/customer')->load($observer->getData('invoice')->getOrder()->getCustomerId());
			if (!empty($customer->getId()))
				Mage::helper('maillog')->sendSync($customer, 'customer', 'email', 'update');
		}
	}


	// EVENT adminhtml_customer_prepare_save (adminhtml)
	// génère le store_id du client lors de la création d'un client
	// sinon Magento aura la merveilleuse idée de dire que le store_id est 0
	// actif même si le module n'est pas activé
	public function setCustomerStoreId($observer) {

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
	// se base sur le store_id du client ($subscriber->getData('store_id') = 0 sur Magento 1.4/1.5)
	// actif même si le module n'est pas activé
	public function setSubscriberStoreId($observer) {

		$subscriber = $observer->getData('subscriber');
		$customer   = Mage::registry('current_customer');

		if (is_object($customer) && ($subscriber->getData('store_id') != $customer->getData('store_id')))
			$subscriber->setData('store_id', $customer->getData('store_id'));

		if (is_object($customer) && ($subscriber->getData('subscriber_email') != $customer->getOrigData('email')))
			$subscriber->setOrigData('subscriber_email', $customer->getOrigData('email'));
	}


	// CRON maillog_clean_old_data
	// réduit l'historique des synchronisations (qui est configuré en minutes) et des emails (qui est configuré en mois)
	public function cleanOldData($cron = null) {

		$msg = array();
		$total = 0;

		$val = Mage::getStoreConfig('maillog/sync/lifetime');
		if (!empty($val) && is_numeric($val)) {

			$syncs = Mage::getResourceModel('maillog/sync_collection');
			$syncs->addFieldToFilter('status', array('eq' => 'success'));
			$syncs->addFieldToFilter('created_at', array('lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$val.' MINUTE)')));
			$total += $syncs->getSize();
			$syncs->deleteAll();

			$syncs = Mage::getResourceModel('maillog/sync_collection');
			$syncs->addFieldToFilter('status', array('neq' => 'success'));
			$syncs->addFieldToFilter('created_at', array('lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.(3 * $val).' MINUTE)')));
			$total += $syncs->getSize();
			$syncs->deleteAll();

			$msg[] = 'Remove successful synchronizations after '.($val / 60 / 24).' days';
			$msg[] = !empty($total) ? ' → '.$total.' item(s) removed' : ' → no item to remove';
			$msg[] = '';
		}

		$config = @unserialize(Mage::getStoreConfig('maillog/general/special_config'));
		if (!empty($config) && is_array($config)) {

			foreach ($config as $key => $months) {

				if (is_numeric($months) && ($months >= 2)) {

					$cut    = mb_strpos($key, '_');
					$type   = mb_substr($key, 0, $cut);
					$action = mb_substr($key, $cut + 1);

					$emails = Mage::getResourceModel('maillog/email_collection');
					$emails->addFieldToFilter('deleted', array('neq' => 1));
					$emails->addFieldToFilter('created_at', array('lt' => new Zend_Db_Expr(
						'DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$months.' MONTH)')));

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

					$msg[] = !empty($total = $emails->getSize()) ? ' → '.$total.' item(s) removed ('.$ids.')' : ' → no item to remove';
					$msg[] = '';

					if (!empty($total) && ($action == 'all')) {
						$emails->deleteAll();
					}
					else if (!empty($total) && ($action == 'data')) {
						// même chose que customerDeleteClean
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

		if (is_object($cron))
			$cron->setMessages(implode("\n", array_slice($msg, 0, -1)));
	}

	// CRON maillog_bounces_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message de la tâche cron et dans le fichier status.dat
	public function bouncesFileImport($cron = null, $source = null) {

		Mage::register('maillog_no_sync', true);
		$diff = array('started_at' => date('Y-m-d H:i:s'), 'errors' => array());

		try {
			$folder = Mage::getStoreConfig('maillog/bounces/directory');
			$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = Mage::getStoreConfig('maillog/bounces/format');
			$type   = mb_substr($config, 0, 3);
			$source = $this->todayFile($folder, $type);

			$newItems = $this->dataFromFile($folder, $source, $config);

			$oldItems = Mage::getResourceModel('customer/customer_collection');
			$oldItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('email')); // optimisation maximale
			$oldItems->addAttributeToFilter('is_bounce', 2); // 1/No 2/Yes 3/Yes-forced 4/No-forced

			$this->updateCustomersDatabase($newItems, $oldItems->getColumnValues('email'), $diff);
			$this->moveFiles($folder, $source, $type);
			$this->writeLog($folder, $source, $diff, $cron);

			Mage::unregister('maillog_no_sync');
		}
		catch (Exception $e) {

			$error = !empty($diff['errors']) ? implode("\n", $diff['errors']) : $e->getMessage();
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
		$diff = array('started_at' => date('Y-m-d H:i:s'), 'errors' => array());

		try {
			$folder = Mage::getStoreConfig('maillog/unsubscribers/directory');
			$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = Mage::getStoreConfig('maillog/unsubscribers/format');
			$type   = mb_substr($config, 0, 3);
			$source = $this->todayFile($folder, $type);

			$newItems = $this->dataFromFile($folder, $source, $config);

			$oldItems = Mage::getResourceModel('newsletter/subscriber_collection');
			$oldItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('subscriber_email')); // optimisation maximale
			$oldItems->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);

			$this->updateUnsubscribersDatabase($newItems, $oldItems->getColumnValues('subscriber_email'), $diff);
			$this->moveFiles($folder, $source, $type);
			$this->writeLog($folder, $source, $diff, $cron);

			Mage::unregister('maillog_no_sync');
		}
		catch (Exception $e) {

			$error = !empty($diff['errors']) ? implode("\n", $diff['errors']) : $e->getMessage();
			$this->writeLog($folder, $source, $error, $cron);

			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}
	}


	// 7 exceptions - c'est ici que tout se joue car si tout va bien nous avons un fichier et un dossier qui sont accessibles et modifiables
	// dossier de base : inexistant, non accessible en lecture, non accessible en écriture, vide
	// fichier : non accessible en lecture, non accessible en écriture, trop vieux
	private function todayFile($folder, $type) {

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
		$files = array();

		foreach ($allfiles as $file)
			$files[filemtime($file)] = basename($file);

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
	private function dataFromFile($folder, $source, $config) {

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

		$colum = ($colum > 1) ? $colum - 1 : 1;
		$items = array();
		$lines = trim(str_replace("\xEF\xBB\xBF", '', file_get_contents($folder.$source)));

		if (mb_detect_encoding($lines, 'utf-8', true) === false)
			Mage::throwException('Sorry, the file "'.$folder.$source.'" is not an utf-8 file.');

		$lines = explode("\n", $lines);
		foreach ($lines as $line) {

			$line = trim($line);

			if (mb_strlen($line) <= 5) {
				continue;
			}
			else if ($type == 'csv') {
				$delim = ($delim == '→') ? "\t" : $delim;
				$data  = explode($delim, $line);
				if (!empty($data[$colum]) && (mb_strpos($data[$colum], '@') !== false))
					$items[] = trim(str_replace($separ, '', $data[$colum]));
			}
			else if ($type == 'txt') {
				if (mb_strpos($line, '@') !== false)
					$items[] = trim($line);
			}
		}

		return $items;
	}

	private function updateCustomersDatabase($newItems, $oldItems, &$diff) {

		$diff['oldItems']    = count($oldItems);
		$diff['newItems']    = count($newItems);
		$diff['invalidated'] = array();
		$diff['validated']   = array();

		// traitement des adresses emails AJOUTÉES
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
		// une adresse email AJOUTÉE = une adresse email devenue invalide
		// 0/No 1/Yes 2/Yes-forced-admin 3/No-forced-admin 4/No-forced-customer
		$allEmails     = array_diff($newItems, $oldItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('customer/customer_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('entity_id', 'entity_type_id', 'email')); // optimisation maximale
			$items->addAttributeToSelect('is_bounce');
			// non, car cela génère un INNER JOIN, et donc cela merde si l'attribut n'a pas de ligne dans customer_entity_int
			//$items->addAttributeToFilter('is_bounce', array('nin' => array(1, 2, 3, 4))); // donc 0/No ou null
			$items->addAttributeToFilter('email', array('in' => $emails));

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
				catch (Exception $e) {
					$diff['errors'][] = $email.' - '.$e->getMessage();
				}
			}
		}

		if (Mage::getStoreConfigFlag('maillog/bounces/subscribe')) {

			// traitement des adresses emails SUPPRIMÉES
			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			// une adresse email SUPPRIMÉE = une adresse email devenue valide
			// 0/No 1/Yes 2/Yes-forced-admin 3/No-forced-admin 4/No-forced-customer
			$allEmails     = array_diff($oldItems, $newItems);
			$chunkedEmails = array_chunk($allEmails, 1000);

			foreach ($chunkedEmails as $emails) {

				$items = Mage::getResourceModel('customer/customer_collection');
				$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('entity_id', 'entity_type_id', 'email')); // opt. maximale
				$items->addAttributeToSelect('is_bounce');
				$items->addAttributeToFilter('is_bounce', array('nin' => array(0, 2, 3, 4))); // donc 1/Yes ou null
				$items->addAttributeToFilter('email', array('in' => $emails));

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
					catch (Exception $e) {
						$diff['errors'][] = $email.' - '.$e->getMessage();
					}
				}
			}
		}
	}

	private function updateUnsubscribersDatabase($newItems, $oldItems, &$diff) {

		$diff['oldItems']     = count($oldItems);
		$diff['newItems']     = count($newItems);
		$diff['unsubscribed'] = array();
		$diff['subscribed']   = array();

		// traitement des adresses emails AJOUTÉES
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
		// une adresse email AJOUTÉE = une adresse désinscrite de la newsletter (STATUS_UNSUBSCRIBED)
		$allEmails = array_diff($newItems, $oldItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('newsletter/subscriber_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('subscriber_id', 'subscriber_email')); // optimisation maximale
			// oui, car pour être inscrit, il faut forcément une ligne dans newsletter_subscriber
			$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
			$items->addFieldToFilter('subscriber_email', array('in' => $emails));

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
				catch (Exception $e) {
					$diff['errors'][] = $email.' - '.$e->getMessage();
				}
			}
		}

		if (Mage::getStoreConfigFlag('maillog/unsubscribers/subscribe')) {

			// traitement des adresses emails SUPPRIMÉES
			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			// une adresse email SUPPRIMÉE = une adresse inscrite à la newsletter (STATUS_SUBSCRIBED)
			$allEmails = array_diff($oldItems, $newItems);
			$chunkedEmails = array_chunk($allEmails, 1000);

			foreach ($chunkedEmails as $emails) {

				$items = Mage::getResourceModel('newsletter/subscriber_collection');
				$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(array('subscriber_id', 'subscriber_email'));  // opt. maximale
				$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
				$items->addFieldToFilter('subscriber_email', array('in' => $emails));

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
					catch (Exception $e) {
						$diff['errors'][] = $email.' - '.$e->getMessage();
					}
				}
			}
		}
	}

	private function moveFiles($folder, &$source, $type) {

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

	private function writeLog($folder, $source, $diff, $cron) {

		$diff = is_string($diff) ? array('started_at' => date('Y-m-d H:i:s'), 'exception' => $diff) : $diff;

		// pour le message du cron
		if (is_object($cron)) {
			$cron->setData('messages', str_replace('    ', "\t", print_r($diff, true)));
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
			$diff['invalidated']  = !empty($diff['invalidated'])  ? count($diff['invalidated'])  : 0;
			$diff['validated']    = !empty($diff['validated'])    ? count($diff['validated'])    : 0;
		}
		if (isset($diff['unsubscribed'])) {
			$diff['unsubscribed'] = !empty($diff['unsubscribed']) ? count($diff['unsubscribed']) : 0;
			$diff['subscribed']   = !empty($diff['subscribed'])   ? count($diff['subscribed'])   : 0;
		}
		$diff['errors']      = !empty($diff['errors'])       ? count($diff['errors'])       : 0;
		$diff['finished_at'] = date('Y-m-d H:i:s');

		if (!is_dir($folder))
			mkdir($folder, 0755, true);

		file_put_contents($folder.'status.dat', serialize($diff));
	}
}