<?php
/**
 * Created J/09/05/2019
 * Updated M/02/02/2021
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

// de manière à empécher de lancer cette procédure plusieurs fois
$lock = Mage::getModel('index/process')->setId('maillog_setup');
if ($lock->isLocked())
	Mage::throwException('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// de manière à continuer quoi qu'il arrive
ignore_user_abort(true);
set_time_limit(0);

try {
	$var = Mage::getModel('admin/variable');
	if (is_object($var)) {
		$var->load('design/head/default_title', 'variable_name');
		$var->setData('variable_name', 'design/head/default_title');
		$var->setData('is_allowed', '1');
		$var->save();
	}

	$table = $this->getTable('core_config_data');
	$this->run('UPDATE '.$table.' SET path = REPLACE(path, "maillog/sync/", "maillog_sync/general/")
		WHERE path LIKE "maillog/sync/%"');
	$this->run('UPDATE '.$table.' SET path = REPLACE(path, "maillog/bounces/", "maillog_sync/bounces/")
		WHERE path LIKE "maillog/bounces/%"');
	$this->run('UPDATE '.$table.' SET path = REPLACE(path, "maillog/unsubscribers/", "maillog_sync/unsubscribers/")
		WHERE path LIKE "maillog/unsubscribers/%"');
	Mage::getConfig()->reinit();
}
catch (Throwable $e) {
	$lock->unlock();
	throw $e;
}

$this->endSetup();
$lock->unlock();