<?php
/**
 * Created L/09/11/2015
 * Updated M/08/01/2019
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
	// ADD COLUMN IF NOT EXISTS, à partir de MariaDB 10.0.2, n'existe pas dans MySQL 8.0
	// https://mariadb.com/kb/en/mariadb/alter-table/
	// https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
	$sql = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne('SELECT VERSION()');
	if ((mb_stripos($sql, 'MariaDB') !== false) && version_compare($sql, '10.0.2', '>='))
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN IF NOT EXISTS mail_parts longblob NULL DEFAULT NULL');
	else
		$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD COLUMN mail_parts longblob NULL DEFAULT NULL');

	$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY COLUMN type varchar(50) NOT NULL DEFAULT "--"');
	$this->run('UPDATE '.$this->getTable('luigifab_maillog').' SET type = "--" WHERE type = ""');

	// EN CAS D'INTERRUPTION IL SUFFIT SIMPLEMENT DE RELANCER SI L'ENSEMBLE DES EMAILS À AU MAXIMUM 1 SEULE PIÈCE JOINTE
	// suppression de la première pièce jointe qui correspond au contenu de l'email uniquement s'il y a au moins 2 pièces jointes
	// par lot de 1000 pour éviter un memory_limit
	$total = Mage::getResourceModel('maillog/email_collection')
		->addFieldToFilter('mail_parts', array('notnull' => true))
		->getSize();

	$p = ceil($total / 1000);
	$i = 0;

	Mage::log('Update v3.0! Starting update of '.$total.' emails in '.$p.' steps of 1000 emails...', Zend_Log::INFO, 'maillog.log');

	while ($p > 0) {

		$emails = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('mail_parts', array('notnull' => true))
			->setOrder('email_id', 'desc')
			->setPageLimit(1000, $p);

		Mage::log('Update v3.0! Starting update of '.$emails->getSize().' emails (from #'.$emails->getLastItem()->getId().' to #'.$emails->getFirstItem()->getId().') of step '.$p.'...', Zend_Log::INFO, 'maillog.log');

		foreach ($emails as $email) {

			$parts = unserialize(gzdecode($email->getData('mail_parts')));

			if (($parts !== false) && (count($parts) >= 2)) {

				array_shift($parts);
				$parts = gzencode(serialize($parts), 9);

				Mage::log('Update v3.0! Removing body part from email #'.$email->getId().' (step '.$p.')', Zend_Log::INFO, 'maillog.log');
				$email->setData('mail_parts', $parts)->save();
				$i += 1;
			}
			else if (($parts !== false) && (count($parts) >= 1)) {

			}
			else {
				Mage::log('Update v3.0! Removing all parts from email #'.$email->getId().' (step '.$p.')', Zend_Log::INFO, 'maillog.log');
				$email->setData('mail_parts', null)->save();
				$i += 1;
			}
		}

		$p -= 1;
	}

	Mage::log('Update v3.0! Done', Zend_Log::INFO, 'maillog.log');
}
catch (Exception $e) {
	$lock->unlock();
	throw $e;
}

$this->endSetup();
$lock->unlock();