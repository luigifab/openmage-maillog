<?php
/**
 * Created D/13/08/2017
 * Updated V/22/10/2021
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Lifetime extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_template = 'luigifab/maillog/lifetime.phtml';

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$config = @unserialize(Mage::getStoreConfig('maillog/general/special_config'), ['allowed_classes' => false]);
		$types  = $this->helper('maillog')->getAllTypes();

		if (!empty($config) && is_array($config)) {
			// ajoute les types configurés ayant disparus
			foreach ($config as $key => $value) {
				$type = mb_substr($key, 0, mb_strpos($key, '_'));
				if (!in_array($type, $types) && !in_array($type, ['without', 'all']))
					$types[] = $type;
			}
		}

		array_push($types, 'without', 'all');

		$this->setHtmlId('row_'.$element->getHtmlId());
		$this->setScopeLabel($element->getScopeLabel());
		$this->setLabel($element->getLabel());
		$this->setData('config', $config);
		$this->setData('types', $types);

		return $this->toHtml();
	}
}