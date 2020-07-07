<?php
/**
 * Created W/11/11/2015
 * Updated S/16/05/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
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

class Luigifab_Maillog_Model_Source_Type {

	public function toOptionArray() {

		$config  = Mage::getConfig()->getNode('global/models/maillog/adaptators')->asArray();
		$options = [];

		foreach ($config as $code => $key)
			$options[$key] = ['value' => $key, 'label' => Mage::getSingleton($key)->getType()];

		ksort($options);
		return $options;
	}
}