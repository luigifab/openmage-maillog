<?php
/**
 * Created W/28/12/2016
 * Updated S/22/12/2018
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
	// remet à jour la liste des status
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').'
		MODIFY COLUMN status ENUM("pending","sent","error","read","notsent","bounce","sending") NOT NULL DEFAULT "pending"');

	// remplace :address_ par :address_shipping_
	$this->run('
		UPDATE '.$this->getTable('core_config_data').'
			SET value = REPLACE(value, ":address_", ":address_shipping_") WHERE path = "maillog/sync/mapping_config";
		UPDATE '.$this->getTable('core_config_data').'
			SET value = REPLACE(value, ":address_shipping_shipping_", ":address_shipping_") WHERE path = "maillog/sync/mapping_config";
		UPDATE '.$this->getTable('core_config_data').'
			SET value = REPLACE(value, ":address_shipping_billing_", ":address_billing_") WHERE path = "maillog/sync/mapping_config";
	');

	// ajoute la colonne mail_sender et deleted
	// ADD COLUMN IF NOT EXISTS, à partir de MariaDB 10.0.2, n'existe pas dans MySQL 8.0
	// https://mariadb.com/kb/en/mariadb/alter-table/
	// https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
	$sql = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne('SELECT VERSION()');
	if ((mb_stripos($sql, 'MariaDB') !== false) && version_compare($sql, '10.0.2', '>=')) {
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN IF NOT EXISTS mail_sender varchar(255) NOT NULL DEFAULT ""');
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN IF NOT EXISTS deleted tinyint(1) unsigned NOT NULL DEFAULT 0');
	}
	else {
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN mail_sender varchar(255) NOT NULL DEFAULT ""');
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN deleted tinyint(1) unsigned NOT NULL DEFAULT 0');
	}

	// rend nullable les colonnes
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN encoded_mail_recipients varchar(255) NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN encoded_mail_subject    varchar(255) NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_recipients         varchar(255) NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_subject            varchar(255) NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_body               longtext     NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_header             text         NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_parameters         text         NULL DEFAULT NULL');
	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN mail_parts              longblob     NULL DEFAULT NULL');

	// remplace la table bounce par un attribut client
	// une idée lumineuse, je crois que j'ai deux neurones qui se sont connectés aujourd'hui
	$this->run('DROP TABLE IF EXISTS '.$this->getTable('luigifab_maillog_bounce'));

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

	Mage::getConfig()->reinit();
}
catch (Exception $e) {
	$lock->unlock();
	throw $e;
}

$this->endSetup();
$lock->unlock();