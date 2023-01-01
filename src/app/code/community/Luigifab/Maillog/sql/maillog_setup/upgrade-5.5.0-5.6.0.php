<?php
/**
 * Created D/14/08/2022
 * Updated L/26/12/2022
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

// prevent multiple execution
$lock = Mage::getModel('index/process')->setId('maillog_setup');
if ($lock->isLocked())
	Mage::throwException('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// ignore user abort and time limit
ignore_user_abort(true);
set_time_limit(0);

try {
	$table = $this->getTable('maillog/email');
	if (!$this->getConnection()->tableColumnExists($table, 'useragent'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN useragent varchar(255) NULL DEFAULT NULL');

	$table = $this->getTable('maillog/email');
	if (!$this->getConnection()->tableColumnExists($table, 'referer'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN referer varchar(255) NULL DEFAULT NULL');
}
catch (Throwable $t) {
	$lock->unlock();
	Mage::throwException($t);
}

$this->endSetup();
$lock->unlock();