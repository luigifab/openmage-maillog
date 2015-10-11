<?php
/**
 * Created D/22/03/2015
 * Updated M/25/08/2015
 * Version 7
 *
 * Copyright 2015 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://redmine.luigifab.info/projects/magento/wiki/maillog
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

$this->startSetup();
$this->run('
	DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog').';
	DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%";
	CREATE TABLE '.$this->getTable('luigifab_maillog').' (
		email_id           int(11) unsigned NOT NULL auto_increment,
		status             ENUM("pending", "sent", "error", "read") NOT NULL DEFAULT "pending",
		--
		created_at         datetime        NULL DEFAULT NULL,
		sent_at            datetime        NULL DEFAULT NULL,
		uniqid             varchar(30)     NOT NULL DEFAULT "",
		type               varchar(50)     NOT NULL DEFAULT "",
		size               int(8) unsigned NOT NULL DEFAULT 0,
		encoded_mail_recipients varchar(255) NOT NULL DEFAULT "",
		encoded_mail_subject    varchar(255) NOT NULL DEFAULT "",
		--
		mail_recipients    varchar(255) NOT NULL DEFAULT "",
		mail_subject       varchar(255) NOT NULL DEFAULT "",
		mail_body          longtext     NOT NULL DEFAULT "",
		mail_header        text         NOT NULL DEFAULT "",
		mail_parameters    text         NOT NULL DEFAULT "",
		--
		PRIMARY KEY (email_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
');
$this->endSetup();