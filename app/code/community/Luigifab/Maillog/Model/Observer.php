<?php
/**
 * Created S/04/04/2015
 * Updated D/11/10/2015
 * Version 35
 *
 * Copyright 2015 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://redmine.luigifab.info/projects/magento/wiki/maillog (source cronlog)
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

	public function sendEmailReport() {

		Mage::getSingleton('core/translate')->setLocale(Mage::getStoreConfig('general/locale/code'))->init('adminhtml', true);

		$frequency = Mage::getStoreConfig('maillog/email/frequency');
		$date = Mage::getSingleton('core/locale');
		$errors = array();

		// chargement des emails de la période
		// le mois dernier (mensuel/monthly), les septs derniers jour (hebdomadaire/weekly), hier (quotidien/daily)
		if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
			$frequency = $this->__('monthly');
			$dates = $this->getDates('last_month');
		}
		else if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
			$frequency = $this->__('weekly');
			$dates = $this->getDates('last_week');
		}
		else {
			$frequency = $this->__('daily');
			$dates = $this->getDates('last_day');
		}

		// chargement des emails
		$emails = Mage::getResourceModel('maillog/email_collection');
		$emails->getSelect()->order('email_id', 'DESC');
		$emails->addFieldToFilter('created_at', array(
			'datetime' => true,
			'from' => $dates['start']->toString(Zend_Date::RFC_3339),
			'to' => $dates['end']->toString(Zend_Date::RFC_3339)
		));

		foreach ($emails as $email) {

			if (!in_array($email->getStatus(), array('error', 'pending')))
				continue;

			$link = '<a href="'.Mage::helper('adminhtml')->getUrl('adminhtml/maillog_history/view', array('id' => $email->getId())).'" style="font-weight:bold; color:red; text-decoration:none;">'.$this->__('Email %d: %s', $email->getId(), $email->getMailSubject()).'</a>';

			$hour  = $this->__('Created At: %s', $date->date($email->getCreatedAt(), Zend_Date::ISO_8601));
			$state = $this->__('Status: %s', $this->__(ucfirst($email->getStatus())));

			array_push($errors, sprintf('(%d) %s / %s / %s', count($errors) + 1, $link, $hour, $state));
		}

		// chargement des statistiques
		$dates1 = $this->getDates('last_week');
		$week = Mage::getResourceModel('maillog/email_collection');
		$week->addFieldToFilter('created_at', array(
			'datetime' => true, 'from' => $dates1['start']->toString(Zend_Date::RFC_3339), 'to' => $dates1['end']->toString(Zend_Date::RFC_3339)
		));

		$dates2 = $this->getDates('last_last_week');
		$lastweek = Mage::getResourceModel('maillog/email_collection');
		$lastweek->addFieldToFilter('created_at', array(
			'datetime' => true, 'from' => $dates2['start']->toString(Zend_Date::RFC_3339), 'to' => $dates2['end']->toString(Zend_Date::RFC_3339)
		));

		$dates3 = $this->getDates('last_month');
		$month = Mage::getResourceModel('maillog/email_collection');
		$month->addFieldToFilter('created_at', array(
			'datetime' => true, 'from' => $dates3['start']->toString(Zend_Date::RFC_3339), 'to' => $dates3['end']->toString(Zend_Date::RFC_3339)
		));

		$dates4 = $this->getDates('last_last_month');
		$lastmonth = Mage::getResourceModel('maillog/email_collection');
		$lastmonth->addFieldToFilter('created_at', array(
			'datetime' => true, 'from' => $dates4['start']->toString(Zend_Date::RFC_3339), 'to' => $dates4['end']->toString(Zend_Date::RFC_3339)
		));

		// envoi des emails
		$this->sendEmails(array(
			'frequency'        => $frequency,
			'date_period_from' => $date->date($dates['start'])->toString(Zend_Date::DATETIME_FULL),
			'date_period_to'   => $date->date($dates['end'])->toString(Zend_Date::DATETIME_FULL),
			'total_email'      => count($emails),
			'total_pending'    => count($emails->getItemsByColumnValue('status', 'pending')),
			'total_sending'    => count($emails->getItemsByColumnValue('status', 'sending')),
			'total_sent'       => count($emails->getItemsByColumnValue('status', 'sent')),
			'total_read'       => count($emails->getItemsByColumnValue('status', 'read')),
			'total_error'      => count($emails->getItemsByColumnValue('status', 'error')),
			'total_notsent'    => count($emails->getItemsByColumnValue('status', 'notsent')),
			'error_list'       => (count($errors) > 0) ? implode('</li><li style="margin:0.8em 0 0.5em;">', $errors) : '',

			'week_period'       => $date->date($dates1['start'])->toString(Zend_Date::DATE_SHORT).' - '.$date->date($dates1['end'])->toString(Zend_Date::DATE_SHORT),
			'week_total_email'  => count($week),
			'week_percent_sent' => $this->getPercent(
				$week->getItemsByColumnValue('status', 'sent'), $week->getItemsByColumnValue('status', 'read'), $week),
			'week_percent_read' => $this->getPercent(
				$week->getItemsByColumnValue('status', 'read'), $week),

			'lastweek_period'       => $date->date($dates2['start'])->toString(Zend_Date::DATE_SHORT).' - '.$date->date($dates2['end'])->toString(Zend_Date::DATE_SHORT),
			'lastweek_total_email'  => count($lastweek),
			'lastweek_percent_sent' => $this->getPercent(
				$lastweek->getItemsByColumnValue('status', 'sent'), $lastweek->getItemsByColumnValue('status', 'read'), $lastweek),
			'lastweek_percent_read' => $this->getPercent(
				$lastweek->getItemsByColumnValue('status', 'read'), $lastweek),

			'month_period'       => $date->date($dates3['start'])->toString(Zend_Date::DATE_SHORT).' - '.$date->date($dates3['end'])->toString(Zend_Date::DATE_SHORT),
			'month_total_email'  => count($month),
			'month_percent_sent' => $this->getPercent(
				$month->getItemsByColumnValue('status', 'sent'), $month->getItemsByColumnValue('status', 'read'), $month),
			'month_percent_read' => $this->getPercent(
				$month->getItemsByColumnValue('status', 'read'), $month),

			'lastmonth_period'       => $date->date($dates4['start'])->toString(Zend_Date::DATE_SHORT).' - '.$date->date($dates4['end'])->toString(Zend_Date::DATE_SHORT),
			'lastmonth_total_email'  => count($lastmonth),
			'lastmonth_percent_sent' => $this->getPercent(
				$lastmonth->getItemsByColumnValue('status', 'sent'), $lastmonth->getItemsByColumnValue('status', 'read'), $lastmonth),
			'lastmonth_percent_read' => $this->getPercent(
				$lastmonth->getItemsByColumnValue('status', 'read'), $lastmonth)
		));
	}

	public function updateConfig() {

		// EVENT admin_system_config_changed_section_maillog
		try {
			$config = Mage::getModel('core/config_data');
			$config->load('crontab/jobs/maillog_send_report/schedule/cron_expr', 'path');

			if (Mage::getStoreConfig('maillog/email/enabled') === '1') {

				// quotidien, tous les jours à 1h00 (quotidien/daily)
				// hebdomadaire, tous les lundi à 1h00 (hebdomadaire/weekly)
				// mensuel, chaque premier jour du mois à 1h00 (mensuel/monthly)
				$frequency = Mage::getStoreConfig('maillog/email/frequency');

				// minute hour day-of-month month-of-year day-of-week (Dimanche = 0, Lundi = 1...)
				// 0	     1    1            *             *           => monthly
				// 0	     1    *            *             0|1         => weekly
				// 0	     1    *            *             *           => daily
				if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY)
					$config->setValue('0 1 1 * *');
				else if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY)
					$config->setValue('0 1 * * '.Mage::getStoreConfig('general/locale/firstday'));
				else
					$config->setValue('0 1 * * *');

				$config->setPath('crontab/jobs/maillog_send_report/schedule/cron_expr');
				$config->save();

				// email de test
				$this->sendEmailReport();
			}
			else {
				$config->delete();
			}
		}
		catch (Exception $e) {
			Mage::throwException($e->getMessage());
		}
	}

	private function getDates($range) {

		$dateStart = Mage::app()->getLocale()->date();
		$dateStart->setTimezone(Mage::getStoreConfig('general/locale/timezone'));
		$dateStart->setHour(0);
		$dateStart->setMinute(0);
		$dateStart->setSecond(0);

		$dateEnd = Mage::app()->getLocale()->date();
		$dateEnd->setTimezone(Mage::getStoreConfig('general/locale/timezone'));
		$dateEnd->setHour(23);
		$dateEnd->setMinute(59);
		$dateEnd->setSecond(59);

		if ($range === 'last_month') {
			$dateStart->subMonth(1)->setDay(1);
			$dateEnd->subMonth(1)->setDay(1);
			$dateEnd->setDay(date('t', $dateEnd->getTimestamp()));
			// Évite ce genre de chose... (date(n) = numéro du mois, date(t) = nombre de jour du mois)
			// Période du dimanche 1 mars 2015 00:00:00 Europe/Paris au samedi 28 février 2015 23:59:59 Europe/Paris
			// Il est étrange que la variable dateEnd ne soit pas affectée
			if (date('n', $dateStart->getTimestamp()) === date('n', $dateEnd->getTimestamp()))
				$dateStart->subDay(date('t', $dateStart->getTimestamp()));
		}
		else if ($range === 'last_last_month') {
			$dateStart->subMonth(2)->setDay(1);
			$dateEnd->subMonth(2)->setDay(1);
			$dateEnd->setDay(date('t', $dateEnd->getTimestamp()));
			// Évite ce genre de chose... (date(n) = numéro du mois, date(t) = nombre de jour du mois)
			// Période du dimanche 1 mars 2015 00:00:00 Europe/Paris au samedi 28 février 2015 23:59:59 Europe/Paris
			// Il est étrange que la variable dateEnd ne soit pas affectée
			if (date('n', $dateStart->getTimestamp()) === date('n', $dateEnd->getTimestamp()))
				$dateStart->subDay(date('t', $dateStart->getTimestamp()));
		}
		else if ($range === 'last_week') {
			$dateStart->subDay(7);
			$dateEnd->subDay(1);
		}
		else if ($range === 'last_last_week') {
			$dateStart->subDay(14);
			$dateEnd->subDay(8);
		}
		else if ($range === 'last_day'){
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		return array('start' => $dateStart, 'end' => $dateEnd);
	}

	private function getPercent($nb1, $nb2, $nb3 = null) {

		if (!is_null($nb3))
			$percent = (count($nb3) > 0) ? (count($nb1) + count($nb2)) * 100 / count($nb3) : 0;
		else
			$percent = (count($nb2) > 0) ? count($nb1) * 100 / count($nb2) : 0;

		$percent = number_format($percent, 2);
		$percent = Zend_Locale_Format::toNumber($percent);

		return str_replace(array('.00', ',00'), '', $percent);
	}

	private function sendEmails($vars) {

		$emails = explode(' ', trim(Mage::getStoreConfig('maillog/email/recipient_email')));
		$vars['config'] = Mage::helper('adminhtml')->getUrl('adminhtml/system/config');
		$vars['config'] = substr($vars['config'], 0, strrpos($vars['config'], '/system/config'));

		foreach ($emails as $email) {

			// sendTransactional($templateId, $sender, $recipient, $name, $vars = array(), $storeId = null)
			// pièce jointe uniquement lors de la sauvegarde de la configuration
			$template = Mage::getModel('core/email_template');

			if (strpos(Mage::helper('core/url')->getCurrentUrl(), 'section/maillog') !== false) {
				$template->getMail()->createAttachment(
					gzencode(file_get_contents(realpath(dirname(__FILE__).'/../etc/tidy.conf'))),
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
				Mage::throwException($this->__('Can not send email report to %s.', $email));

			//exit($template->getProcessedTemplate($vars));
		}
	}
}