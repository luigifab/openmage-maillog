<?php
/**
 * Created W/28/12/2016
 * Updated S/25/11/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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
	// ajoute et modifie des colonnes
	$table = $this->getTable('maillog/email');
	if (!$this->getConnection()->tableColumnExists($table, 'mail_sender'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN mail_sender varchar(255) NOT NULL DEFAULT "" AFTER encoded_mail_subject');
	if (!$this->getConnection()->tableColumnExists($table, 'deleted'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN deleted tinyint(1) unsigned NOT NULL DEFAULT 0');

	$this->run('
		ALTER TABLE '.$table.' MODIFY COLUMN encoded_mail_recipients varchar(255) NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN encoded_mail_subject    varchar(255) NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN mail_recipients         varchar(255) NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN mail_subject            varchar(255) NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN mail_body               longtext     NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN mail_header             text         NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN mail_parameters         text         NULL DEFAULT NULL;
		ALTER TABLE '.$table.' MODIFY COLUMN mail_parts              longblob     NULL DEFAULT NULL;
	');

	// remplace :address_ par :address_shipping_
	$this->run('
		UPDATE '.$this->getTable('core_config_data').'
			SET value = REPLACE(value, ":address_", ":address_shipping_") WHERE path = "maillog/sync/mapping_config";

		UPDATE '.$this->getTable('core_config_data').'
			SET value = REPLACE(value, ":address_shipping_shipping_", ":address_shipping_") WHERE path = "maillog/sync/mapping_config";

		UPDATE '.$this->getTable('core_config_data').'
			SET value = REPLACE(value, ":address_shipping_billing_", ":address_billing_") WHERE path = "maillog/sync/mapping_config";
	');

	// remplace la table bounce par un attribut client
	// une idée lumineuse, je crois que j'ai deux neurones qui se sont connectés aujourd'hui
	$this->run('DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_bounce'));

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

	Mage::getConfig()->reinit();
}
catch (Throwable $t) {
	$lock->unlock();
	throw $t;
}

$this->endSetup();
$lock->unlock();