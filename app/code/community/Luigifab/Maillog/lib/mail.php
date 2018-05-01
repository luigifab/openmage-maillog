<?php
/**
 * Created D/22/03/2015
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
$id   = (!empty($argv[1])) ? intval($argv[1]) : 0;
$dev  = (!empty($argv[2])) ? true : false;
$code = (!empty($argv[3])) ? $argv[3] : ''; // store code

if (!empty($id)) {

	Mage::app($code);
	Mage::setIsDeveloperMode($dev);

	try {
		for ($i = 0; $i < 5; $i++) {

			$mail = Mage::getModel('maillog/email')->load($id);

			if (!empty($mail->getId())) {
				$mail->sendNow();
				exit(0);
			}
			else {
				Mage::log('Warning! Mail id #'.$id.' is not ready! Next try in 3 seconds... ('.($i + 1).'/5)', Zend_Log::ERR, 'maillog.log');
				sleep(3);
			}
		}
	}
	catch (Exception $e) {
		Mage::logException($e);
		throw $e;
	}
}

exit(-1);