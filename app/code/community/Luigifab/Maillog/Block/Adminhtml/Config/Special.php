<?php
/**
 * Created D/13/08/2017
 * Updated S/31/03/2018
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

class Luigifab_Maillog_Block_Adminhtml_Config_Special extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$config = @unserialize(Mage::getStoreConfig('maillog/general/special_config'));
		$types  = $this->helper('maillog')->getAllTypes();

		array_push($types, 'without', 'all');

		if (!empty($config) && is_array($config)) {
			// ajoute les types configurés ayant disparus
			foreach ($config as $key => $value) {
				$type = substr($key, 0, strpos($key, '_'));
				if (!in_array($type, $types))
					array_push($types, $type);
			}
		}

		return $this->getLayout()->createBlock('core/template')
			->setTemplate('luigifab/maillog/special.phtml')
			->setHtmlId('row_'.$element->getHtmlId())
			->setConfig($config)
			->setTypes($types)
			->toHtml();
	}
}