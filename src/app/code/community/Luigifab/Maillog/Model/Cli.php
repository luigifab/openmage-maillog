<?php
/**
 * Created S/25/08/2018
 * Updated S/25/11/2023
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

class Luigifab_Maillog_Model_Cli {

	public function sendEmails(object $job, string $stop, int $page) {

		$results = ['success' => [], 'error' => []];
		$emails  = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('status', 'pending')
			->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 SECOND)')])
			->setOrder('email_id', 'asc');

		$count = 0;
		if ($page > 0)
			$emails->setPageSize(500)->setCurPage($page);

		foreach ($emails as $email) {

			if (is_file($stop))
				break;

			try {
				$email->sendNow(); // and save
				$results['success'][] = 'email:'.$email->getId();
			}
			catch (Throwable $t) {
				try {
					$email->setData('status', 'error');
					$email->setData('exception', $email->formatException($t));
					$email->save();
					$results['error'][] = 'email:'.$email->getId().' '.$t->getMessage();
				}
				catch (Throwable $tt) {
					Mage::logException($tt);
				}
			}

			if ((++$count % 100) == 0)
				$this->saveJob($job, $results, false);
			else if ($count == 1)
				$job->save();
		}

		return $results;
	}

	public function sendSyncs(object $job, string $stop, int $page) {

		$systems = Mage::helper('maillog')->getSystem();
		if (empty($systems))
			return ['success' => [], 'error' => ['No sync systems enabled!']];

		$results = ['success' => [], 'error' => []];
		$syncs   = Mage::getResourceModel('maillog/sync_collection')
			->addFieldToFilter('status', 'pending')
			->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 SECOND)')])
			->setOrder('created_at', 'asc');

		$count = 0;
		if ($page > 0)
			$syncs->setPageSize(500)->setCurPage($page);

		foreach ($syncs as $sync) {

			if (is_file($stop))
				break;

			try {
				// 0 method_name (update) : 1 object_type (customer) : 2 object_id : 3 old-email : 4 email
				// 0 method_name (update) : 1 object_type (customer) : 2 object_id : 3           : 4 email
				$info = explode(':', $sync->getData('action'));
				if (method_exists($sync, $info[0].'Now')) {
					$sync->{$info[0].'Now'}($systems[$sync->getData('model')]); // and save
					$results['success'][] = 'sync:'.$sync->getId();
				}
				else {
					Mage::throwException('Unknown method ('.get_class($sync).'::'.$info[0] .'[Now]).');
				}
			}
			catch (Throwable $t) {
				try {
					$sync->setData('status', 'error');
					$sync->setData('exception', $sync->formatException($t));
					$sync->save();
					$results['error'][] = 'sync:'.$sync->getId().' '.$t->getMessage();
				}
				catch (Throwable $tt) {
					Mage::logException($tt);
				}
			}

			if ((++$count % 100) == 0)
				$this->saveJob($job, $results, false);
			else if ($count == 1)
				$job->save();
		}

		return $results;
	}

	public function fullSync(object $job, string $stop, int $page) {

		$systems = Mage::helper('maillog')->getSystem();
		if (empty($systems))
			return ['success' => [], 'error' => ['No sync systems enabled!']];

		Mage::register('maillog_full_sync', true, true);

		$customer   = Mage::getModel('customer/customer');
		$attributes = ['default_shipping', 'default_billing'];
		foreach ($systems as $system)
			$attributes = array_merge($attributes, $system->mapFields($customer, 'customer', true));

		$results   = ['success' => [], 'error' => []];
		$customers = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect($attributes)
			->setOrder('entity_id', 'asc');

		$count = 0;
		if ($page > 0)
			$customers->setPageSize(5000)->setCurPage($page);

		foreach ($customers as $customer) {

			if (is_file($stop))
				break;

			foreach ($systems as $system) {

				try {
					$sync = Mage::getModel('maillog/sync');
					$sync->setData('created_at', date('Y-m-d H:i:s'));
					$sync->setData('action', 'update:customer:'.$customer->getId().'::'.$customer->getData('email'));
					$sync->setData('model', $system->getCode());
					$sync->setData('user', 'allsync');
					$sync->updateNow($system, $customer); // and save
					$results['success'][] = 'sync:'.$sync->getId();
				}
				catch (Throwable $t) {
					try {
						$sync->setData('status', 'error');
						$sync->setData('exception', $sync->formatException($t));
						$sync->save();
						$results['error'][] = 'sync:'.$sync->getId().' '.$t->getMessage();
					}
					catch (Throwable $tt) {
						Mage::logException($tt);
					}
				}
			}

			if ((++$count % 100) == 0)
				$this->saveJob($job, $results, false);
			else if ($count == 1)
				$job->save();
		}

		return $results;
	}

	public function saveJob(object $job, array $results, bool $end, bool $full = false) {

		if (!empty($results['success']) || !empty($results['error'])) {

			$textok = trim(str_replace(
				['    ', ' => Array', "\n\n"],
				[' ', '', "\n"],
				preg_replace('#\s+[()]#', '', print_r($results['success'], true))
			));

			$textko = trim(str_replace(
				['    ', ' => Array', "\n\n"],
				[' ', '', "\n"],
				preg_replace('#\s+[()]#', '', print_r($results['error'], true))
			));

			if ($end) {
				$job->setData('finished_at', date('Y-m-d H:i:s'));
				$job->setData('status', empty($results['error']) ? 'success' : 'error');
			}

			$job->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n".'success: '.$textok."\n".'error: '.$textko);
			$job->save();
		}
		else if ($end && $full) {
			$job->setData('finished_at', date('Y-m-d H:i:s'));
			$job->setData('status', 'error');
			$job->setData('messages', 'nothing to do');
			$job->save();
		}

		return $this;
	}
}