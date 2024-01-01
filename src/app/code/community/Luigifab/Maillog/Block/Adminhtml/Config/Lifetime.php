<?php
/**
 * Created D/13/08/2017
 * Updated V/22/12/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://github.com/luigifab/openmage-maillog
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

class Luigifab_Maillog_Block_Adminhtml_Config_Lifetime extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_template = 'luigifab/maillog/lifetime.phtml';

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$helper = $this->helper('maillog');
		$types  = $helper->getAllTypes();
		$config = $helper->getConfigUnserialized('maillog/general/special_config');

		foreach (array_keys($config) as $key) {
			$type = mb_substr($key, 0, mb_strpos($key, '_'));
			if (!in_array($type, $types) && !in_array($type, ['all', 'without']))
				$types[$type] = $type;
		}

		unset($types['--']);
		$types[] = 'all'; // or default

		$this->setData('element', $element);
		$this->setData('config', $config);
		$this->setData('types', $types);

		return $this->toHtml();
	}
}