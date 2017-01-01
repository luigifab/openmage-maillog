<?php
/**
 * Created V/01/05/2015
 * Updated M/08/11/2016
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

$this->startSetup();
$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' ADD mail_parts LONGBLOB NULL DEFAULT NULL;');
$this->run('ALTER TABLE '.$this->getTable('luigifab_maillog').' MODIFY type varchar(50) NOT NULL DEFAULT "--";');
$this->run('UPDATE '.$this->getTable('luigifab_maillog').' SET type = "--" WHERE type = "";');
$this->endSetup();