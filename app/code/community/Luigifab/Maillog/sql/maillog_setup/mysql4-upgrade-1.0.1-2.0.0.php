<?php
/**
 * Created V/01/05/2015
 * Updated J/22/02/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * https://www.luigifab.info/magento/maillog
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

// de manière à empécher de lancer cette procédure plusieurs fois car Magento en est capable
ignore_user_abort(true);
$lock = Mage::getModel('index/process')->setId('maillog_setup');
if ($lock->isLocked())
	throw new Exception('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

try {
	// ADD COLUMN IF NOT EXISTS, à partir de MariaDB 10.0.2, n'existe pas dans MySQL 8.0
	// https://mariadb.com/kb/en/mariadb/alter-table/
	// https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
	$sql = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne('SELECT VERSION()');
	if ((stripos($sql, 'MariaDB') !== false) && version_compare($sql, '10.0.2', '>='))
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN IF NOT EXISTS mail_parts longblob NULL DEFAULT NULL');
	else
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN mail_parts longblob NULL DEFAULT NULL');

	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN type varchar(50) NOT NULL DEFAULT "--"');
	$this->run('UPDATE '.$this->getTable('luigifab_maillog').' SET type = "--" WHERE type = ""');
}
catch (Exception $e) {
	$lock->unlock();
	throw new Exception($e);
}

$this->endSetup();
$lock->unlock();