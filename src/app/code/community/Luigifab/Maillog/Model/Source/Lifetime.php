<?php
/**
 * Created D/12/06/2016
 * Updated V/22/01/2021
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

class Luigifab_Maillog_Model_Source_Lifetime {

	public function toOptionArray() {

		$help = Mage::helper('maillog');
		return [
			['value' => 0, 'label' => '--'],
			['value' => 5 * 24 * 60,  'label' => $help->__('%d days', 5)], // 5+
			['value' => 7 * 24 * 60,  'label' => $help->__('%d days', 7)], // 5+
			['value' => 14 * 24 * 60, 'label' => $help->_('%d days (%d weeks)',  14, 2)], // 2-4
			['value' => 28 * 24 * 60, 'label' => $help->_('%d days (%d weeks)',  28, 4)], // 2-4
			//                                       ->__('%d days (%d weeks)')           // 5+
			['value' => 31 * 24 * 60, 'label' => $help->__('%d days (%d month)', 31, 1)], // 1
			['value' => 62 * 24 * 60, 'label' => $help->_('%d days (%d months)', 62, 2)], // 2-4
			['value' => 93 * 24 * 60, 'label' => $help->_('%d days (%d months)', 93, 3)]  // 2-4
			//                                       ->__('%d days (%d months)')          // 5+
		];
	}
}