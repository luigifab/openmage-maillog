<?php
/**
 * Created S/25/08/2018
 * Updated J/28/12/2023
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

chdir(dirname($argv[0])); // root
error_reporting(E_ALL);
ini_set('display_errors', (PHP_VERSION_ID < 80100) ? '1' : 1);

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
	echo ' --multi          enable multi threads max(1,nbOfCpu-2) (500 emails/syncs)',"\n";
	echo ' --all-customers  synchronize all customers (5000 customers)',"\n";
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

try {
	$model = Mage::getModel('maillog/cli');
	$stop  = Mage::getBaseDir('var').'/maillog.stop';

	if (is_file($stop)) {
		echo 'fatal: stop file found (',$stop,'), stop now.',"\n";
		exit(-1);
	}

	// text info
	if ($isEmail && !Mage::getStoreConfigFlag('maillog/general/enabled')) {
		echo 'fatal: emails disabled!',"\n";
		exit(-1);
	}

	if (($isSync || $isFull) && !Mage::getStoreConfigFlag('maillog_sync/general/enabled')) {
		echo 'fatal: syncs disabled!',"\n";
		exit(-1);
	}

	if ($isDev) {
		Mage::log('dev:true'.
			' email:'.($isEmail ? 'true' : 'false').
			' sync:'.($isSync ? 'true' : 'false').
			' multi:'.($isMulti ? 'true' : 'false').
			' full:'.($isFull ? 'true' : 'false').
			' run:'.($runNumb ?: 'false'),
		Zend_Log::INFO, 'maillog.log');
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
							clearstatcache(true, '/proc/'.$pid);
						else
							unset($pids[$key]);
					}
				}
			}

			while (count($pids) > 0) {
				sleep(10);
				foreach ($pids as $key => $pid) {
					if (file_exists('/proc/'.$pid))
						clearstatcache(true, '/proc/'.$pid);
					else
						unset($pids[$key]);
				}
			}
		}

		if ($isFull)
			echo 'Finished! ',date('c'),"\n";
	}


	// mode single thread ou exécution des threads
	if (!$isMulti && !$isFull && $isEmail) {

		if ($isDev)
			Mage::log('run email ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

		$job = Mage::getModel('cron/schedule');
		$job->setData('job_code', ($runNumb > 0) ? 'maillog_cron_email_'.$runNumb : 'maillog_cron_email');
		$job->setData('created_at', date('Y-m-d H:i:s'));
		$job->setData('scheduled_at', date('Y-m-d H:i:s'));
		$job->setData('executed_at', date('Y-m-d H:i:s'));
		$job->setData('status', 'running');

		$results = $model->sendEmails($job, $stop, $runNumb);

		if (is_file($stop)) {
			$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
			$results['error'][] = $msg;
			if ($isDev)
				Mage::log($msg, Zend_Log::INFO, 'maillog.log');
		}

		$model->saveJob($job, $results, true);
	}

	if (!$isMulti && !$isFull && $isSync) {

		if ($isDev)
			Mage::log('run sync ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

		$job = Mage::getModel('cron/schedule');
		$job->setData('job_code', ($runNumb > 0) ? 'maillog_cron_sync_'.$runNumb : 'maillog_cron_sync');
		$job->setData('created_at', date('Y-m-d H:i:s'));
		$job->setData('scheduled_at', date('Y-m-d H:i:s'));
		$job->setData('executed_at', date('Y-m-d H:i:s'));
		$job->setData('status', 'running');

		$results = $model->sendSyncs($job, $stop, $runNumb);

		if (is_file($stop)) {
			$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
			$results['error'][] = $msg;
			if ($isDev)
				Mage::log($msg, Zend_Log::INFO, 'maillog.log');
		}

		$model->saveJob($job, $results, true);
	}

	if (!$isMulti && $isFull) {

		if ($isDev)
			Mage::log('run full sync ('.(($runNumb > 0) ? 'multi threads, nb#'.$runNumb : 'single thread').')', Zend_Log::INFO, 'maillog.log');

		ini_set('memory_limit', '1G');

		$job = Mage::getModel('cron/schedule');
		$job->setData('job_code', ($runNumb > 0) ? 'maillog_cron_fullsync_'.$runNumb : 'maillog_cron_fullsync');
		$job->setData('created_at', date('Y-m-d H:i:s'));
		$job->setData('scheduled_at', date('Y-m-d H:i:s'));
		$job->setData('executed_at', date('Y-m-d H:i:s'));
		$job->setData('status', 'running');

		$results = $model->fullSync($job, $stop, $runNumb);

		if (is_file($stop)) {
			$msg = ($runNumb > 0) ? 'Interrupted by '.$stop.' file (thread nb#'.$runNumb.').' : 'Interrupted by '.$stop.' file.';
			$results['error'][] = $msg;
			if ($isDev)
				Mage::log($msg, Zend_Log::INFO, 'maillog.log');
		}

		$model->saveJob($job, $results, true, true);
	}
}
catch (Throwable $t) {
	if (!empty($runNumb))
		Mage::logException($t);
	throw $t;
}

exit(0);