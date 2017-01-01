<?php
/**
 * Created D/12/06/2016
 * Updated M/08/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Maillog_Model_Source_Lifetime extends Luigifab_Maillog_Helper_Data {

	public function toOptionArray() {

		return array(
			array('value' => 0, 'label' => '--'),
			array('value' => 5 * 24 * 60,  'label' => $this->__('%d days', 5)),
			array('value' => 7 * 24 * 60,  'label' => $this->__('%d days', 7)),
			array('value' => 14 * 24 * 60, 'label' => $this->__('%d days (%d weeks)', 14, 2)),
			array('value' => 28 * 24 * 60, 'label' => $this->__('%d days (%d weeks)', 28, 4)),
			array('value' => 31 * 24 * 60, 'label' => $this->__('%d days (%d month)', 31, 1)),
			array('value' => 62 * 24 * 60, 'label' => $this->__('%d days (%d months)', 62, 2)),
			array('value' => 93 * 24 * 60, 'label' => $this->__('%d days (%d months)', 93, 3))
		);
	}
}