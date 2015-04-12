<?php
/**
 * Created D/22/03/2015
 * Updated V/03/04/2015
 * Version 4
 *
 * Copyright 2015 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
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

require_once(substr(__FILE__, 0, strpos(__FILE__, 'app/code')).'app/Mage.php');
$id = (isset($argv[1])) ? intval($argv[1]) : 0;

if ($id > 0) {
	Mage::app();
	Mage::getModel('maillog/email')->load($id)->send(true);
}

exit(0);