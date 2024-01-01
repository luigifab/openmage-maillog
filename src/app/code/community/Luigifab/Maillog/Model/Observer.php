<?php
/**
 * Created S/04/04/2015
 * Updated V/22/12/2023
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

class Luigifab_Maillog_Model_Observer extends Luigifab_Maillog_Helper_Data {

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

			$order    = $observer->getData('invoice')->getOrder();
			$customer = new Varien_Object(['id' => $order->getData('customer_id'), 'email' => $order->getData('customer_email')]);

			if (!empty($customer->getId()))
				$this->sendSync($customer, 'customer', 'email', 'update');
		}
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


	// EVENT admin_system_config_changed_section_maillog (adminhtml)
	// EVENT admin_system_config_changed_section_maillog_sync (adminhtml)
	public function updateConfig() {

		// suppression des anciens emails
		// suppression des anciennes synchros
		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/maillog_clean_old_data/schedule/cron_expr', 'path');

		$check = $this->getConfigUnserialized('maillog/general/special_config');
		foreach ($check as $value) {
			if (is_numeric($value)) {
				$check = true;
				break;
			}
		}

		if (($check === true) || !empty(Mage::getStoreConfig('maillog_sync/general/lifetime'))) {
			$config->setData('value', '30 2 * * *');
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

			// test email
			if (!empty(Mage::app()->getRequest()->getPost('maillog_email_test')))
				Mage::getSingleton('maillog/report')->send(null, true);
		}
		else {
			$config->delete();
		}

		Mage::getConfig()->reinit();
	}

	// EVENT admin_system_config_changed_section_maillog_sync (adminhtml)
	public function clearConfig(Varien_Event_Observer $observer) {

		$database = Mage::getSingleton('core/resource');
		$writer   = $database->getConnection('core_write');
		$table    = $database->getTableName('core_config_data');
		$codes    = array_keys(Mage::getConfig()->getNode('global/models/maillog/adaptators')->asArray());

		foreach ($codes as $code) {
			if (Mage::getStoreConfigFlag('maillog_sync/general/remove_'.$code)) {
				$writer->query('DELETE FROM '.$table.' WHERE path LIKE "maillog_sync/'.$code.'/%" AND path NOT LIKE "maillog_sync/'.$code.'/enabled"');
				$writer->query('DELETE FROM '.$table.' WHERE path LIKE "maillog_sync/'.$code.'/enabled" AND scope_id != 0');
				Mage::getModel('core/config')->saveConfig('maillog_sync/'.$code.'/enabled', '0');
			}
		}

		Mage::getConfig()->reinit();
	}

	// EVENT admin_system_config_changed_section_maillog_directives (adminhtml)
	public function clearCache(Varien_Event_Observer $observer) {

		if (Mage::getStoreConfigFlag('maillog_directives/general/remove_unused_sizes')) {

			$dirSizes = [];
			$dirs = glob(Mage::getBaseDir('media').'/catalog/product/cache/*/*x*');
			foreach ($dirs as $dir) {
				$name = explode('x', basename($dir));
				if ((count($name) == 2) && is_numeric($name[0]) && (is_numeric($name[1]) || empty($name[1])))
					$dirSizes[implode('x', $name)][] = $dir;
			}

			$dirs = glob(Mage::getBaseDir('media').'/catalog/category/cache/*x*');
			foreach ($dirs as $dir) {
				$name = explode('x', basename($dir));
				if ((count($name) == 2) && is_numeric($name[0]) && (is_numeric($name[1]) || empty($name[1])))
					$dirSizes[implode('x', $name)][] = $dir;
			}

			$dirs = glob(Mage::getBaseDir('media').'/wysiwyg/cache/*x*');
			foreach ($dirs as $dir) {
				$name = explode('x', basename($dir));
				if ((count($name) == 2) && is_numeric($name[0]) && (is_numeric($name[1]) || empty($name[1])))
					$dirSizes[implode('x', $name)][] = $dir;
			}

			$newSizes = [];
			$config = $this->getConfigUnserialized('maillog_directives/general/special_config');
			foreach ($config as $values) {
				foreach ($values as $sizes) {
					if (is_array($sizes)) {
						if (empty($sizes['w'])) {
							$newSizes[] = 'x'.$sizes['h'];
							$newSizes[] = 'x'.($sizes['h'] * 2);
						}
						else if (empty($sizes['h'])) {
							$newSizes[] = $sizes['w'].'x';
							$newSizes[] = ($sizes['w'] * 2).'x';
						}
						else {
							$newSizes[] = $sizes['w'].'x'.$sizes['h'];
							$newSizes[] = ($sizes['w'] * 2).'x'.($sizes['h'] * 2);
						}
					}
				}
			}

			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			$deleteSizes = array_diff(array_keys($dirSizes), $newSizes);
			foreach ($deleteSizes as $deleteSize) {
				if (!empty($dirSizes[$deleteSize])) {
					$cmd = 'rm -rf '.implode(' ', array_map('escapeshellarg', $dirSizes[$deleteSize]));
					Mage::log($cmd, Zend_Log::DEBUG, 'maillog.log');
					exec($cmd);
					Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('maillog')->__('Directories for unused size %s was removed.', $deleteSize));
				}
			}
		}

		Mage::app()->cleanCache();
		Mage::dispatchEvent('adminhtml_cache_flush_system');
		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('The OpenMage cache has been flushed and updates applied.'));
	}

	// EVENT adminhtml_init_system_config (adminhtml)
	public function hideConfig(Varien_Event_Observer $observer) {

		if (Mage::app()->getRequest()->getParam('section') == 'maillog_sync') {

			$nodes = $observer->getData('config')->getNode('sections/maillog_sync/groups')->children();
			$codes = array_keys(Mage::getConfig()->getNode('global/models/maillog/adaptators')->asArray());

			foreach ($codes as $code) {
				if (!empty($nodes->{$code}) && Mage::getStoreConfigFlag('maillog_sync/general/remove_'.$code)) {
					$nodes->{$code}->show_in_default = 0;
					$nodes->{$code}->show_in_website = 0;
					$nodes->{$code}->show_in_store = 0;
				}
			}
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
	// met à jour le store_id de l'abonné lors de l'enregistrement d'un abonné (se base sur le store_id du client)
	// met à jour change_status_at qui ne semble pas fonctionner non plus
	// actif même si le module n'est pas activé
	public function setSubscriberStoreId(Varien_Event_Observer $observer) {

		$subscriber = $observer->getData('subscriber');
		$customer   = Mage::registry('current_customer');

		if ($subscriber->getIsStatusChanged())
			$subscriber->setChangeStatusAt(date('Y-m-d H:i:s'));

		if (is_object($customer) && ($subscriber->getStoreId() != $customer->getStoreId()))
			$subscriber->setData('store_id', $customer->getStoreId());

		if (is_object($customer) && ($subscriber->getData('subscriber_email') != $customer->getOrigData('email')))
			$subscriber->setOrigData('subscriber_email', $customer->getOrigData('email'));
	}


	// CRON maillog_clean_old_data
	public function cleanOldData($cron = null) {

		$msg = [];
		$all = 0;

		$val = Mage::getStoreConfig('maillog_sync/general/lifetime');
		if (!empty($val) && is_numeric($val)) {

			$syncs = Mage::getResourceModel('maillog/sync_collection')
				->addFieldToFilter('status', ['eq' => 'success'])
				->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$val.' MINUTE)')]);
			$cnt = $syncs->getSize();
			$syncs->deleteAll();

			$syncs = Mage::getResourceModel('maillog/sync_collection')
				->addFieldToFilter('status', ['neq' => 'success'])
				->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.(3 * $val).' MINUTE)')]);
			$cnt += $syncs->getSize();
			$syncs->deleteAll();

			$msg[] = 'Remove successful synchronizations after '.($val / 60 / 24).' days';
			$msg[] = empty($cnt) ? ' → no items to remove' : ' → '.$cnt.' item(s) removed';
			$msg[] = '';
		}

		$config = $this->getConfigUnserialized('maillog/general/special_config');
		if (!empty($config)) {

			//   $key = $type_$action
			// action = data (Emails content and attachments) or all (All emails data)
			foreach ($config as $key => $months) {

				if (is_numeric($months) && ($months >= 1)) {

					$cut    = mb_stripos($key, '_');
					$type   = mb_substr($key, 0, $cut);
					$action = mb_substr($key, $cut + 1);

					// @deprecated
					if ($type == 'without')
						continue;

					$emails = Mage::getResourceModel('maillog/email_collection')
						->addFieldToFilter('deleted', ['neq' => 1])
						->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$months.' MONTH)')]);
					$emails->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['email_id']); // optimisation maximale

					// tous les emails
					//     type = all
					// all_data = Emails content and attachments
					// all_all  = All emails data
					if ($key == 'all_data') {
						$emails->addFieldToFilter('deleted', 0);
						$msg[] = 'Remove content and attachments for '.$type.' emails after '.$months.' months';
					}
					else if ($key == 'all_all') {
						$msg[] = 'Remove ALL data for '.$type.' emails after '.$months.' months';
					}
					// les emails avec un type
					//     type = xyz
					// xyz_data = Emails content and attachments
					// xyz_all  = All emails data
					else if ($action == 'data') {
						$emails->addFieldToFilter('deleted', 0);
						$emails->addFieldToFilter('type', $type);
						$msg[] = 'Remove content and attachments for '.$type.' emails after '.$months.' months';
					}
					else if ($action == 'all') {
						$emails->addFieldToFilter('type', $type);
						$msg[] = 'Remove ALL data for '.$type.' emails after '.$months.' months';
					}

					// action
					// supprime l'email en partie ou entièrement
					$cnt = $emails->getSize();
					$msg[] = empty($cnt) ? ' → no items to remove' : ' → '.$cnt.' item(s) removed';
					$msg[] = '';

					if ($cnt > 0) {
						$all += $cnt;
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

			if ($all > 100) {
				// mysqltuner: can free 8823 MB
				$database = Mage::getSingleton('core/resource');
				$database->getConnection('core_write')->query('OPTIMIZE TABLE '.$database->getTableName('luigifab_maillog'));
			}
		}

		if (is_object($cron))
			$cron->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n\n".trim(implode("\n", $msg)));

		return $msg;
	}
}