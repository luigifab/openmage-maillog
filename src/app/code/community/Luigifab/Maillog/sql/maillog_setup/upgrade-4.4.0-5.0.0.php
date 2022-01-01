<?php
/**
 * Created V/25/12/2020
 * Updated V/18/06/2021
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
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
	$adaptator = $this->_conn->fetchOne('SELECT value FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog_sync/general/type"');
	if ($adaptator == 'maillog/system_dolist')
		$this->run('UPDATE '.$this->getTable('core_config_data').'
			SET path = REPLACE(path, "maillog_sync/general", "maillog_sync/dolist")
			WHERE path LIKE "maillog_sync/general/%" AND path NOT LIKE "%lifetime%"');
	else if ($adaptator == 'maillog/system_emarsys')
		$this->run('UPDATE '.$this->getTable('core_config_data').'
			SET path = REPLACE(path, "maillog_sync/general", "maillog_sync/emarsys")
			WHERE path LIKE "maillog_sync/general/%" AND path NOT LIKE "%lifetime%"');

	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog_sync/%/type"');
	Mage::getConfig()->reinit();

	// ajoute la colonne model
	$table = $this->getTable('maillog/sync');
	if (!$this->getConnection()->tableColumnExists($table, 'model'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN model varchar(75) NULL DEFAULT NULL AFTER user');
}
catch (Throwable $t) {
	$lock->unlock();
	throw $t;
}

$this->endSetup();
$lock->unlock();