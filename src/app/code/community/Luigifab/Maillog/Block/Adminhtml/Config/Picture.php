<?php
/**
 * Created D/13/08/2017
 * Updated V/08/10/2021
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

class Luigifab_Maillog_Block_Adminhtml_Config_Picture extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_template = 'luigifab/maillog/picture.phtml';

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$config = @unserialize(Mage::getStoreConfig('maillog_directives/general/special_config'), ['allowed_classes' => false]);
		$config = is_array($config) ? $config : [];

		uasort($config, static function ($a, $b) {
			if (!is_array($a) || !is_array($b) || !array_key_exists('c', $a) || !array_key_exists('c', $b)) return 0;
			return strnatcasecmp($a['c'], $b['c']);
		});

		foreach ($config as &$data) {
			// $data => Array(
			//  [c] => test
			//  [d] =>
			//  [0] => Array( [w] => 560 [h] => 480 )
			//  [1] => Array( [b] => 320 [w] => 209 [h] => 177 )
			//  [3] => Array( [b] => 768 [w] => 420 [h] => 360 )
			//  [2] => Array( [b] => 380 [w] => 252 [h] => 216 )
			// )
			uasort($data, static function ($a, $b) {
				if (!is_array($a) || !is_array($b) || !array_key_exists('b', $a) || !array_key_exists('b', $b)) return 0;
				return ($a['b'] == $b['b']) ? 0 : (($a['b'] < $b['b']) ? -1 : 1);
			});
		}
		unset($data);

		$this->setHtmlId('row_'.$element->getHtmlId());
		$this->setScopeLabel($element->getScopeLabel());
		$this->setLabel($element->getLabel());
		$this->setData('config', $config);

		return $this->toHtml();
	}
}