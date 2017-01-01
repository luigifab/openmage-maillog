<?php
/**
 * Created D/22/03/2015
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
$id = (isset($argv[1])) ? intval($argv[1]) : 0;

if ($id > 0) {

	Mage::app();

	if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE']))
		Mage::setIsDeveloperMode(true);

	for ($i = 0; $i < 5; $i++) {

		$mail = Mage::getModel('maillog/email')->load($id);

		if ($mail->getId() > 0) {
			$mail->send(true);
			break;
		}
		else {
			Mage::log('Warning! Mail id #'.$id.' is not ready! Next try in 3 seconds... ('.($i + 1).'/5)', Zend_Log::ERR, 'maillog.log');
			sleep(3);
		}
	}
}

exit(0);