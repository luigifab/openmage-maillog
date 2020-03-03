<?php
/**
 * Created D/13/08/2017
 * Updated V/03/01/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
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

		$config = @unserialize(Mage::getStoreConfig('maillog_directives/general/special_config'));
		$config = is_array($config) ? $config : [];

		$this->setHtmlId('row_'.$element->getHtmlId());
		$this->setScopeLabel($element->getScopeLabel());
		$this->setLabel($element->getLabel());
		$this->setConfig($config);

		return $this->toHtml();
	}
}