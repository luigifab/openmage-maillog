<?php
/**
 * Created L/09/11/2015
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
	// ajoute et modifie des colonnes
	$table = $this->getTable('maillog/email');
	if (!$this->getConnection()->tableColumnExists($table, 'mail_parts'))
		$this->run('ALTER TABLE '.$table.' ADD COLUMN mail_parts longblob NULL DEFAULT NULL');

	$this->run('ALTER TABLE '.$table.' MODIFY COLUMN type varchar(50) NOT NULL DEFAULT "--"');
	$this->run('UPDATE '.$table.' SET type = "--" WHERE type = ""');

	// EN CAS D'INTERRUPTION IL SUFFIT SIMPLEMENT DE RELANCER SI L'ENSEMBLE DES EMAILS À AU MAXIMUM 1 SEULE PIÈCE JOINTE
	// suppression de la première pièce jointe qui correspond au contenu de l'email uniquement s'il y a au moins 2 pièces jointes
	// par lot de 1000 pour éviter un memory_limit
	$total = Mage::getResourceModel('maillog/email_collection')->addFieldToFilter('mail_parts', ['notnull' => true])->getSize();
	$page  = ceil($total / 1000);

	Mage::log('Update v3.0! Starting update of '.$total.' emails in '.$page.' steps of 1000 emails...', Zend_Log::INFO, 'maillog.log');

	while ($page > 0) {

		$emails = Mage::getResourceModel('maillog/email_collection')
			->addFieldToFilter('mail_parts', ['notnull' => true])
			->setOrder('email_id', 'desc')
			->setPageSize(1000)
			->setCurPage($page);

		Mage::log('Update v3.0! Starting update of '.$emails->getSize().' emails (from #'.$emails->getLastItem()->getId().' to #'.$emails->getFirstItem()->getId().') of step '.$page.'...', Zend_Log::INFO, 'maillog.log');

		foreach ($emails as $email) {

			$parts = $email->getEmailParts();
			$count = empty($parts) ? 0 : count($parts);

			if ($count >= 2) {
				array_shift($parts); // supprime le premier
				Mage::log('Update v3.0! Removing body part from email #'.$email->getId().' (step '.$page.')', Zend_Log::INFO, 'maillog.log');
				$email->setData('mail_parts', gzencode(serialize($parts), 9))->save();
			}
			else if ($count >= 1) {
				continue; // nothing to do
			}
			else {
				Mage::log('Update v3.0! Removing all parts from email #'.$email->getId().' (step '.$page.')', Zend_Log::INFO, 'maillog.log');
				$email->setData('mail_parts', null)->save();
			}
		}

		$page--;
	}

	Mage::log('Update v3.0! Done', Zend_Log::INFO, 'maillog.log');
}
catch (Throwable $t) {
	$lock->unlock();
	Mage::throwException($t);
}

$this->endSetup();
$lock->unlock();