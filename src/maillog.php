<?php
/**
 * Created S/25/08/2018
 * Updated V/19/05/2023
 *
 * Copyright 2015-2023 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

chdir(dirname($argv[0])); // root
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (PHP_SAPI != 'cli')
	exit(-1);
if (is_file('maintenance.flag') || is_file('upgrade.flag'))
	exit(0);
if (is_file('app/bootstrap.php'))
	require_once('app/bootstrap.php');

if (in_array('help', $argv) || in_array('--help', $argv) || in_array('-h', $argv)) {
	echo 'Usage: maillog.sh (crontab every minute) or maillog.php (cli)',"\n";
	echo 'Without arguments, sends all pending emails, and performs all pending synchronizations.',"\n";
	echo "\n";
	echo ' --help -h        display this screen',"\n";
	echo ' --dev            enable developer mode',"\n";
	echo ' --only-email     send only pending emails',"\n";
	echo ' --only-sync      perform only pending synchronizations',"\n";
	echo ' --multi          enable multi threads max(1,nbOfCpu-2) (500 emails/syncs per thread)',"\n";
	echo ' --all-customers  synchronize all customers (5000 customers per thread)',"\n";
	echo "\n";
	echo 'For example to sync all customers:',"\n";
	echo ' nice -n 15 su - www-data -s /bin/bash -c \'php .../maillog.php --all-customers\'',"\n";
	exit(0);
}

$isDev   = !empty($_SERVER['MAGE_IS_DEVELOPER_MODE']) || !empty($_ENV['MAGE_IS_DEVELOPER_MODE']) || in_array('--dev', $argv);
$isEmail = !in_array('--only-sync', $argv);
$isSync  = !in_array('--only-email', $argv);
$isMulti = in_array('--multi', $argv);
$isFull  = in_array('--all-customers', $argv);
$runNumb = (int) end($argv);

require_once('app/Mage.php');

Mage::app('admin')->setUseSessionInUrl(false);
Mage::app()->addEventArea('crontab');
Mage::setIsDeveloperMode($isDev);

$stop = Mage::getBaseDir('var').'/maillog.stop';


// info
if ($isDev) {
	Mage::log('dev:true'.
		' email:'.($isEmail ? 'true' : 'false').
		' sync:'.($isSync ? 'true' : 'false').
		' multi:'.($isMulti ? 'true' : 'false').
		' full:'.($isFull ? 'true' : 'false').
		' run:'.($runNumb ?: 'false'),
	Zend_Log::INFO, 'maillog.log');
}

if (is_file($stop)) {
	echo 'Stop file is here: ',$stop,' - Stop now.',"\n";
	exit(-1);
}

if (empty($runNumb) && $isFull) {
	echo 'Starting full sync in 25 seconds...',"\n";
	echo 'You can cancel it at any moment by creating the stop file:'."\n".' '.$stop,"\n";
	file_put_contents($stop, 'checking');
	sleep(25);
	unlink($stop);
	echo 'Go... ',date('c'),"\n";
}


// mode multi threads
// UPDATE luigifab_maillog_sync SET status = "pending";
// DELETE FROM cron_schedule WHERE job_code like "maillog_cron%";
if (empty($runNumb) && ($isMulti || $isFull)) {

	exec('nproc', $core);
	$core = max(1, (int) trim(implode($core)) - 2);
	$pids = [];
	$cmds = [];

	if ($isFull) {

		// syncs
		$nbRunOfSyncs = ceil(Mage::getResourceModel('customer/customer_collection')
			->getSize() / 5000);

		while ($nbRunOfSyncs > 0) {
			$cmds[] = sprintf('%s %s/%s --all-customers %s %d',
				escapeshellcmd(PHP_BINARY),
				getcwd(), basename($argv[0]),
				$isDev ? '--dev' : '',
				$nbRunOfSyncs
			);
			$nbRunOfSyncs--;
		}

		$isMulti = true;
		$isEmail = false;
		$isSync  = false;
	}
	else {
		// emails
		$nbRunOfEmails = $isEmail ? ceil(Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('status', 'pending')
			->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 SECOND)')])
			->getSize() / 500) : 0;

		while ($nbRunOfEmails > 0) {
			$cmds[] = sprintf('%s %s/%s --only-email %s %d',
				escapeshellcmd(PHP_BINARY),
				getcwd(), basename($argv[0]),
				$isDev ? '--dev' : '',
				$nbRunOfEmails
			);
			$nbRunOfEmails--;
		}

		// syncs
		$nbRunOfSyncs = $isSync ? ceil(Mage::getResourceModel('maillog/sync_collection')
			->addFieldToFilter('status', 'pending')
			->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 SECOND)')])
			->getSize() / 500) : 0;

		while ($nbRunOfSyncs > 0) {
			$cmds[] = sprintf('%s %s/%s --only-sync %s %d',
				escapeshellcmd(PHP_BINARY),
				getcwd(), basename($argv[0]),
				$isDev ? '--dev' : '',
				$nbRunOfSyncs
			);
			$nbRunOfSyncs--;
		}

		$isEmail = false;
		$isSync  = false;
	}

	// exécute les threads
	if (!empty($cmds)) {

		while (!empty($cmds)) {

			if (is_file($stop))
				break;

			$pids[] = exec(array_shift($cmds).' >/dev/null 2>&1 & echo $!');
			while (count($pids) >= $core) {
				sleep(10);
				foreach ($pids as $key => $pid) {
					if (file_exists('/proc/'.$pid))
						clearstatcache('/proc/'.$pid);
					else
						unset($pids[$key]);
				}
			}
		}

		while (count($pids) > 0) {
			sleep(10);
			foreach ($pids as $key => $pid) {
				if (file_exists('/proc/'.$pid))
					clearstatcache('/proc/'.$pid);
				else
					unset($pids[$key]);
			}
		}
	}
}


// mode single thread ou exécution des threads
if (!$isMulti && !$isFull && $isEmail) {

	if ($isDev)
		Mage::log('run email ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

	$cron = Mage::getModel('cron/schedule');
	$cron->setData('job_code', ($runNumb > 0) ? 'maillog_cron_email_'.$runNumb : 'maillog_cron_email');
	$cron->setData('created_at', date('Y-m-d H:i:s'));
	$cron->setData('scheduled_at', date('Y-m-d H:i:s'));
	$cron->setData('executed_at', date('Y-m-d H:i:s'));
	$cron->setData('status', 'running');

	$results = sendEmails($cron, $runNumb);

	if (is_file($stop)) {
		$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
		$results['error'][] = $msg;
		if ($isDev)
			Mage::log($msg, Zend_Log::INFO, 'maillog.log');
	}

	saveCron($cron, $results, true);
}

if (!$isMulti && !$isFull && $isSync) {

	if ($isDev)
		Mage::log('run sync ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

	$cron = Mage::getModel('cron/schedule');
	$cron->setData('job_code', ($runNumb > 0) ? 'maillog_cron_sync_'.$runNumb : 'maillog_cron_sync');
	$cron->setData('created_at', date('Y-m-d H:i:s'));
	$cron->setData('scheduled_at', date('Y-m-d H:i:s'));
	$cron->setData('executed_at', date('Y-m-d H:i:s'));
	$cron->setData('status', 'running');

	$results = sendSyncs($cron, $runNumb);

	if (is_file($stop)) {
		$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
		$results['error'][] = $msg;
		if ($isDev)
			Mage::log($msg, Zend_Log::INFO, 'maillog.log');
	}

	saveCron($cron, $results, true);
}

if (!$isMulti && $isFull) {

	if ($isDev)
		Mage::log('run full sync ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

	ini_set('memory_limit', '1G');

	$cron = Mage::getModel('cron/schedule');
	$cron->setData('job_code', ($runNumb > 0) ? 'maillog_cron_fullsync_'.$runNumb : 'maillog_cron_fullsync');
	$cron->setData('created_at', date('Y-m-d H:i:s'));
	$cron->setData('scheduled_at', date('Y-m-d H:i:s'));
	$cron->setData('executed_at', date('Y-m-d H:i:s'));
	$cron->setData('status', 'running');

	$results = fullSync($cron, $runNumb);

	if (is_file($stop)) {
		$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
		$results['error'][] = $msg;
		if ($isDev)
			Mage::log($msg, Zend_Log::INFO, 'maillog.log');
	}

	saveCron($cron, $results, true, true);
}


// action
function sendEmails(object $cron, int $page) {

	$count   = 0;
	$results = ['success' => [], 'error' => []];
	$stop    = Mage::getBaseDir('var').'/maillog.stop';
	$emails  = Mage::getResourceModel('maillog/email_collection')
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
			$email->setData('status', 'error');
			$email->setData('exception', $t->getMessage()."\n".str_replace(dirname(Mage::getBaseDir()), '', $t->getTraceAsString()."\n".'  thrown in '.$t->getFile().' on line '.$t->getLine()));
			$email->save();
			$results['error'][] = 'email:'.$email->getId().' '.$t->getMessage();
			if (Mage::getIsDeveloperMode())
				Mage::logException($t);
		}

		if ((++$count % 100) == 0)
			saveCron($cron, $results, false);
		else if ($count == 1)
			$cron->save();
	}

	return $results;
}

function sendSyncs(object $cron, int $page) {

	$count   = 0;
	$results = ['success' => [], 'error' => []];
	$stop    = Mage::getBaseDir('var').'/maillog.stop';
	$syncs   = Mage::getResourceModel('maillog/sync_collection')
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
			$info = explode(':', $sync->getData('action'));
			if (method_exists($sync, $info[0].'Now')) {
				$sync->{$info[0].'Now'}();
				$results['success'][] = 'sync:'.$sync->getId();
			}
			else {
				Mage::throwException('Unknown method_name ('.get_class($sync).'::'.$info[0] .'[Now]).');
			}
		}
		catch (Throwable $t) {
			$sync->setData('status', 'error');
			$sync->setData('exception', $t->getMessage()."\n".str_replace(dirname(Mage::getBaseDir()), '', $t->getTraceAsString()."\n".'  thrown in '.$t->getFile().' on line '.$t->getLine()));
			$sync->save();
			$results['error'][] = 'sync:'.$sync->getId().' '.$t->getMessage();
			if (Mage::getIsDeveloperMode())
				Mage::logException($t);
		}

		if ((++$count % 100) == 0)
			saveCron($cron, $results, false);
		else if ($count == 1)
			$cron->save();
	}

	return $results;
}

function fullSync(object $cron, int $page) {

	Mage::register('maillog_full_sync', true, true);

	$systems    = array_keys(Mage::helper('maillog')->getSystem());
	$customer   = Mage::getModel('customer/customer');
	$attributes = ['default_shipping', 'default_billing'];

	foreach ($systems as $system)
		$attributes = array_merge($attributes, Mage::helper('maillog')->getSystem($system)->mapFields($customer, 'customer', true));

	$count     = 0;
	$results   = ['success' => [], 'error' => []];
	$stop      = Mage::getBaseDir('var').'/maillog.stop';
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
				$sync->setData('action', 'update:customer:'.$customer->getId().'::'.$customer->getData('email'));
				$sync->setData('model', $system);
				$sync->setData('user', 'allsync');
				$sync->setData('status', 'running');
				$sync->save();
				$sync->updateNow($customer);
				$sync->save();
				$results['success'][] = 'sync:'.$sync->getId();
			}
			catch (Throwable $t) {
				$sync->setData('status', 'error');
				$sync->setData('exception', $t->getMessage()."\n".str_replace(dirname(Mage::getBaseDir()), '', $t->getTraceAsString()."\n".'  thrown in '.$t->getFile().' on line '.$t->getLine()));
				$sync->save();
				$results['error'][] = 'sync:'.$sync->getId().' '.$t->getMessage();
				if (Mage::getIsDeveloperMode())
					Mage::logException($t);
			}
		}

		if ((++$count % 100) == 0)
			saveCron($cron, $results, false);
		else if ($count == 1)
			$cron->save();
	}

	return $results;
}

function saveCron(object $cron, array $results, bool $end, bool $full = false) {

	if (!empty($results['success']) || !empty($results['error'])) {

		$textok = trim(str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '',
			print_r($results['success'], true))));
		$textko = trim(str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '',
			print_r($results['error'], true))));

		if ($end) {
			$cron->setData('finished_at', date('Y-m-d H:i:s'));
			$cron->setData('status', empty($results['error']) ? 'success' : 'error');
		}

		$cron->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n".
			'success: '.$textok."\n".'error: '.$textko);
		$cron->save();
	}
	else if ($end && $full) {
		$cron->setData('finished_at', date('Y-m-d H:i:s'));
		$cron->setData('status', 'error');
		$cron->setData('messages', 'nothing to do');
		$cron->save();
	}
}


// info
if (empty($runNumb) && $isFull) {
	echo 'Finished! ',date('c'),"\n";
}

exit(0);