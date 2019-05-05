<?php
/**
 * Created M/01/05/2018
 * Updated D/27/01/2019
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

// de manière à empécher de lancer cette procédure plusieurs fois car Magento en est capable
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
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_sync').';
		CREATE TABLE '.$this->getTable('luigifab_maillog_sync').' (
			sync_id                 int(11) unsigned NOT NULL AUTO_INCREMENT,
			status                  enum("pending","success","error","running","notsync") NOT NULL DEFAULT "pending",
			created_at              datetime         NULL DEFAULT NULL,
			sync_at                 datetime         NULL DEFAULT NULL,
			duration                int(4)           NOT NULL DEFAULT -1,
			user                    varchar(50)      NULL DEFAULT NULL,
			action                  varchar(250)     NULL DEFAULT NULL,
			request                 text             NULL DEFAULT NULL,
			response                text             NULL DEFAULT NULL,
			PRIMARY KEY (sync_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

		ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_sender varchar(255) NULL DEFAULT NULL;

		DELETE FROM '.$this->getTable('core_config_data').' WHERE
			   path LIKE "crontab/jobs/maillog%import/schedule/cron_expr"
			OR path LIKE "maillog/general/block"
			OR path LIKE "maillog/sync/%uniq%"
			OR path LIKE "maillog/sync/mapping_customeridfield"
			OR path LIKE "maillog/sync/mapping_fields"
			OR path LIKE "maillog/sync/maping%"
			OR path LIKE "maillog/newsletter/%"
			OR path LIKE "maillog/bounce/%"
			OR path LIKE "maillog/system/%"
			OR path LIKE "maillog/content/%"
			OR path LIKE "maillog%background"
			OR path LIKE "maillog/general/lifetime%"
			OR path LIKE "maillog/email/template"
			OR path LIKE "modules/email/template"
			OR path LIKE "cronlog/email/template";
	');

	// ajoute la colonne duration
	// ADD COLUMN IF NOT EXISTS, à partir de MariaDB 10.0.2, n'existe pas dans MySQL 8.0
	// https://mariadb.com/kb/en/mariadb/alter-table/
	// https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
	$sql = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne('SELECT VERSION()');
	$col = 'duration int(4) NOT NULL DEFAULT -1 AFTER sent_at';
	if ((mb_stripos($sql, 'MariaDB') !== false) && version_compare($sql, '10.0.2', '>='))
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN IF NOT EXISTS '.$col);
	else
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN '.$col);
}
catch (Exception $e) {
	$lock->unlock();
	throw $e;
}

$this->endSetup();
$lock->unlock();