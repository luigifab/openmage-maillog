<?php
/**
 * Created D/22/03/2015
 * Updated J/18/04/2019
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
	Mage::throwException('Please wait, install is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// de manière à continuer quoi qu'il arrive
ignore_user_abort(true);
set_time_limit(0);

try {
	// configuration et tables
	$this->run('
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog/%";
		DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "crontab/jobs/maillog_%";

		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog').';
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_sync').';
		DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_bounce').';

		CREATE TABLE '.$this->getTable('luigifab_maillog').' (
			email_id                int(11) unsigned NOT NULL AUTO_INCREMENT,
			status                  enum("pending","sent","error","read","notsent","bounce","sending") NOT NULL DEFAULT "pending",
			created_at              datetime         DEFAULT NULL,
			sent_at                 datetime         DEFAULT NULL,
			duration                int(4)           NOT NULL DEFAULT -1,
			uniqid                  varchar(30)      NOT NULL DEFAULT "",
			type                    varchar(50)      NOT NULL DEFAULT "--",
			size                    int(8) unsigned  NOT NULL DEFAULT 0,
			encoded_mail_recipients varchar(255)     NULL DEFAULT NULL,
			encoded_mail_subject    varchar(255)     NULL DEFAULT NULL,
			mail_sender             varchar(255)     NULL DEFAULT NULL,
			mail_recipients         varchar(255)     NULL DEFAULT NULL,
			mail_subject            varchar(255) CHARACTER SET utf8mb4 NULL DEFAULT NULL,
			mail_body               longtext     CHARACTER SET utf8mb4 NULL DEFAULT NULL,
			mail_header             text             NULL DEFAULT NULL,
			mail_parameters         text             NULL DEFAULT NULL,
			mail_parts              longblob         NULL DEFAULT NULL,
			deleted                 tinyint(1) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (email_id),
			KEY uniqid (uniqid)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
	');

	// attribut client
	$sortOrder = intval($this->_conn->fetchOne(
		'SELECT sort_order FROM '.$this->getTable('eav_entity_attribute').' WHERE attribute_id = ?',
		 intval(Mage::getResourceModel('eav/entity_attribute')->getIdByCode('customer', 'email'))
	)) + 1;

	$this->removeAttribute('customer', 'is_bounce');
	$this->addAttribute('customer', 'is_bounce', array(
		'label'    => 'Invalid email (hard bounce)', //$this->__('Invalid email (hard bounce)') pour le translate.php avec Magento 1.7 et +
		'type'     => 'int',
		'input'    => 'select',
		'source'   => 'maillog/source_bounce',
		'visible'  => 1,
		'required' => 0
	));

	$attributeSetId   = $this->getDefaultAttributeSetId('customer');
	$attributeGroupId = $this->getDefaultAttributeGroupId('customer', $attributeSetId);
	$this->addAttributeToGroup('customer', $attributeSetId, $attributeGroupId, 'is_bounce', $sortOrder);

	if (version_compare(Mage::getVersion(), '1.4.2', '>=')) {
		$attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'is_bounce');
		$attribute->setData('used_in_forms', array('adminhtml_customer'));
		$attribute->setData('sort_order', $sortOrder);
		$attribute->save();
	}
}
catch (Exception $e) {
	$lock->unlock();
	throw $e;
}

$this->endSetup();
$lock->unlock();