<?php
/**
 * Created S/09/03/2019
 * Updated V/12/06/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
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
	$this->run('
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%/ignore";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%/baseurl";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%/notbaseurl";
		ALTER TABLE '.$this->getTable('maillog/email').' CHANGE mail_subject mail_subject varchar(255) CHARACTER SET utf8mb4 NULL DEFAULT NULL;
		ALTER TABLE '.$this->getTable('maillog/email').' CHANGE mail_body mail_body longtext CHARACTER SET utf8mb4 NULL DEFAULT NULL;
		ALTER TABLE '.$this->getTable('maillog/sync').' CHANGE status status enum("pending","success","error","running","notsync") NOT NULL DEFAULT "pending";
	');

	// CREATE INDEX IF NOT EXISTS, n'existe pas dans MySQL 8.0
	// https://mariadb.com/kb/en/library/create-index/
	// https://dev.mysql.com/doc/refman/8.0/en/create-index.html
	$sql = $this->getConnection()->fetchOne('SELECT VERSION()');
	if (mb_stripos($sql, 'MariaDB') !== false)
		$this->run('CREATE INDEX IF NOT EXISTS uniqid ON '.$this->getTable('maillog/email').' (uniqid);');
	else
		$this->run('CREATE INDEX uniqid ON '.$this->getTable('maillog/email').' (uniqid);');
}
catch (Exception $e) {
	$lock->unlock();
	throw $e;
}

$this->endSetup();
$lock->unlock();