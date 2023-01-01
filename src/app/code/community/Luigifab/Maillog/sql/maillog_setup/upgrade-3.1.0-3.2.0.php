<?php
/**
 * Created M/01/05/2018
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
	// ajoute la colonne duration
	$table = $this->getTable('maillog/email');
	if (!$this->getConnection()->tableColumnExists($table, 'duration'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN duration int(4) NOT NULL DEFAULT -1 AFTER sent_at');

	// ménage
	$this->run('
		ALTER TABLE '.$table.' MODIFY COLUMN mail_sender varchar(255) NULL DEFAULT NULL;

		DROP TABLE IF EXISTS '.$this->getTable('maillog/sync').';
		CREATE TABLE '.$this->getTable('maillog/sync').' (
			sync_id                 int(11) unsigned NOT NULL AUTO_INCREMENT,
			status                  enum("pending","success","error","running","notsync") NOT NULL DEFAULT "pending",
			created_at              datetime         NULL DEFAULT NULL,
			sync_at                 datetime         NULL DEFAULT NULL,
			duration                int(4)           NOT NULL DEFAULT -1,
			user                    varchar(50)      NULL DEFAULT NULL,
			model                   varchar(75)      NULL DEFAULT NULL,
			action                  varchar(250)     NULL DEFAULT NULL,
			request                 text             NULL DEFAULT NULL,
			response                text             NULL DEFAULT NULL,
			PRIMARY KEY (sync_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
			OR path LIKE "maillog/email/template";
	');
}
catch (Throwable $t) {
	$lock->unlock();
	Mage::throwException($t);
}

$this->endSetup();
$lock->unlock();