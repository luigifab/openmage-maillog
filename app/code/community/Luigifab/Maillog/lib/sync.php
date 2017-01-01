<?php
/**
 * Created S/14/11/2015
 * Updated V/18/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
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

require_once(preg_replace('#app/code.+$#', 'app/Mage.php', __FILE__));
$id       = (isset($argv[1])) ? intval($argv[1]) : 0;
$website  = (isset($argv[2])) ? intval($argv[2]) : 1;
$store    = (isset($argv[3])) ? $argv[3] : '';
$username = (isset($argv[4])) ? $argv[4] : '';

if ($id > 0) {

	Mage::app($store);

	if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE']))
		Mage::setIsDeveloperMode(true);

	if (($store === 'admin') && ($username !== ''))
		Mage::getSingleton('admin/session')->setUser(new Varien_Object(array('username' => $username)));

	for ($i = 0; $i < 5; $i++) {

		$sync = Mage::getSingleton('maillog/sync')->load($id);

		if ($sync->getId() > 0) {
			$sync->backgroundSync($website, $store);
			break;
		}
		else {
			Mage::log('Warning! Sync id #'.$id.' is not ready! Next try in 3 seconds... ('.($i + 1).'/5)', Zend_Log::ERR, 'maillog.log');
			sleep(3);
		}
	}
}

exit(0);