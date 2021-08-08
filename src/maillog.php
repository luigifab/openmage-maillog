<?php
/**
 * Created S/25/08/2018
 * Updated S/17/07/2021
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

$success = []; $error = []; $done = [];
$email = true; $sync = true; $dev = false;

if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE']) || in_array('--dev', $argv))
	$dev = true;
if (in_array('--only-email', $argv))
	$sync = false;
if (in_array('--only-sync', $argv))
	$email = false;

require_once('app/Mage.php');

Mage::app('admin')->setUseSessionInUrl(false);
Mage::app()->addEventArea('crontab');
Mage::setIsDeveloperMode($dev);


$job = Mage::getModel('cron/schedule');
$job->setData('job_code', 'maillog_sendemails_syncdatas');
$job->setData('created_at', date('Y-m-d H:i:s'));
$job->setData('scheduled_at', date('Y-m-d H:i:s'));
$job->setData('executed_at', date('Y-m-d H:i:s'));

// envoie les emails
if ($email) {

	$emails = Mage::getResourceModel('maillog/email_collection');
	$emails->addFieldToFilter('status', 'pending');
	$emails->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 SECOND)')]);

	foreach ($emails as $email) {
		try {
			$email->sendNow();
			$success[] = 'email:'.$email->getId();
		}
		catch (Throwable $t) {
			$email->setData('status', 'error')->save();
			$error[] = 'email:'.$email->getId().' '.$t->getMessage();
		}
	}
}

// synchronise les données
if ($sync) {

	$syncs = Mage::getResourceModel('maillog/sync_collection');
	$syncs->addFieldToFilter('status', 'pending');
	$syncs->addFieldToFilter('created_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND)')]);
	$syncs->setOrder('created_at', 'asc');

	foreach ($syncs as $sync) {
		try {
			// 0 action : 1 type : 2 id : 3 ancien-email : 4 email
			// 0 action : 1 type : 2 id : 3              : 4 email
			$info = (array) explode(':', $sync->getData('action')); // (yes)
			if (in_array($info[4], $done))
				sleep(1);

			if ($info[0] == 'update') {
				$sync->updateNow();
				$success[] = 'sync:'.$sync->getId();
				$done[]    = $info[4];
			}
			else if ($info[0] == 'delete') {
				$sync->deleteNow();
				$success[] = 'sync:'.$sync->getId();
				$done[]    = $info[4];
			}
		}
		catch (Throwable $t) {
			$sync->setData('status', 'error')->save();
			$error[] = 'sync:'.$sync->getId().' '.$t->getMessage();
		}
	}
}

// enregistre le schedule
if (!empty($success) || !empty($error)) {

	$textok = trim(str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '', print_r($success, true))));
	$textko = trim(str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '', print_r($error, true))));

	$job->setData('finished_at', date('Y-m-d H:i:s'));
	$job->setData('messages', "success:".$textok."\nerror:".$textko);
	$job->setData('status', empty($error) ? Mage_Cron_Model_Schedule::STATUS_SUCCESS : Mage_Cron_Model_Schedule::STATUS_ERROR);
	$job->save();
}

// fin
exit(0);