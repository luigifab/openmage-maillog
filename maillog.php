<?php
/**
 * Created S/25/08/2018
 * Updated M/15/01/2019
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

chdir(realpath(__DIR__));
if ((PHP_SAPI != 'cli') || is_file('maintenance.flag') || is_file('upgrade.flag'))
	exit(0);

error_reporting(E_ALL);
ini_set('display_errors', 1);
define('MAGENTO_ROOT', getcwd());

$success = $error = $done = array();
$email = $sync = true; $dev = false;

if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE']) || in_array('--dev', $argv))
	$dev = true;
if (in_array('--only-email', $argv))
	$sync = false;
if (in_array('--only-sync', $argv))
	$email = false;
if (!$sync && !$email)
	$email = $sync = true;

if (is_file('includes/config.php'))
	include('includes/config.php');
if (is_file('app/bootstrap.php'))
	require_once('app/bootstrap.php');

require_once('app/Mage.php');

Mage::app('admin')->setUseSessionInUrl(false);
Mage::app()->addEventArea('crontab');
Mage::setIsDeveloperMode($dev);


$cron = Mage::getModel('cron/schedule');
$cron->setData('job_code', 'maillog_sendemails_syncdatas');
$cron->setData('created_at', date('Y-m-d H:i:s'));
$cron->setData('scheduled_at', date('Y-m-d H:i:s'));
$cron->setData('executed_at', date('Y-m-d H:i:s'));

// envoie les emails
if ($email) {

	$emails = Mage::getResourceModel('maillog/email_collection');
	$emails->addFieldToFilter('status', 'pending');
	$emails->addFieldToFilter('created_at', array('lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 SECOND)')));

	foreach ($emails as $email) {
		try {
			$email->sendNow();
			$success[] = 'email:'.$email->getId();
		}
		catch (Exception $e) {
			$email->setData('status', 'error')->save();
			$error[] = 'email:'.$email->getId().' '.$e->getMessage();
		}
	}
}

// synchronise les données
if ($sync) {

	$syncs = Mage::getResourceModel('maillog/sync_collection');
	$syncs->addFieldToFilter('status', 'pending');
	$syncs->addFieldToFilter('created_at', array('lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND)')));
	$syncs->addFieldToSort('created_at', 'asc');

	foreach ($syncs as $sync) {
		try {
			// 0 action : 1 type : 2 id : 3 ancien-email : 4 email
			// 0 action : 1 type : 2 id : 3              : 4 email
			$dat = explode(':', $sync->getData('action'));
			if (in_array($dat[4], $done))
				sleep(1);

			if ($dat[0] == 'update') {
				$sync->updateNow();
				$success[] = 'sync:'.$sync->getId();
				$done[]    = $dat[4];
			}
			else if ($dat[0] == 'delete') {
				$sync->deleteNow();
				$success[] = 'sync:'.$sync->getId();
				$done[]    = $dat[4];
			}
		}
		catch (Exception $e) {
			$sync->setData('status', 'error')->save();
			$error[] = 'sync:'.$sync->getId().' '.$e->getMessage();
		}
	}
}

// enregistre le schedule
if (!empty($success) || !empty($error)) {
	$cron->setData('finished_at', date('Y-m-d H:i:s'));
	$cron->setData('messages', 'success:'.trim(print_r($success, true))."\n".'error:'.trim(print_r($error, true)));
	$cron->setData('status', empty($error) ? 'success' : 'error');
	$cron->save();
}