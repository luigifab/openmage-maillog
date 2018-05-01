<?php
/**
 * Created D/22/03/2015
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
	$this->run('
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "crontab/jobs/maillog_%";
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog').';
		CREATE TABLE '.$this->getTable('luigifab_maillog').' (
			email_id                int(11) unsigned NOT NULL AUTO_INCREMENT,
			status                  ENUM("pending","sent","error","read") NOT NULL DEFAULT "pending", -- ENUM(...)          v2.4
			created_at              datetime         NULL     DEFAULT NULL,
			sent_at                 datetime         NULL     DEFAULT NULL,
			uniqid                  varchar(30)      NOT NULL DEFAULT "",
			type                    varchar(50)      NOT NULL DEFAULT "",                             -- DEFAULT "--"       v2.0
			size                    int(8) unsigned  NOT NULL DEFAULT 0,
			encoded_mail_recipients varchar(255)     NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			encoded_mail_subject    varchar(255)     NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			mail_recipients         varchar(255)     NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			mail_subject            varchar(255)     NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			mail_body               longtext         NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			mail_header             text             NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			mail_parameters         text             NOT NULL DEFAULT "",                             -- NULL DEFAULT NULL  v3.1
			-- mail_parts           longblob         NULL     DEFAULT NULL,                                                 v2.0
			-- mail_sender          varchar(255)     NOT NULL DEFAULT "",                                                   v3.1
			-- deleted           tinyint(1) unsigned NOT NULL DEFAULT 0,                                                    v3.1
			PRIMARY KEY (email_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	');
}
catch (Exception $e) {
	$lock->unlock();
	throw new Exception($e);
}

$this->endSetup();
$lock->unlock();