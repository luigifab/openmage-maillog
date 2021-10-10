<?php
/**
 * Created S/25/08/2018
 * Updated M/05/10/2021
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

if (PHP_SAPI != 'cli')
	exit(-1);

chdir(dirname($argv[0])); // root
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (is_file('maintenance.flag') || is_file('upgrade.flag'))
	exit(0);
if (is_file('app/bootstrap.php'))
	require_once('app/bootstrap.php');

// php .../maillog.php --dev                        => single thread  / email + data sync
// php .../maillog.php --only-sync  [--dev]         => single thread  / data sync only
// php .../maillog.php --only-email [--dev]         => single thread  / email only
// php .../maillog.php --multi      [--dev]         => multi threads* / email + data sync
// php .../maillog.php --multi --only-sync  [--dev] => multi threads* / data sync only
// php .../maillog.php --multi --only-email [--dev] => multi threads* / email only
// php .../maillog.php --all-customers [--dev]      => multi threads* / full data sync only
//   nice -n 15 su - www-data -s /bin/bash -c 'php /var/www/openmage/web/maillog.php --all-customers'
//   SELECT SUBSTRING(messages, 1, 15) AS msg FROM cron_schedule WHERE job_code LIKE "maillog_cron_fullsync%";

$isDev   = isset($_SERVER['MAGE_IS_DEVELOPER_MODE']) || in_array('--dev', $argv);
$isEmail = !in_array('--only-sync', $argv);
$isSync  = !in_array('--only-email', $argv);
$isMulti = in_array('--multi', $argv);
$isFull  = in_array('--all-customers', $argv);
$runNumb = (int) end($argv);

require_once('app/Mage.php');

Mage::app('admin')->setUseSessionInUrl(false);
Mage::app()->addEventArea('crontab');
Mage::setIsDeveloperMode($isDev);

exec('nproc', $core);
$core = max(1, (int) trim(implode($core)));
$stop = Mage::getBaseDir('var').'/maillog.stop';


if ($isDev) {
	Mage::log('dev:'.($isDev ? 'true' : 'false').
		' email:'.($isEmail ? 'true' : 'false').
		' sync:'.($isSync ? 'true' : 'false').
		' multi:'.($isMulti ? 'true' : 'false').
		' full:'.($isFull ? 'true' : 'false').
		' run:'.($runNumb ?: 'false'),
	Zend_Log::INFO);
}

if (is_file($stop)) {
	echo 'Stop file is here: ',$stop,' - Exiting now.',"\n";
	exit(-1);
}

if (empty($runNumb) && $isFull) {
	echo 'Starting full sync in 25 seconds...',"\n";
	echo 'You can cancel it at any moment by creating the stop file: '.$stop,"\n";
	file_put_contents($stop, 'checking');
	sleep(25);
	unlink($stop);
	echo 'Go!',"\n";
}


// mode multi threads
// dans tous les cas s'il y en a plus de 500 à faire, laisse toujours 3 CPU de libre
if (empty($runNumb) && ($isMulti || $isFull)) {

	$core = max(1, $core - 3);
	$pids = [];
	$cmds = [];

	// multi threads
	// passe en single thread si nécessaire
	if ($isFull) {

		$runNumbSyncs = ceil(Mage::getResourceModel('customer/customer_collection')->getSize() / 5000);
		if ($runNumbSyncs > 1) {

			while ($runNumbSyncs > 0) {
				$cmds[] = PHP_BINARY.' '.getcwd().'/'.basename($argv[0]).' --all-customers'.($isDev ? ' --dev ' : ' ').$runNumbSyncs;
				$runNumbSyncs--;
			}
		}

		$isEmail = false;
		$isSync  = false;
	}
	else {
		$runNumbEmails = $isEmail ? ceil(Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('status', 'pending')
			->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 SECOND)')])
			->getSize() / 500) : 0;

		$runNumbSyncs = $isSync ? ceil(Mage::getResourceModel('maillog/sync_collection')
			->addFieldToFilter('status', 'pending')
			->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 SECOND)')])
			->getSize() / 500) : 0;

		// prépare les commmandes
		if (($runNumbEmails > 1) || ($runNumbSyncs > 1)) {

			while ($runNumbEmails > 0) {
				$cmds[] = PHP_BINARY.' '.getcwd().'/'.basename($argv[0]).' --only-email'.($isDev ? ' --dev ' : ' ').$runNumbEmails;
				$runNumbEmails--;
			}
			while ($runNumbSyncs > 0) {
				$cmds[] = PHP_BINARY.' '.getcwd().'/'.basename($argv[0]).' --only-sync'.($isDev ? ' --dev ' : ' ').$runNumbSyncs;
				$runNumbSyncs--;
			}

			$isEmail = false;
			$isSync  = false;
		}
	}

	// exécute les threads
	// s'il y en a pas, c'est qu'on est passé en single thread
	if (!empty($cmds)) {

		while (!empty($cmds)) {

			if (is_file($stop))
				break;

			$pids[] = exec($cmd = array_shift($cmds).' >/dev/null 2>&1 & echo $!');
			while (count($pids) >= $core) {
				sleep(10);
				foreach ($pids as $key => $pid) {
					if (!file_exists('/proc/'.$pid))
						unset($pids[$key]);
					else
						clearstatcache('/proc/'.$pid);
				}
			}
		}

		while (count($pids) > 0) {
			sleep(10);
			foreach ($pids as $key => $pid) {
				if (!file_exists('/proc/'.$pid))
					unset($pids[$key]);
				else
					clearstatcache('/proc/'.$pid);
			}
		}
	}
	else {
		$isMulti = false;
	}
}


// mode single thread ou exécution des threads
if (!$isMulti && !$isFull && $isEmail) {

	if (Mage::getIsDeveloperMode())
		Mage::log('run email ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

	$job = Mage::getModel('cron/schedule');
	$job->setData('job_code', ($runNumb > 0) ? 'maillog_cron_email_'.$runNumb : 'maillog_cron_email');
	$job->setData('created_at', date('Y-m-d H:i:s'));
	$job->setData('scheduled_at', date('Y-m-d H:i:s'));
	$job->setData('executed_at', date('Y-m-d H:i:s'));
	$job->setData('status', 'running');

	$results = sendEmails($job, $runNumb);

	if (is_file($stop)) {
		$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
		$results['error'][] = $msg;
		if (Mage::getIsDeveloperMode())
			Mage::log($msg, Zend_Log::INFO, 'maillog.log');
	}

	saveCron($job, $results, true);
}

if (!$isMulti && !$isFull && $isSync) {

	if (Mage::getIsDeveloperMode())
		Mage::log('run sync ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

	$job = Mage::getModel('cron/schedule');
	$job->setData('job_code', ($runNumb > 0) ? 'maillog_cron_sync_'.$runNumb : 'maillog_cron_sync');
	$job->setData('created_at', date('Y-m-d H:i:s'));
	$job->setData('scheduled_at', date('Y-m-d H:i:s'));
	$job->setData('executed_at', date('Y-m-d H:i:s'));
	$job->setData('status', 'running');

	$results = sendSyncs($job, $runNumb);

	if (is_file($stop)) {
		$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
		$results['error'][] = $msg;
		if (Mage::getIsDeveloperMode())
			Mage::log($msg, Zend_Log::INFO, 'maillog.log');
	}

	saveCron($job, $results, true);
}

if (!$isMulti && $isFull) {

	if (Mage::getIsDeveloperMode())
		Mage::log('run full sync ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

	ini_set('memory_limit', '1G');

	$job = Mage::getModel('cron/schedule');
	$job->setData('job_code', ($runNumb > 0) ? 'maillog_cron_fullsync_'.$runNumb : 'maillog_cron_fullsync');
	$job->setData('created_at', date('Y-m-d H:i:s'));
	$job->setData('scheduled_at', date('Y-m-d H:i:s'));
	$job->setData('executed_at', date('Y-m-d H:i:s'));
	$job->setData('status', 'running');

	$results = fullSync($job, $runNumb);

	if (is_file($stop)) {
		$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
		$results['error'][] = $msg;
		if (Mage::getIsDeveloperMode())
			Mage::log($msg, Zend_Log::INFO, 'maillog.log');
	}

	saveCron($job, $results, true);
}


// action
function sendEmails(object $job, int $page) {

	$results = ['success' => [], 'error' => []];

	$count  = 0;
	$stop   = Mage::getBaseDir('var').'/maillog.stop';
	$emails = Mage::getResourceModel('maillog/email_collection')
		->addFieldToFilter('status', 'pending')
		->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 SECOND)')])
		->setOrder('email_id', 'asc');

	if ($page > 0)
		$emails->setPageSize(500)->setCurPage($page);

	foreach ($emails as $email) {

		if (is_file($stop))
			break;

		try {
			$email->sendNow();
			$results['success'][] = 'email:'.$email->getId();
		}
		catch (Throwable $t) {
			$email->setData('status', 'error')->save();
			$results['error'][] = 'email:'.$email->getId().' '.$t->getMessage();
			if (Mage::getIsDeveloperMode())
				Mage::logException($t);
		}

		if ((++$count % 100) == 0) {
			saveCron($job, $results, false);
		}
		else if ($count == 1) {
			$job->save();
		}
	}

	return $results;
}

function sendSyncs(object $job, int $page) {

	$results = ['success' => [], 'error' => []];

	$count = 0;
	$stop  = Mage::getBaseDir('var').'/maillog.stop';
	$syncs = Mage::getResourceModel('maillog/sync_collection')
		->addFieldToFilter('status', 'pending')
		->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 SECOND)')])
		->setOrder('created_at', 'asc');

	if ($page > 0)
		$syncs->setPageSize(500)->setCurPage($page);

	foreach ($syncs as $sync) {

		if (is_file($stop))
			break;

		try {
			// 0 method_name (update) : 1 object_type (customer) : 2 object_id : 3 old-email : 4 email
			// 0 method_name (update) : 1 object_type (customer) : 2 object_id : 3           : 4 email
			$info = (array) explode(':', $sync->getData('action')); // (yes)
			if (method_exists($sync, $info[0].'Now')) {
				$sync->{$info[0].'Now'}();
				$results['success'][] = 'sync:'.$sync->getId();
			}
			else {
				Mage::throwException('Unknown method_name ('.get_class($sync).'::'.$info[0] .'[Now]).');
			}
		}
		catch (Throwable $t) {
			$sync->setData('status', 'error')->save();
			$results['error'][] = 'sync:'.$sync->getId().' '.$t->getMessage();
			if (Mage::getIsDeveloperMode())
				Mage::logException($t);
		}

		if ((++$count % 100) == 0) {
			saveCron($job, $results, false);
		}
		else if ($count == 1) {
			$job->save();
		}
	}

	return $results;
}

function fullSync(object $job, int $page) {

	$results  = ['success' => [], 'error' => []];
	$systems  = array_keys(Mage::helper('maillog')->getSystems());
	$customer = Mage::getModel('customer/customer');

	$attributes = [];
	foreach ($systems as $system)
		$attributes = array_merge($attributes, Mage::helper('maillog')->getSystem($system)->mapFields($customer, 'customer', true));

	$count = 0;
	$stop  = Mage::getBaseDir('var').'/maillog.stop';
	$customers = Mage::getResourceModel('customer/customer_collection')
		->addAttributeToSelect($attributes)
		->setOrder('entity_id', 'asc');

	if ($page > 0)
		$customers->setPageSize(5000)->setCurPage($page);

	foreach ($customers as $customer) {

		if (is_file($stop))
			break;

		foreach ($systems as $system) {

			try {
				$sync = Mage::getModel('maillog/sync');
				$sync->setData('created_at', date('Y-m-d H:i:s'));
				$sync->setData('action', 'update:customer:'.$customer->getId().'::');
				$sync->setData('model', $system);
				$sync->setData('user', 'allsync');
				$sync->setData('status', 'running');
				$sync->save();
				$sync->updateNow($customer);
				$sync->save();
				$results['success'][] = 'sync:'.$sync->getId();
			}
			catch (Throwable $t) {
				$sync->setData('status', 'error')->save();
				$results['error'][] = 'sync:'.$sync->getId().' '.$t->getMessage();
				if (Mage::getIsDeveloperMode())
					Mage::logException($t);
			}
		}

		if ((++$count % 100) == 0) {
			saveCron($job, $results, false);
		}
		else if ($count == 1) {
			$job->save();
		}
	}

	return $results;
}

function saveCron(object $job, array $results, bool $end) {

	if (!empty($results['success']) || !empty($results['error'])) {

		$textok = trim(str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '', print_r($results['success'], true))));
		$textko = trim(str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '', print_r($results['error'], true))));

		if ($end) {
			$job->setData('finished_at', date('Y-m-d H:i:s'));
			$job->setData('status', empty($results['error']) ? Mage_Cron_Model_Schedule::STATUS_SUCCESS : Mage_Cron_Model_Schedule::STATUS_ERROR);
		}

		$job->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).' M'."\n".'success: '.$textok."\n".'error: '.$textko);
		$job->save();
	}
}


exit(0);