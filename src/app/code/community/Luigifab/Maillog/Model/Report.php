<?php
/**
 * Created S/04/04/2015
 * Updated D/17/12/2023
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

class Luigifab_Maillog_Model_Report extends Luigifab_Maillog_Model_Observer {

	// CRON maillog_send_report
	public function send($cron = null, bool $test = false, bool $preview = false) {

		$frequency = Mage::getStoreConfig('maillog/email/frequency');
		$oldLocale = Mage::getSingleton('core/translate')->getLocale();
		$newLocale = Mage::app()->getStore()->isAdmin() ? $oldLocale : Mage::getStoreConfig('general/locale/code');
		$locales   = [];

		// search locales and emails
		$data = Mage::getStoreConfig('maillog/email/recipient_email');
		if ($preview) {
			$locales = [$oldLocale => ['hack@example.org']];
		}
		else if (!empty($data) && ($data != 'a:0:{}')) {
			$data = @unserialize($data, ['allowed_classes' => false]);
			if (!empty($data)) {
				foreach ($data as $datum) {
					if (!in_array($datum['email'], ['hello@example.org', 'hello@example.com', '']))
						$locales[empty($datum['locale']) ? $newLocale : $datum['locale']][] = $datum['email'];
				}
			}
		}

		// generate and send the report
		// @todo on recalcul tout alors qu'il ne faudrait pas
		// @todo nouvelle méthode pour la génération du contenu de l'email
		foreach ($locales as $locale => $recipients) {

			if (!$preview)
				Mage::getSingleton('core/translate')->setLocale($locale)->init('adminhtml', true);

			// recherche des dates
			if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
				$text  = $this->_('monthly');
				$dates = $this->getDateRange('month');
			}
			else if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
				$text  = $this->_('weekly');
				$dates = $this->getDateRange('week');
			}
			else {
				$text  = $this->_('daily');
				$dates = $this->getDateRange('day');
			}

			// chargement des emails
			// optimisation maximale de manière à ne charger que le nécessaire (pas le contenu des emails)
			$emails = Mage::getResourceModel('maillog/email_collection');
			$emails->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['email_id', 'status', 'mail_subject', 'created_at']); // opt. maximale
			$emails->addFieldToFilter('created_at', [
				'datetime' => true,
				'from' => $dates['start']->toString(Zend_Date::RFC_3339),
				'to'   => $dates['end']->toString(Zend_Date::RFC_3339),
			]);
			$emails->setOrder('email_id', 'desc');

			$errors = [];
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
				'frequency'            => $text,
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
				'sync'                 => Mage::getStoreConfigFlag('maillog_sync/general/enabled'),
			];

			// chargement des statistiques des emails et des synchronisations
			// optimisation maximale de manière à ne faire que des COUNT en base de données
			$emails = Mage::getResourceModel('maillog/email_collection');
			$syncs  = Mage::getResourceModel('maillog/sync_collection');
			$val    = Mage::getStoreConfig('maillog_sync/general/lifetime');

			foreach (['week_' => true, 'month_' => false] as $key => $isWeek) {

				// n'affiche plus la semaine et le mois en cours
				for ($i = 2; $i <= 14; $i++) {

					$dates = $this->getDateRange($isWeek ? 'week' : 'month', $i - 1);
					$where = [
						'datetime' => true,
						'from' => $dates['start']->toString(Zend_Date::RFC_3339),
						'to'   => $dates['end']->toString(Zend_Date::RFC_3339),
					];

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
					$vars['items'][$i][$key.'total_email']  = $this->calcNumber($where, $emails);
					$vars['items'][$i][$key.'percent_sent'] = $this->calcNumber($where, $emails, ['in' => ['sent', 'read']]);
					$vars['items'][$i][$key.'percent_read'] = $this->calcNumber($where, $emails, ['in' => ['sent', 'read']], 'read');

					if (!empty($val))
						$where['gteq'] = new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$val.' MINUTE)');

					$vars['items'][$i][$key.'total_sync']   = $this->calcNumber($where, $syncs);
					$vars['items'][$i][$key.'percent_sync'] = $this->calcNumber($where, $syncs, 'success');
				}
			}

			$html = $this->sendEmailToRecipients($locale, $recipients, $vars, $test, $preview);
			if ($preview)
				return $html;
		}

		Mage::getSingleton('core/translate')->setLocale($oldLocale)->init('adminhtml', true);

		if (is_object($cron))
			$cron->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n".print_r($locales, true));

		return $locales;
	}

	protected function calcNumber(array $where, object $collection, $status1 = null, $status2 = null) {

		if (!empty($where['gteq'])) {
			$gteq = ['gteq' => $where['gteq']];
			unset($where['gteq']);
		}

		// avec des resets sinon le where est conservé avec le clone
		if (empty($status1)) {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			if (!empty($gteq))
				$data->addFieldToFilter('created_at', $gteq);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->getSize(); // totalité
		}
		else if (empty($status2)) {

			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			if (!empty($gteq))
				$data->addFieldToFilter('created_at', $gteq);
			$data->addFieldToFilter('created_at', $where);
			$nb1 = $data->getSize(); // totalité

			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			if (!empty($gteq))
				$data->addFieldToFilter('created_at', $gteq);
			$data->addFieldToFilter('created_at', $where);
			$data->addFieldToFilter('status', $status1);
			$nb2 = $data->getSize(); // filtré
		}
		else {
			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			if (!empty($gteq))
				$data->addFieldToFilter('created_at', $gteq);
			$data->addFieldToFilter('created_at', $where);
			$data->addFieldToFilter('status', $status1);
			$nb1 = $data->getSize(); // totalité

			$data = clone $collection;
			$data->getSelect()->reset(Zend_Db_Select::WHERE);
			if (!empty($gteq))
				$data->addFieldToFilter('created_at', $gteq);
			$data->addFieldToFilter('created_at', $where);
			$data->addFieldToFilter('status', $status2);
			$nb2 = $data->getSize(); // filtré
		}

		// nombre d'email/sync ou pourcentage
		return empty($status1) ? $nb1 : floor(($nb1 > 0) ? ($nb2 * 100) / $nb1 : 0);
	}

	protected function getDateRange(string $range, int $coef = 1) {

		$dateStart = Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->setSecond(0);
		$dateEnd   = Mage::getSingleton('core/locale')->date()->setHour(23)->setMinute(59)->setSecond(59);

		// de 1 (pour Lundi) à 7 (pour Dimanche)
		// permet d'obtenir des semaines du lundi au dimanche
		$day = $dateStart->toString(Zend_Date::WEEKDAY_8601) - 1;

		if ($range == 'month') {
			$dateStart->setDay(3)->subMonth($coef)->setDay(1);
			$dateEnd->setDay(3)->subMonth($coef)->setDay($dateEnd->toString(Zend_Date::MONTH_DAYS));
		}
		else if ($range == 'week') {
			$dateStart->subDay($day + 7 * $coef);
			$dateEnd->subDay($day + 7 * $coef - 6);
		}
		else if ($range == 'day') {
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		return ['start' => $dateStart, 'end' => $dateEnd];
	}

	protected function getEmailUrl(string $url, array $params = []) {

		if (Mage::getStoreConfigFlag('web/seo/use_rewrites'))
			return preg_replace('#/[^/]+\.php\d*/#', '/', Mage::helper('adminhtml')->getUrl($url, $params));

		return preg_replace('#/[^/]+\.php(\d*)/#', '/index.php$1/', Mage::helper('adminhtml')->getUrl($url, $params));
	}

	protected function sendEmailToRecipients(string $locale, array $emails, array $vars = [], bool $test = false, bool $preview = false) {

		$vars['config'] = $this->getEmailUrl('adminhtml/system/config');
		$vars['config'] = mb_substr($vars['config'], 0, mb_strrpos($vars['config'], '/system/config'));
		$sender = Mage::getStoreConfig('maillog/email/sender_email_identity');

		foreach ($emails as $email) {

			$template = Mage::getModel('core/email_template');
			$template->setDesignConfig(['store' => null]);
			$template->loadDefault('maillog_email_template', $locale);

			if ($preview)
				return $template->getProcessedTemplate($vars);

			if ($test) {
				$template->getMail()->createAttachment(
					gzencode(file_get_contents(Mage::getModuleDir('etc', 'Luigifab_Maillog').'/tidy.conf'), 9),
					'application/x-gzip',
					Zend_Mime::DISPOSITION_ATTACHMENT,
					Zend_Mime::ENCODING_BASE64,
					'tidy.gz'
				);
				if (Mage::getIsDeveloperMode()) {
					$isMaster = str_contains($email, '@luigifab.fr');
					$isGmail  = str_contains($email, '@gmail.com');
					if ($isMaster || $isGmail) {
						$template->getMail()
							->addTo($isMaster ? 'test1@luigifab.fr' : str_replace('@', '+to1@', $email), 'Test addTo1')
							->addTo($isMaster ? 'test2@luigifab.fr' : str_replace('@', '+to2@', $email), 'Têst addTo2')
							->addCc($isMaster ? 'test3@luigifab.fr' : str_replace('@', '+cc1@', $email), 'Test addCc1')
							->addCc($isMaster ? 'test4@luigifab.fr' : str_replace('@', '+cc2@', $email), 'Têst addCc2')
							->addBcc($isMaster ? 'test5@luigifab.fr' : str_replace('@', '+bcc1@', $email), 'Test addBcc1')
							->addBcc($isMaster ? 'test6@luigifab.fr' : str_replace('@', '+bcc2@', $email), 'Têst addBcc2');
					}
				}
			}

			$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name'));
			$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email'));
			$template->setSentSuccess($template->send($email, null, $vars));
			//exit($template->getProcessedTemplate($vars));

			if (!$template->getSentSuccess())
				Mage::throwException($this->__('Can not send the report by email to %s.', $email));
		}

		return true;
	}
}