<?php
/**
 * Created S/14/11/2015
 * Updated D/25/03/2018
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

require_once(preg_replace('#app/code.+$#', 'app/Mage.php', $argv[0]));
$action = (!empty($argv[1])) ? $argv[1] : ''; // update ou delete ou module/model:function
$store  = (!empty($argv[2])) ? intval($argv[2]) : 0; // customer store id
$email  = (!empty($argv[3])) ? $argv[3] : ''; // customer email
$dev    = (!empty($argv[4])) ? true : false;
$code   = (!empty($argv[5])) ? $argv[5] : ''; // store code
$user   = (!empty($argv[6])) ? $argv[6] : ''; // admin user name

if (!empty($action) && !empty($store) && !empty($email)) {

	sleep(2);

	Mage::app($code);
	Mage::setIsDeveloperMode($dev);

	if (($code == 'admin') && !empty($user))
		Mage::getSingleton('admin/session')->setData('user', new Varien_Object(array('username' => $user)));

	try {
		if ($action == 'update') {
			Mage::getSingleton('maillog/sync')->updateNow($store, $email);
			exit(0);
		}
		else if ($action == 'delete') {
			Mage::getSingleton('maillog/sync')->deleteNow($store, $email);
			exit(0);
		}
		else if (strpos($action, ':') !== false) {
			list($model, $method) = explode(':', $action);
			Mage::getSingleton($model)->$method($store, $email);
			exit(0);
		}
	}
	catch (Exception $e) {
		Mage::logException($e);
		throw $e;
	}
}

exit(-1);