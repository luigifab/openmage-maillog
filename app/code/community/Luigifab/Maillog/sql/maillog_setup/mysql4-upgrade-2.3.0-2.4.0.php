<?php
/**
 * Created S/10/10/2015
 * Updated S/10/10/2015
 * Version 1
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

$this->startSetup();
$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' CHANGE status status ENUM("pending", "sent", "error", "read", "notsent", "bounce", "sending") NOT NULL DEFAULT "pending";');
$this->endSetup();