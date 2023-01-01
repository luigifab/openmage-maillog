<?php
/**
 * Created D/22/03/2015
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
	Mage::throwException('Please wait, install is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// ignore user abort and time limit
ignore_user_abort(true);
set_time_limit(0);

try {
	// variable de configuration dans les emails
	$var = Mage::getModel('admin/variable');
	if (is_object($var)) {
		$var->load('design/head/default_title', 'variable_name');
		$var->setData('variable_name', 'design/head/default_title');
		$var->setData('is_allowed', '1');
		$var->save();
	}

	// configuration et tables
	$this->run('
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog_sync/%";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog_directives/%";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "crontab/jobs/maillog_%";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "newsletter/%/%send";

		DROP TABLE IF EXISTS '.$this->getTable('maillog/email').';
		DROP TABLE IF EXISTS '.$this->getTable('maillog/sync').';
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_bounce').';

		CREATE TABLE '.$this->getTable('maillog/email').' (
			email_id                int(11) unsigned    NOT NULL AUTO_INCREMENT,
			status                  enum("pending","sent","error","read","notsent","bounce","sending") NOT NULL DEFAULT "pending",
			created_at              datetime            NULL DEFAULT NULL,
			sent_at                 datetime            NULL DEFAULT NULL,
			duration                int(4)              NOT NULL DEFAULT -1,
			uniqid                  varchar(30)         NOT NULL,
			type                    varchar(50)         NOT NULL DEFAULT "--",
			size                    int(8) unsigned     NOT NULL DEFAULT 0,
			encoded_mail_recipients varchar(255)        NULL DEFAULT NULL,
			encoded_mail_subject    varchar(255)        NULL DEFAULT NULL,
			mail_sender             varchar(255)        NULL DEFAULT NULL,
			mail_recipients         varchar(255)        NULL DEFAULT NULL,
			mail_subject            varchar(255)        CHARACTER SET utf8mb4 NULL DEFAULT NULL,
			mail_body               longtext            CHARACTER SET utf8mb4 NULL DEFAULT NULL,
			mail_header             text                NULL DEFAULT NULL,
			mail_parameters         text                NULL DEFAULT NULL,
			mail_parts              longblob            NULL DEFAULT NULL,
			deleted                 tinyint(1) unsigned NOT NULL DEFAULT 0,
			useragent               varchar(255)        NULL DEFAULT NULL,
			referer                 varchar(255)        NULL DEFAULT NULL,
			PRIMARY KEY (email_id),
			KEY uniqid (uniqid),
			FULLTEXT mail_recipients (mail_recipients),
			FULLTEXT mail_subject (mail_subject)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
	');

	// attribut client
	$sortOrder = (int) $this->_conn->fetchOne(
		'SELECT sort_order FROM '.$this->getTable('eav_entity_attribute').' WHERE attribute_id = ?',
		 (int) Mage::getResourceModel('eav/entity_attribute')->getIdByCode('customer', 'email')
	) + 1;

	$this->removeAttribute('customer', 'is_bounce');
	$this->addAttribute('customer', 'is_bounce', [
		'label'    => 'Invalid email (hard bounce)', //$this->__('Invalid email (hard bounce)') translate.php
		'type'     => 'int',
		'input'    => 'select',
		'source'   => 'maillog/source_bounce',
		'visible'  => 1,
		'required' => 0,
	]);

	$attributeSetId   = $this->getDefaultAttributeSetId('customer');
	$attributeGroupId = $this->getDefaultAttributeGroupId('customer', $attributeSetId);
	$this->addAttributeToGroup('customer', $attributeSetId, $attributeGroupId, 'is_bounce', $sortOrder);

	Mage::getSingleton('eav/config')
		->getAttribute('customer', 'is_bounce')
		->setData('used_in_forms', ['adminhtml_customer'])
		->setData('sort_order', $sortOrder)
		->save();
}
catch (Throwable $t) {
	$lock->unlock();
	Mage::throwException($t);
}

$this->endSetup();
$lock->unlock();