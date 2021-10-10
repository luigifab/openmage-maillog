<?php
/**
 * Created D/05/09/2021
 * Updated J/30/09/2021
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://www.luigifab.fr/openmage/maillog
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

// de manière à empécher de lancer cette procédure plusieurs fois
$lock = Mage::getModel('index/process')->setId('maillog_setup');
if ($lock->isLocked())
	Mage::throwException('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// de manière à continuer quoi qu'il arrive
ignore_user_abort(true);
set_time_limit(0);

try {
	$config = Mage::getModel('core/config_data')->load('maillog_sync/emarsys/api_username', 'path');
	if (!empty($user = $config->getData('value'))) {
		$user = Mage::helper('core')->decrypt($user);
		$config->setData('value', empty($user) ? null : $user)->save();
	}

	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog_directives/general/update%";');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "maillog_sync/dolist%";');

	Mage::getConfig()->reinit();
}
catch (Throwable $t) {
	$lock->unlock();
	throw $t;
}

$this->endSetup();
$lock->unlock();